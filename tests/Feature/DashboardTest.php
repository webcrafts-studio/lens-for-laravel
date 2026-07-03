<?php

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
