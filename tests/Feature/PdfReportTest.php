<?php

test('PDF report includes the selected WCAG version and classifies WCAG 2.2 tags', function () {
    $html = view('lens-for-laravel::report', [
        'issues' => [[
            'id' => 'target-size',
            'impact' => 'serious',
            'description' => 'Targets must have sufficient size',
            'tags' => ['wcag22aa'],
        ]],
        'url' => 'https://example.com',
        'wcagVersion' => '2.2',
        'generatedAt' => now(),
    ])->render();

    expect($html)->toContain('WCAG 2.2')
        ->and($html)->toContain('<div class="stat-value">1</div>');
});

test('PDF report renders all labels in the selected locale', function () {
    app()->setLocale('pl');

    $html = view('lens-for-laravel::report', [
        'issues' => [[
            'id' => 'image-alt',
            'impact' => 'critical',
            'description' => 'Image needs alternative text',
            'htmlSnippet' => '<img src="logo.png">',
            'selector' => 'img.logo',
            'tags' => ['wcag2a'],
            'stateLabel' => 'Otwarta nawigacja',
            'fileName' => 'home.blade.php',
            'lineNumber' => 12,
            'helpUrl' => 'https://example.com/help',
        ]],
        'url' => 'https://example.com',
        'wcagVersion' => '2.2',
        'generatedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Raport z audytu dostępności')
        ->toContain('Wygenerowano:')
        ->toContain('Naruszenia (1)')
        ->toContain('Krytyczny')
        ->toContain('Fragment HTML')
        ->toContain('Lokalizacja w źródle')
        ->not->toContain('ACCESSIBILITY AUDIT REPORT')
        ->not->toContain('HTML Snippet');
});

test('POST /report/pdf requires issues array', function () {
    $this->postJson(route('lens-for-laravel.report.pdf'), ['url' => 'https://example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['issues']);
});

test('POST /report/pdf requires url', function () {
    $this->postJson(route('lens-for-laravel.report.pdf'), ['issues' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

test('POST /report/pdf returns 403 when environment not allowed', function () {
    $this->app['config']->set('lens-for-laravel.enabled_environments', ['local']);

    $this->postJson(route('lens-for-laravel.report.pdf'), [
        'issues' => [],
        'url' => 'https://example.com',
    ])->assertStatus(403);
});

test('POST /report/pdf validates interactive state labels', function () {
    $this->postJson(route('lens-for-laravel.report.pdf'), [
        'issues' => [
            [
                'id' => 'button-name',
                'impact' => 'critical',
                'description' => 'Buttons must have text',
                'stateLabel' => str_repeat('x', 101),
            ],
        ],
        'url' => 'https://example.com',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['issues.0.stateLabel']);
});

test('POST /report/pdf returns error json when browsershot fails', function () {
    // Without headless Chrome, Browsershot throws — the route catches Throwable
    $this->postJson(route('lens-for-laravel.report.pdf'), [
        'issues' => [
            [
                'id' => 'image-alt',
                'impact' => 'critical',
                'description' => 'Images must have alt text',
                'htmlSnippet' => '<img src="x.png">',
                'selector' => 'img',
                'tags' => ['wcag2a'],
            ],
        ],
        'url' => 'https://example.com',
    ])->assertStatus(500)
        ->assertJson(['status' => 'error']);
});
