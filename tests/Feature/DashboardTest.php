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

test('dashboard renders interactive state scan controls', function () {
    $response = $this->get(route('lens-for-laravel.dashboard'));

    $response->assertStatus(200)
        ->assertSee('STATES')
        ->assertSee('INTERACTIVE_STATE_RECORDER')
        ->assertSee('Record')
        ->assertSee('openStateRecorder', false)
        ->assertSee('INTERACTION_SCRIPT')
        ->assertSee('scanInteractiveStates', false)
        ->assertSee(route('lens-for-laravel.scan.states'), false);
});

test('state recorder renders for same origin target urls', function () {
    $response = $this->get(route('lens-for-laravel.states.recorder', [
        'target' => url('/states'),
    ]));

    $response->assertStatus(200)
        ->assertSee('Lens State Recorder')
        ->assertSee('Send Script');
});
