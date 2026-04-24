<?php

use LensForLaravel\LensForLaravel\Models\Scan;
use LensForLaravel\LensForLaravel\Models\ScanIssue;

function createScanPayload(array $overrides = []): array
{
    return array_merge([
        'url' => 'http://localhost',
        'scanMode' => 'single',
        'urlsScanned' => ['http://localhost'],
        'issues' => [
            [
                'id' => 'color-contrast',
                'impact' => 'serious',
                'description' => 'Elements must have sufficient color contrast',
                'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.4/color-contrast',
                'htmlSnippet' => '<p style="color: #aaa">Hello</p>',
                'selector' => 'p',
                'tags' => ['wcag2aa'],
                'url' => 'http://localhost',
                'fileName' => 'welcome.blade.php',
                'lineNumber' => 10,
            ],
            [
                'id' => 'image-alt',
                'impact' => 'critical',
                'description' => 'Images must have alternate text',
                'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.4/image-alt',
                'htmlSnippet' => '<img src="photo.jpg">',
                'selector' => 'img',
                'tags' => ['wcag2a'],
            ],
        ],
    ], $overrides);
}

// ─── POST /history (store) ──────────────────────────────────────────

test('store creates scan with issues', function () {
    $response = $this->postJson(route('lens-for-laravel.history.store'), createScanPayload());

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('scan.url', 'http://localhost')
        ->assertJsonPath('scan.scan_mode', 'single')
        ->assertJsonPath('scan.total_issues', 2)
        ->assertJsonPath('scan.level_a_count', 1)
        ->assertJsonPath('scan.level_aa_count', 1);

    expect(Scan::count())->toBe(1);
    expect(ScanIssue::count())->toBe(2);
});

test('store validates required fields', function () {
    $response = $this->postJson(route('lens-for-laravel.history.store'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['url', 'scanMode', 'issues']);
});

test('store validates scanMode enum', function () {
    $payload = createScanPayload(['scanMode' => 'invalid']);

    $this->postJson(route('lens-for-laravel.history.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scanMode']);
});

test('store validates issues max count', function () {
    $issues = array_fill(0, 1001, [
        'id' => 'rule-1',
        'impact' => 'minor',
        'description' => 'test',
        'tags' => [],
    ]);

    $payload = createScanPayload(['issues' => $issues]);

    $this->postJson(route('lens-for-laravel.history.store'), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['issues']);
});

test('store is blocked in non-allowed environment', function () {
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['production']);

    $this->postJson(route('lens-for-laravel.history.store'), createScanPayload())
        ->assertStatus(403);
});

// ─── GET /history (index) ───────────────────────────────────────────

test('index returns paginated scans without issues', function () {
    Scan::create([
        'url' => 'http://localhost',
        'scan_mode' => 'single',
        'urls_scanned' => [],
        'total_issues' => 0,
        'level_a_count' => 0,
        'level_aa_count' => 0,
        'level_aaa_count' => 0,
    ]);

    $response = $this->getJson(route('lens-for-laravel.history.index'));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'scans.data');
});

test('index is blocked in non-allowed environment', function () {
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['production']);

    $this->getJson(route('lens-for-laravel.history.index'))
        ->assertStatus(403);
});

// ─── GET /history/{id} (show) ───────────────────────────────────────

test('show returns scan with issues', function () {
    $this->postJson(route('lens-for-laravel.history.store'), createScanPayload());

    $scan = Scan::first();

    $response = $this->getJson(route('lens-for-laravel.history.show', $scan->id));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('scan.id', $scan->id)
        ->assertJsonCount(2, 'scan.issues');
});

test('show returns 404 for missing scan', function () {
    $this->getJson(route('lens-for-laravel.history.show', 999))
        ->assertStatus(404);
});

// ─── DELETE /history/{id} ───────────────────────────────────────────

test('destroy deletes scan and cascades issues', function () {
    $this->postJson(route('lens-for-laravel.history.store'), createScanPayload());

    $scan = Scan::first();

    $this->deleteJson(route('lens-for-laravel.history.destroy', $scan->id))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(Scan::count())->toBe(0);
    expect(ScanIssue::count())->toBe(0);
});

test('destroy returns 404 for missing scan', function () {
    $this->deleteJson(route('lens-for-laravel.history.destroy', 999))
        ->assertStatus(404);
});

// ─── GET /history/trends ────────────────────────────────────────────

test('trends returns last 30 scans ordered by date', function () {
    foreach (range(1, 5) as $i) {
        Scan::create([
            'url' => 'http://localhost',
            'scan_mode' => 'single',
            'urls_scanned' => [],
            'total_issues' => $i * 2,
            'level_a_count' => $i,
            'level_aa_count' => 0,
            'level_aaa_count' => 0,
            'created_at' => now()->subDays(5 - $i),
        ]);
    }

    $response = $this->getJson(route('lens-for-laravel.history.trends'));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(5, 'trends');

    // Should be ordered by created_at ascending
    $trends = $response->json('trends');
    expect($trends[0]['total_issues'])->toBe(2);
    expect($trends[4]['total_issues'])->toBe(10);
});

// ─── GET /history/{id}/compare/{compareId} ──────────────────────────

test('compare returns new, fixed, and remaining issues', function () {
    // Base scan: has color-contrast + image-alt
    $this->postJson(route('lens-for-laravel.history.store'), createScanPayload());
    $baseScan = Scan::first();

    // Compare scan: has image-alt + link-name (color-contrast was fixed, link-name is new)
    $this->postJson(route('lens-for-laravel.history.store'), createScanPayload([
        'issues' => [
            [
                'id' => 'image-alt',
                'impact' => 'critical',
                'description' => 'Images must have alternate text',
                'selector' => 'img',
                'tags' => ['wcag2a'],
            ],
            [
                'id' => 'link-name',
                'impact' => 'serious',
                'description' => 'Links must have discernible text',
                'selector' => 'a',
                'tags' => ['wcag2a'],
            ],
        ],
    ]));
    $compareScan = Scan::orderBy('id', 'desc')->first();

    $response = $this->getJson(route('lens-for-laravel.history.compare', [$baseScan->id, $compareScan->id]));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('base.id', $baseScan->id)
        ->assertJsonPath('compare.id', $compareScan->id);

    $data = $response->json();

    // link-name is new (in compare but not in base)
    expect($data['new'])->toHaveCount(1);
    expect($data['new'][0]['rule_id'])->toBe('link-name');

    // color-contrast is fixed (in base but not in compare)
    expect($data['fixed'])->toHaveCount(1);
    expect($data['fixed'][0]['rule_id'])->toBe('color-contrast');

    // image-alt is remaining (in both)
    expect($data['remaining'])->toHaveCount(1);
    expect($data['remaining'][0]['rule_id'])->toBe('image-alt');
});

test('compare returns 404 for missing scan', function () {
    $this->postJson(route('lens-for-laravel.history.store'), createScanPayload());
    $scan = Scan::first();

    $this->getJson(route('lens-for-laravel.history.compare', [$scan->id, 999]))
        ->assertStatus(404);
});
