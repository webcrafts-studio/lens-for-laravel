<?php

use LensForLaravel\LensForLaravel\Services\InteractionScriptParser;

test('parses colon based interaction scripts', function () {
    $states = (new InteractionScriptParser)->parse(<<<'SCRIPT'
        # Navigation state
        state: Navigation open
        click: [data-menu-button]
        wait: 300

        state: Form validation
        type: input[name="email"] => invalid@example.test
        select: select[name="plan"] | pro
        check: input[name="terms"]
        uncheck: input[name="newsletter"]
        click: button[type="submit"]
        SCRIPT);

    expect($states)->toHaveCount(2)
        ->and($states[0]['label'])->toBe('Navigation open')
        ->and($states[0]['actions'])->toHaveCount(2)
        ->and($states[1]['actions'])->toHaveCount(5)
        ->and($states[1]['actions'][0])->toMatchArray([
            'type' => 'type',
            'selector' => 'input[name="email"]',
            'value' => 'invalid@example.test',
        ])
        ->and($states[1]['actions'][1])->toMatchArray([
            'type' => 'select',
            'selector' => 'select[name="plan"]',
            'value' => 'pro',
        ]);
});

test('parses space based shorthand actions and quoted values', function () {
    $states = (new InteractionScriptParser)->parse(<<<'SCRIPT'
        state "Modal open"
        click "[data-open-modal]"
        type input[name=email] "hello@example.test"
        SCRIPT);

    expect($states)->toHaveCount(1)
        ->and($states[0]['label'])->toBe('Modal open')
        ->and($states[0]['actions'][0]['selector'])->toBe('[data-open-modal]')
        ->and($states[0]['actions'][1]['value'])->toBe('hello@example.test');
});

test('allows a state without actions to scan initial rendered state', function () {
    $states = (new InteractionScriptParser)->parse('state: Initial page');

    expect($states)->toHaveCount(1)
        ->and($states[0]['actions'])->toBe([]);
});

test('rejects actions before a state', function () {
    (new InteractionScriptParser)->parse('click: button');
})->throws(InvalidArgumentException::class, 'add a state before defining actions');

test('rejects unsupported actions', function () {
    (new InteractionScriptParser)->parse(<<<'SCRIPT'
        state: Menu
        hover: button
        SCRIPT);
})->throws(InvalidArgumentException::class, "unsupported action 'hover'");

test('rejects empty scripts', function () {
    (new InteractionScriptParser)->parse("# comment\n\n");
})->throws(InvalidArgumentException::class, 'Add at least one state');

test('rejects invalid wait values', function () {
    (new InteractionScriptParser)->parse(<<<'SCRIPT'
        state: Slow modal
        wait: 6000
        SCRIPT);
})->throws(InvalidArgumentException::class, 'wait must be between');

test('rejects selector value actions without a delimiter', function () {
    (new InteractionScriptParser)->parse(<<<'SCRIPT'
        state: Login
        type: input[name=email]
        SCRIPT);
})->throws(InvalidArgumentException::class, "use 'selector => value'");

test('rejects too many states', function () {
    $script = collect(range(1, 11))
        ->map(fn ($i) => "state: State {$i}")
        ->implode("\n");

    (new InteractionScriptParser)->parse($script);
})->throws(InvalidArgumentException::class, 'up to 10 states');

test('rejects too many actions', function () {
    $script = "state: Heavy flow\n".collect(range(1, 31))
        ->map(fn () => 'click: button')
        ->implode("\n");

    (new InteractionScriptParser)->parse($script);
})->throws(InvalidArgumentException::class, 'up to 30 actions');

test('translates interaction parser errors', function () {
    app()->setLocale('pl');

    (new InteractionScriptParser)->parse('click: button');
})->throws(InvalidArgumentException::class, 'Wiersz 1: dodaj stan przed zdefiniowaniem akcji');
