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
        ->assertSee('--lens-accent: #991b1b', false)
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
