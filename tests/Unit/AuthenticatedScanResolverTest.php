<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;
use LensForLaravel\LensForLaravel\DTOs\AuthenticatedScanContext;
use LensForLaravel\LensForLaravel\Services\AuthenticatedScanResolver;

class LensFakeUserProvider implements UserProvider
{
    /**
     * @param  array<int, Authenticatable>  $users
     */
    public function __construct(private array $users) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->users[(int) $identifier] ?? null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void {}

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
}

beforeEach(function () {
    Auth::provider('lens-test-users', fn () => new LensFakeUserProvider([
        5 => new GenericUser(['id' => 5, 'name' => 'Original', 'remember_token' => null]),
        7 => new GenericUser(['id' => 7, 'name' => 'Target', 'remember_token' => null]),
    ]));
    config()->set('auth.providers.users.driver', 'lens-test-users');
    config()->set('lens-for-laravel.auth_enabled', true);
    config()->set('lens-for-laravel.auth_guard', 'web');
    config()->set('lens-for-laravel.auth_allowed_user_ids', []);

    $dir = sys_get_temp_dir().'/lens-auth-sessions-'.uniqid();
    mkdir($dir, 0777, true);
    $this->lensSessionDir = $dir;
    config()->set('session.driver', 'file');
    config()->set('session.files', $dir);
});

afterEach(function () {
    foreach (glob($this->lensSessionDir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->lensSessionDir);
});

test('availability follows the auth_enabled setting', function () {
    expect(app(AuthenticatedScanResolver::class)->available())->toBeTrue();

    config()->set('lens-for-laravel.auth_enabled', false);

    expect(app(AuthenticatedScanResolver::class)->available())->toBeFalse();
});

test('runAsUser passes null through when no user id is given', function () {
    config()->set('lens-for-laravel.auth_enabled', false);

    $result = app(AuthenticatedScanResolver::class)->runAsUser(null, fn ($auth) => [
        'auth' => $auth,
        'guest' => Auth::guard('web')->guest(),
    ]);

    expect($result['auth'])->toBeNull()
        ->and($result['guest'])->toBeTrue();
});

test('runAsUser rejects user ids when authenticated scans are disabled', function () {
    config()->set('lens-for-laravel.auth_enabled', false);

    app(AuthenticatedScanResolver::class)->runAsUser(7, fn ($auth) => null);
})->throws(InvalidArgumentException::class);

test('runAsUser enforces the allowed user id list', function () {
    config()->set('lens-for-laravel.auth_allowed_user_ids', [7]);

    expect(fn () => app(AuthenticatedScanResolver::class)->runAsUser(9, fn ($auth) => null))
        ->toThrow(InvalidArgumentException::class);

    expect(Auth::guard('web')->guest())->toBeTrue();
});

test('runAsUser rejects unknown user ids', function () {
    expect(fn () => app(AuthenticatedScanResolver::class)->runAsUser(404, fn ($auth) => null))
        ->toThrow(InvalidArgumentException::class);

    expect(Auth::guard('web')->guest())->toBeTrue();
});

test('runAsUser requires a persistent session driver', function () {
    config()->set('session.driver', 'array');

    expect(fn () => app(AuthenticatedScanResolver::class)->runAsUser(7, fn ($auth) => null))
        ->toThrow(InvalidArgumentException::class);
});

test('runAsUser reports a misconfigured guard safely', function () {
    config()->set('lens-for-laravel.auth_guard', 'missing-guard');

    expect(fn () => app(AuthenticatedScanResolver::class)->runAsUser(7, fn ($auth) => null))
        ->toThrow(InvalidArgumentException::class);
});

test('runAsUser issues a session cookie and restores the guest state', function () {
    $seen = null;

    $result = app(AuthenticatedScanResolver::class)->runAsUser(7, function (?AuthenticatedScanContext $auth) use (&$seen) {
        $seen = [
            'userId' => Auth::guard('web')->id(),
            'cookies' => $auth?->cookies,
        ];

        return 'scanned';
    });

    $cookieName = (string) config('session.cookie');

    expect($result)->toBe('scanned')
        ->and($seen['userId'])->toBe(7)
        ->and($seen['cookies'])->toHaveKey($cookieName)
        ->and($seen['cookies'][$cookieName])->toBeString()->not->toBe('')
        ->and(Auth::guard('web')->guest())->toBeTrue()
        ->and(glob($this->lensSessionDir.'/*'))->not->toBeEmpty();
});

test('runAsUser restores the previously authenticated user', function () {
    session()->start();
    Auth::guard('web')->loginUsingId(5);
    expect(Auth::guard('web')->id())->toBe(5);

    app(AuthenticatedScanResolver::class)->runAsUser(7, function () {
        expect(Auth::guard('web')->id())->toBe(7);
    });

    expect(Auth::guard('web')->id())->toBe(5);
});

test('runAsUser restores auth state when the scan fails', function () {
    expect(fn () => app(AuthenticatedScanResolver::class)->runAsUser(7, function () {
        throw new RuntimeException('scan exploded');
    }))->toThrow(RuntimeException::class, 'scan exploded');

    expect(Auth::guard('web')->guest())->toBeTrue();
});
