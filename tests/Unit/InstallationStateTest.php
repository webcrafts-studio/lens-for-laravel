<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LensForLaravel\LensForLaravel\Support\InstallationState;

beforeEach(function () {
    $this->stateStorage = sys_get_temp_dir().'/lens-install-state-'.Str::uuid();
    $this->previousStateStorage = storage_path();
    app()->useStoragePath($this->stateStorage);
});

afterEach(function () {
    app()->useStoragePath($this->previousStateStorage);
    File::deleteDirectory($this->stateStorage);
});

test('installation state persists across instances in the custom application storage', function () {
    $state = new InstallationState;
    expect($state->markInstalled())->toBeTrue()
        ->and((new InstallationState)->markInstalled())->toBeFalse()
        ->and($state->path())->toStartWith($this->stateStorage.'/app/lens-for-laravel/')
        ->and(fileperms($state->path()) & 0777)->toBe(0600);
});

test('installation state cannot overwrite a symlink target or foreign marker', function () {
    $state = new InstallationState;
    File::ensureDirectoryExists(dirname($state->path()));
    $target = $this->stateStorage.'/keep';
    file_put_contents($target, 'private application data');
    symlink($target, $state->path());
    expect(fn () => $state->markInstalled())->toThrow(RuntimeException::class)
        ->and(file_get_contents($target))->toBe('private application data');
    unlink($state->path());
    file_put_contents($state->path(), 'another project');
    expect(fn () => $state->markInstalled())->toThrow(RuntimeException::class)
        ->and(file_get_contents($state->path()))->toBe('another project');
});
