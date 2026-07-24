<?php

function lensDashboardThemeVariables(string $html, string $selector): array
{
    $pattern = '/'.preg_quote($selector, '/').'\s*\{(?<declarations>.*?)\n\s*\}/s';

    preg_match($pattern, $html, $matches);
    preg_match_all(
        '/(?<name>--lens-[a-z-]+):\s*(?<value>#[0-9a-f]{6});/i',
        $matches['declarations'] ?? '',
        $variables,
    );

    return array_combine($variables['name'], $variables['value']);
}

function lensDashboardContrastRatio(string $foreground, string $background): float
{
    $luminance = function (string $color): float {
        $channels = array_map(
            fn (string $channel): int => hexdec($channel),
            str_split(ltrim($color, '#'), 2),
        );

        $linear = array_map(
            function (int $channel): float {
                $value = $channel / 255;

                return $value <= 0.04045
                    ? $value / 12.92
                    : (($value + 0.055) / 1.055) ** 2.4;
            },
            $channels,
        );

        return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    };

    $foregroundLuminance = $luminance($foreground);
    $backgroundLuminance = $luminance($background);

    return (max($foregroundLuminance, $backgroundLuminance) + 0.05)
        / (min($foregroundLuminance, $backgroundLuminance) + 0.05);
}

test('dashboard returns 200 in testing environment', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertStatus(200);
});

test('dashboard returns 403 when environment is not in allowed list', function () {
    // Remove 'testing' from allowed envs — the app still runs under 'testing'
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['local']);

    $this->get(route('lens-for-laravel.dashboard'))
        ->assertStatus(403);
});

test('dashboard renders the main blade view', function () {
    $response = $this->get(route('lens-for-laravel.dashboard'));

    $response->assertStatus(200)
        ->assertSee('Lens For Laravel');
});

test('dashboard uses the accessible website theme in light and dark modes', function (string $selector) {
    $html = $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee('href="#scanner-content"', false)
        ->assertSee('tabindex="-1"', false)
        ->assertSee(':aria-pressed="theme === \'dark\'"', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('aria-modal="true"', false)
        ->assertSee("localStorage.getItem('lens-theme')", false)
        ->assertSee('JetBrains+Mono', false)
        ->getContent();

    $colors = lensDashboardThemeVariables($html, $selector);

    foreach (['--lens-content', '--lens-body', '--lens-muted'] as $token) {
        expect(lensDashboardContrastRatio($colors[$token], $colors['--lens-page']))
            ->toBeGreaterThanOrEqual(7.0, "{$selector} {$token} should meet an AAA reading-text target.");
    }

    foreach (['--lens-subtle', '--lens-accent'] as $token) {
        expect(lensDashboardContrastRatio($colors[$token], $colors['--lens-page']))
            ->toBeGreaterThanOrEqual(4.5, "{$selector} {$token} should meet the AA normal-text target.");
    }

    foreach (['--lens-control', '--lens-focus'] as $token) {
        expect(lensDashboardContrastRatio($colors[$token], $colors['--lens-page']))
            ->toBeGreaterThanOrEqual(3.0, "{$selector} {$token} should meet the non-text contrast target.");
    }

    expect(lensDashboardContrastRatio($colors['--lens-on-accent'], $colors['--lens-accent-solid']))
        ->toBeGreaterThanOrEqual(4.5, "{$selector} solid accent content should meet the AA normal-text target.");
})->with([
    'light mode' => ':root',
    'dark mode' => '.dark',
]);

test('state recorder inherits the dashboard theme and accessible controls', function () {
    $this->get(route('lens-for-laravel.states.recorder', [
        'target' => url('/states'),
    ]))
        ->assertOk()
        ->assertSee('href="#recorder-preview"', false)
        ->assertSee('tabindex="-1"', false)
        ->assertSee("localStorage.getItem('lens-theme')", false)
        ->assertSee('JetBrains+Mono', false)
        ->assertSee('role="status"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('--lens-accent: #c52b21', false)
        ->assertSee('--lens-accent: #ff8a8a', false);
});

test('dashboard footer lists every supported Laravel version', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee('Laravel 10 / 11 /')
        ->assertSee('12 / 13')
        ->assertSee('Support the creator')
        ->assertSee('https://buycoffee.to/jakub-lipinski', false);

    $this->get(route('lens-for-laravel.dashboard', ['lens_locale' => 'pl']))
        ->assertOk()
        ->assertSee('Postaw autorowi kawę');
});

test('dashboard explains when AI Fix is disabled and hides its actions', function () {
    $this->app['config']->set('lens-for-laravel.ai_enabled', false);

    $this->get(route('lens-for-laravel.dashboard'))
        ->assertStatus(200)
        ->assertSee('AI Fix unavailable')
        ->assertSee('Core accessibility scanning remains available')
        ->assertDontSee('title="Fix with AI"', false);
});

test('dashboard marks an applied AI fix as pending verification until the next scan', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee('AI Fix applied · pending re-scan')
        ->assertSee('This issue remains counted until a new axe-core scan verifies the result.')
        ->assertSee("this.fixIssue.aiFixStatus = 'pending_verification'", false)
        ->assertSee("issue.aiFixStatus === 'pending_verification'", false)
        ->assertSee("issue.aiFixStatus !== 'pending_verification'", false);
});

test('dashboard lets users edit and review an AI fix before applying it', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee('Edit proposed fix')
        ->assertSee('Reset AI version')
        ->assertSee('Preview changes')
        ->assertSee('x-model="editedFixCode"', false)
        ->assertSee('@keydown.tab.prevent="handleFixEditorTab($event)"', false)
        ->assertSee('@keydown.ctrl.enter.prevent="applyFix()"', false)
        ->assertSee('fixedCode: this.editedFixCode', false)
        ->assertSee('this.editedFixCode.split', false);

    $this->get(route('lens-for-laravel.dashboard', ['lens_locale' => 'pl']))
        ->assertOk()
        ->assertSee('Edytuj proponowaną poprawkę')
        ->assertSee('Przywróć wersję AI')
        ->assertSee('Podejrzyj zmiany');
});

test('dashboard streams fix all queues for WCAG A and AA issues', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee('Fix All A')
        ->assertSee('Fix All AA')
        ->assertSee('@click="requestAllAiFixes(\'a\')"', false)
        ->assertSee('@click="requestAllAiFixes(\'aa\')"', false)
        ->assertSee('const workerCount = Math.min(3, this.fixQueue.length)', false)
        ->assertSee('workerIndex * 250', false)
        ->assertSee("['ready', 'applied', 'rejected', 'error'].includes(item.status)", false)
        ->assertSee("['queued', 'loading'].includes(item.status)", false)
        ->assertSee('x-for="(item, index) in fixQueue"', false)
        ->assertSee('@click="goToFix(index)"', false)
        ->assertSee('flex-col items-center justify-center gap-0.5', false)
        ->assertSee("item.status === 'ready' || item.status === 'applied'", false)
        ->assertDontSee('absolute -right-1 -top-1', false)
        ->assertSee('retryCurrentFix()', false)
        ->assertSee('item.editedCode = this.editedFixCode', false);

    $this->get(route('lens-for-laravel.dashboard', ['lens_locale' => 'pl']))
        ->assertOk()
        ->assertSee('Napraw wszystkie A')
        ->assertSee('Napraw wszystkie AA')
        ->assertSee('Ta poprawka jest jeszcze generowana');
});

test('dashboard keeps issue actions bound to the current scan results', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee(':key="issue._lensDomKey"', false)
        ->assertDontSee('<template x-for="(issue, index) in filteredIssues" :key="index">', false)
        ->assertSee('this.prepareIssues(data.issues || [])', false)
        ->assertSee('_lensDomKey: `lens-issue-${++this.issueDomSequence}`', false)
        ->assertSee('this.cancelAiFixRequest()', false)
        ->assertSee('signal: controller.signal', false)
        ->assertSee('requestId !== this.fixRequestSequence', false)
        ->assertSee('this.fixQueue.forEach(item => item.controller?.abort())', false);
});

test('dashboard translates the pending AI verification status', function () {
    $this->get(route('lens-for-laravel.dashboard', ['lens_locale' => 'pl']))
        ->assertOk()
        ->assertSee('AI Fix zastosowany · oczekuje na ponowny skan')
        ->assertSee('Problem pozostaje w statystykach');
});

test('dashboard renders interactive state scan controls', function () {
    $response = $this->get(route('lens-for-laravel.dashboard'));

    $response->assertStatus(200)
        ->assertSee('States')
        ->assertSee('Interactive State Recorder')
        ->assertSee('Record')
        ->assertSee('openStateRecorder', false)
        ->assertSee('Interaction Script')
        ->assertSee('scanInteractiveStates', false)
        ->assertSee(route('lens-for-laravel.scan.states'), false);
});

test('dashboard renders the WCAG standard selector with a 2.0 default', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee('WCAG standard')
        ->assertSee("['2.0', '2.1', '2.2']", false)
        ->assertSee('const LENS_DEFAULT_WCAG_VERSION = "2.0"', false);
});

test('dashboard displays issue URLs in history comparisons', function () {
    $this->get(route('lens-for-laravel.dashboard'))
        ->assertOk()
        ->assertSee('comparisonIssueUrl', false)
        ->assertSee('issue.url', false);
});

test('state recorder renders for same origin target urls', function () {
    $response = $this->get(route('lens-for-laravel.states.recorder', [
        'target' => url('/states'),
    ]));

    $response->assertStatus(200)
        ->assertSee('Lens State Recorder')
        ->assertSee('Send Script');
});

test('dashboard can switch to Polish locale', function () {
    $response = $this->get(route('lens-for-laravel.dashboard', [
        'lens_locale' => 'pl',
    ]));

    $response->assertStatus(200)
        ->assertSee('Skaner')
        ->assertSee('Cel analizy')
        ->assertSee('Rejestrator stanów interaktywnych');
});

test('Polish dashboard translates history comparisons modals and client errors', function () {
    $this->get(route('lens-for-laravel.dashboard', ['lens_locale' => 'pl']))
        ->assertOk()
        ->assertSee('Trend problemów (ostatnie 30 skanów)')
        ->assertSee('Historia skanów')
        ->assertSee('Porównanie')
        ->assertSee('Naprawione')
        ->assertSee('Wyjaśnienie AI')
        ->assertSee('Podgląd elementu')
        ->assertSee('Nie udało się wygenerować raportu PDF.')
        ->assertDontSee('Issue Trend (Last 30 Scans)');
});

test('state recorder can switch to Spanish locale', function () {
    $response = $this->get(route('lens-for-laravel.states.recorder', [
        'target' => url('/states'),
        'lens_locale' => 'es',
    ]));

    $response->assertStatus(200)
        ->assertSee('Grabador de estados Lens')
        ->assertSee('Enviar script');
});
