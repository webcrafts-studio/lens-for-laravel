<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use LensForLaravel\LensForLaravel\Models\Scan;
use LensForLaravel\LensForLaravel\Services\AiFixer;
use LensForLaravel\LensForLaravel\Services\AxeScanner;
use LensForLaravel\LensForLaravel\Services\FileLocator;
use LensForLaravel\LensForLaravel\Services\SiteCrawler;
use Spatie\Browsershot\Browsershot;

// The prefix and middleware for these routes are automatically applied
// by the LensForLaravelServiceProvider based on your config.

// Shared domain validation rule used by all scan-related endpoints.
// Rejects non-HTTP(S) schemes (e.g. file://, gopher://) and any host
// that does not match APP_URL, preventing SSRF and host-header spoofing.
$domainRule = function (string $attribute, string $value, Closure $fail): void {
    $scheme = parse_url($value, PHP_URL_SCHEME);
    if (! in_array($scheme, ['http', 'https'], true)) {
        $fail('Only HTTP and HTTPS URLs are allowed.');

        return;
    }

    $appHost = parse_url(config('app.url', ''), PHP_URL_HOST);
    if (! $appHost) {
        $fail('APP_URL is not configured correctly. Cannot validate the target domain.');

        return;
    }

    if (parse_url($value, PHP_URL_HOST) !== $appHost) {
        $fail("Scanning external domains is not allowed. URL must be on the {$appHost} domain.");
    }
};

$resolveEditableSourceFile = function (string $fileName): array {
    if (str_contains($fileName, '..') || str_starts_with($fileName, DIRECTORY_SEPARATOR)) {
        return ['error' => response()->json(['status' => 'error', 'message' => 'Invalid file path.'], 422)];
    }

    if (str_ends_with($fileName, '.blade.php')) {
        $basePath = resource_path('views');
        $fullPath = realpath($basePath.DIRECTORY_SEPARATOR.$fileName);

        if (! $fullPath || ! str_starts_with($fullPath, $basePath.DIRECTORY_SEPARATOR)) {
            return ['error' => response()->json(['status' => 'error', 'message' => 'File access denied.'], 403)];
        }

        return ['path' => $fullPath, 'type' => 'blade'];
    }

    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (str_starts_with($fileName, 'js/') && in_array($extension, ['js', 'jsx', 'ts', 'tsx'], true)) {
        $basePath = resource_path('js');
        $relativePath = substr($fileName, 3);
        $fullPath = realpath($basePath.DIRECTORY_SEPARATOR.$relativePath);

        if (! $fullPath || ! str_starts_with($fullPath, $basePath.DIRECTORY_SEPARATOR)) {
            return ['error' => response()->json(['status' => 'error', 'message' => 'File access denied.'], 403)];
        }

        return ['path' => $fullPath, 'type' => 'react'];
    }

    return ['error' => response()->json(['status' => 'error', 'message' => 'Only .blade.php files and React files under resources/js can be modified.'], 422)];
};

Route::get('/dashboard', function () {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        abort(403, 'Lens For Laravel is not allowed in this environment.');
    }

    return view('lens-for-laravel::dashboard');
})->name('lens-for-laravel.dashboard');

Route::post('/crawl', function (Request $request) use ($domainRule) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        abort(403, 'Lens For Laravel is not allowed in this environment.');
    }

    $request->validate([
        'url' => ['required', 'url', $domainRule],
    ]);

    try {
        $crawler = app(SiteCrawler::class);
        $urls = $crawler->crawl($request->url, config('lens-for-laravel.crawl_max_pages', 50));

        return response()->json([
            'status' => 'success',
            'urls' => $urls,
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
})->name('lens-for-laravel.crawl')->middleware('throttle:5,1');

Route::post('/scan', function (Request $request) use ($domainRule) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        abort(403, 'Lens For Laravel is not allowed in this environment.');
    }

    $request->validate([
        'url' => ['required', 'url', $domainRule],
    ]);

    try {
        $scanner = app(AxeScanner::class);
        $issues = $scanner->scan($request->url);

        $fileLocator = app(FileLocator::class);

        // Enhance each issue with its estimated file location
        foreach ($issues as $issue) {
            $location = $fileLocator->locate($issue->htmlSnippet, $issue->selector);
            if ($location) {
                $issue->fileName = $location['file'];
                $issue->lineNumber = $location['line'];
            }
        }

        return response()->json([
            'status' => 'success',
            'issues' => $issues,
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
})->name('lens-for-laravel.scan')->middleware('throttle:10,1');

Route::post('/preview', function (Request $request) use ($domainRule) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        abort(403, 'Lens For Laravel is not allowed in this environment.');
    }

    $request->validate([
        'url' => ['required', 'url', $domainRule],
        'selector' => ['required', 'string', 'max:500'],
    ]);

    $selectorJson = json_encode($request->selector);

    // Injected after page load: scroll the element into view and draw a
    // translucent dark overlay with a red highlight rectangle on top of it.
    $highlightScript = <<<JS
    (function () {
        try {
            var el = document.querySelector({$selectorJson});
            if (!el) return;
            el.scrollIntoView({ behavior: 'instant', block: 'center' });
            var r = el.getBoundingClientRect();
            // Full-page dimming overlay
            var dim = document.createElement('div');
            dim.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);pointer-events:none;z-index:2147483646;';
            document.documentElement.appendChild(dim);
            // Red highlight box cut-out (just a border, no fill — so the element stays readable)
            var box = document.createElement('div');
            box.style.cssText = 'position:fixed;pointer-events:none;z-index:2147483647;box-sizing:border-box;border:3px solid #E11D48;outline:1px solid rgba(0,0,0,0.5);';
            box.style.top    = r.top    + 'px';
            box.style.left   = r.left   + 'px';
            box.style.width  = r.width  + 'px';
            box.style.height = r.height + 'px';
            document.documentElement.appendChild(box);
        } catch (e) {}
    })();
    JS;

    try {
        $screenshot = Browsershot::url($request->url)
            ->noSandbox()
            ->waitUntilNetworkIdle()
            ->windowSize(1280, 800)
            ->setOption('addScriptTag', json_encode(['content' => $highlightScript]))
            ->screenshot();

        return response($screenshot, 200, ['Content-Type' => 'image/png']);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
})->name('lens-for-laravel.preview')->middleware('throttle:20,1');

Route::post('/fix/suggest', function (Request $request) {
    try {
        if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
            return response()->json(['status' => 'error', 'message' => 'Lens For Laravel is not allowed in this environment.'], 403);
        }

        $validated = $request->validate([
            'htmlSnippet' => ['required', 'string', 'max:2000'],
            'description' => ['required', 'string', 'max:500'],
            'fileName' => ['required', 'string', 'max:500'],
            'lineNumber' => ['required', 'integer', 'min:1'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ]);

        $result = app(AiFixer::class)->suggestFix(
            $validated['htmlSnippet'],
            $validated['description'],
            $validated['fileName'],
            $validated['lineNumber'],
            $validated['tags'] ?? []
        );

        return response()->json(['status' => 'success', ...$result]);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'errors' => $e->errors(),
        ], 422);
    } catch (Throwable $e) {
        logger()->error('Lens AI fix suggestion failed', ['error' => $e->getMessage()]);

        return response()->json([
            'status' => 'error',
            'message' => app()->isLocal() ? $e->getMessage() : 'The AI provider returned an error. Check your API key configuration and try again.',
        ], 500);
    }
})->name('lens-for-laravel.fix.suggest')->middleware('throttle:20,1');

Route::post('/fix/apply', function (Request $request) use ($resolveEditableSourceFile) {
    try {
        if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
            return response()->json(['status' => 'error', 'message' => 'Lens For Laravel is not allowed in this environment.'], 403);
        }

        $validated = $request->validate([
            'fileName' => ['required', 'string', 'max:500'],
            'originalCode' => ['required', 'string'],
            'fixedCode' => ['required', 'string'],
        ]);

        $fileName = $validated['fileName'];
        $originalCode = $validated['originalCode'];
        $fixedCode = $validated['fixedCode'];

        $source = $resolveEditableSourceFile($fileName);
        if (isset($source['error'])) {
            return $source['error'];
        }

        $content = file_get_contents($source['path']);

        if (! str_contains($content, $originalCode)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Original code not found in file. The file may have been modified since the fix was generated.',
            ], 422);
        }

        // Protect against prompt-injection attacks where a malicious scanned page causes
        // the AI to embed RCE payloads in the suggested fix.
        // Check unconditionally for server-side code execution functions — these have no
        // place in a Blade template regardless of what was in the original code block.
        $rcePatterns = ['shell_exec(', 'system(', 'exec(', 'passthru(', 'proc_open(', 'popen(', 'eval('];
        foreach ($rcePatterns as $pattern) {
            if (str_contains($fixedCode, $pattern)) {
                logger()->warning('Lens AI fix blocked: RCE pattern detected in AI response', ['pattern' => $pattern]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'The AI-generated fix was blocked because it contains potentially dangerous code. Please apply the fix manually after reviewing.',
                ], 422);
            }
        }

        // Reject PHP open tags introduced by the AI that were not present in the
        // original code block — a legitimate accessibility fix never needs to add raw PHP.
        foreach (['<?php', '<?='] as $phpTag) {
            if (str_contains($fixedCode, $phpTag) && ! str_contains($originalCode, $phpTag)) {
                logger()->warning('Lens AI fix blocked: unexpected PHP tag in AI response', ['tag' => $phpTag]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'The AI-generated fix was blocked because it introduces unexpected PHP code. Please apply the fix manually after reviewing.',
                ], 422);
            }
        }

        // LOCK_EX ensures the write is atomic and prevents concurrent overwrites.
        file_put_contents($source['path'], str_replace($originalCode, $fixedCode, $content), LOCK_EX);

        return response()->json(['status' => 'success']);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'errors' => $e->errors(),
        ], 422);
    } catch (Throwable $e) {
        logger()->error('Lens AI fix apply failed', ['error' => $e->getMessage()]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to apply fix: '.$e->getMessage(),
        ], 500);
    }
})->name('lens-for-laravel.fix.apply')->middleware('throttle:20,1');

Route::post('/report/pdf', function (Request $request) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        abort(403, 'Lens For Laravel is not allowed in this environment.');
    }

    $request->validate([
        'issues' => ['required', 'array', 'max:500'],
        'issues.*.id' => ['nullable', 'string', 'max:100'],
        'issues.*.impact' => ['nullable', 'string', 'in:critical,serious,moderate,minor,unknown'],
        'issues.*.description' => ['nullable', 'string', 'max:1000'],
        'issues.*.htmlSnippet' => ['nullable', 'string', 'max:5000'],
        'issues.*.selector' => ['nullable', 'string', 'max:500'],
        'issues.*.tags' => ['nullable', 'array'],
        'issues.*.tags.*' => ['string', 'max:50'],
        'url' => ['required', 'url', 'max:2048'],
    ]);

    try {
        $html = view('lens-for-laravel::report', [
            'issues' => $request->issues,
            'url' => $request->url,
            'generatedAt' => now(),
        ])->render();

        $pdf = Browsershot::html($html)
            ->noSandbox()
            ->format('A4')
            ->margins(0, 0, 0, 0)
            ->pdf();

        $filename = 'accessibility-report-'.now()->format('Y-m-d').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    } catch (Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
})->name('lens-for-laravel.report.pdf');

// ─── Scan History ────────────────────────────────────────────────────

Route::get('/history/trends', function () {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        return response()->json(['status' => 'error', 'message' => 'Not allowed in this environment.'], 403);
    }

    try {
        $trends = Scan::select('id', 'url', 'total_issues', 'level_a_count', 'level_aa_count', 'level_aaa_count', 'created_at')
            ->latest()
            ->take(30)
            ->get()
            ->sortBy('created_at')
            ->values();

        return response()->json(['status' => 'success', 'trends' => $trends]);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
})->name('lens-for-laravel.history.trends');

Route::get('/history/{id}/compare/{compareId}', function (int $id, int $compareId) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        return response()->json(['status' => 'error', 'message' => 'Not allowed in this environment.'], 403);
    }

    try {
        $base = Scan::with('issues')->find($id);
        $compare = Scan::with('issues')->find($compareId);

        if (! $base || ! $compare) {
            return response()->json(['status' => 'error', 'message' => 'Scan not found.'], 404);
        }

        $baseKeys = $base->issues->map(fn ($i) => $i->rule_id.'|'.$i->selector)->toArray();
        $compareKeys = $compare->issues->map(fn ($i) => $i->rule_id.'|'.$i->selector)->toArray();

        $baseKeySet = array_flip($baseKeys);
        $compareKeySet = array_flip($compareKeys);

        $new = $compare->issues->filter(fn ($i) => ! isset($baseKeySet[$i->rule_id.'|'.$i->selector]))->values();
        $fixed = $base->issues->filter(fn ($i) => ! isset($compareKeySet[$i->rule_id.'|'.$i->selector]))->values();
        $remaining = $base->issues->filter(fn ($i) => isset($compareKeySet[$i->rule_id.'|'.$i->selector]))->values();

        return response()->json([
            'status' => 'success',
            'base' => $base->only('id', 'url', 'created_at', 'total_issues'),
            'compare' => $compare->only('id', 'url', 'created_at', 'total_issues'),
            'new' => $new,
            'fixed' => $fixed,
            'remaining' => $remaining,
        ]);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
})->name('lens-for-laravel.history.compare');

Route::post('/history', function (Request $request) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        return response()->json(['status' => 'error', 'message' => 'Not allowed in this environment.'], 403);
    }

    try {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'scanMode' => ['required', 'string', 'in:single,website,multiple'],
            'urlsScanned' => ['nullable', 'array'],
            'urlsScanned.*' => ['string', 'max:2048'],
            'issues' => ['required', 'array', 'max:1000'],
            'issues.*.id' => ['required', 'string', 'max:255'],
            'issues.*.impact' => ['nullable', 'string', 'max:20'],
            'issues.*.description' => ['nullable', 'string', 'max:1000'],
            'issues.*.helpUrl' => ['nullable', 'string', 'max:2048'],
            'issues.*.htmlSnippet' => ['nullable', 'string'],
            'issues.*.selector' => ['nullable', 'string', 'max:500'],
            'issues.*.tags' => ['nullable', 'array'],
            'issues.*.tags.*' => ['string', 'max:50'],
            'issues.*.url' => ['nullable', 'string', 'max:2048'],
            'issues.*.fileName' => ['nullable', 'string', 'max:500'],
            'issues.*.lineNumber' => ['nullable', 'integer', 'min:1'],
        ]);

        $issues = $validated['issues'];
        $levelA = count(array_filter($issues, fn ($i) => isset($i['tags']) && in_array('wcag2a', $i['tags'])));
        $levelAA = count(array_filter($issues, fn ($i) => isset($i['tags']) && in_array('wcag2aa', $i['tags'])));
        $levelAAA = count(array_filter($issues, fn ($i) => isset($i['tags']) && in_array('wcag2aaa', $i['tags'])));

        $scan = DB::transaction(function () use ($validated, $issues, $levelA, $levelAA, $levelAAA) {
            $scan = Scan::create([
                'url' => $validated['url'],
                'scan_mode' => $validated['scanMode'],
                'urls_scanned' => $validated['urlsScanned'] ?? [],
                'total_issues' => count($issues),
                'level_a_count' => $levelA,
                'level_aa_count' => $levelAA,
                'level_aaa_count' => $levelAAA,
            ]);

            foreach ($issues as $issue) {
                $scan->issues()->create([
                    'rule_id' => $issue['id'],
                    'impact' => $issue['impact'] ?? null,
                    'description' => $issue['description'] ?? null,
                    'help_url' => $issue['helpUrl'] ?? null,
                    'html_snippet' => $issue['htmlSnippet'] ?? null,
                    'selector' => $issue['selector'] ?? null,
                    'tags' => $issue['tags'] ?? null,
                    'url' => $issue['url'] ?? null,
                    'file_name' => $issue['fileName'] ?? null,
                    'line_number' => $issue['lineNumber'] ?? null,
                ]);
            }

            return $scan;
        });

        return response()->json(['status' => 'success', 'scan' => $scan], 201);
    } catch (ValidationException $e) {
        return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
})->name('lens-for-laravel.history.store')->middleware('throttle:10,1');

Route::get('/history', function (Request $request) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        return response()->json(['status' => 'error', 'message' => 'Not allowed in this environment.'], 403);
    }

    try {
        $scans = Scan::select('id', 'url', 'scan_mode', 'total_issues', 'level_a_count', 'level_aa_count', 'level_aaa_count', 'created_at')
            ->latest()
            ->paginate(15);

        return response()->json(['status' => 'success', 'scans' => $scans]);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
})->name('lens-for-laravel.history.index');

Route::get('/history/{id}', function (int $id) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        return response()->json(['status' => 'error', 'message' => 'Not allowed in this environment.'], 403);
    }

    try {
        $scan = Scan::with('issues')->find($id);

        if (! $scan) {
            return response()->json(['status' => 'error', 'message' => 'Scan not found.'], 404);
        }

        return response()->json(['status' => 'success', 'scan' => $scan]);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
})->name('lens-for-laravel.history.show');

Route::delete('/history/{id}', function (int $id) {
    if (! in_array(app()->environment(), config('lens-for-laravel.enabled_environments', ['local']))) {
        return response()->json(['status' => 'error', 'message' => 'Not allowed in this environment.'], 403);
    }

    try {
        $scan = Scan::find($id);

        if (! $scan) {
            return response()->json(['status' => 'error', 'message' => 'Scan not found.'], 404);
        }

        $scan->delete();

        return response()->json(['status' => 'success']);
    } catch (Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
})->name('lens-for-laravel.history.destroy')->middleware('throttle:10,1');
