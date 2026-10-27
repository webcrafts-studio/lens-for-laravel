<?php

namespace LensForLaravel\LensForLaravel\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LensForLaravel\LensForLaravel\DTOs\AuthenticatedScanContext;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class AuthenticatedScanResolver
{
    /** Run an MCP operation with a disposable session and browser-ready cookies. */
    public function runIsolatedAsUser(?int $userId, callable $callback): mixed
    {
        if ($userId === null) {
            return $callback(null);
        }
        $session = session();
        $originalId = $session->getId();
        $originalData = $session->all();
        $session->flush();
        $session->setId(Str::random(40));
        try {
            return $this->runAsUser($userId, function (AuthenticatedScanContext $auth) use ($callback, $session) {
                $issuedId = $session->getId();
                $handler = $session->getHandler();
                $active = true;
                register_shutdown_function(static function () use (&$active, $handler, $issuedId) {
                    if ($active) {
                        $handler->destroy($issuedId);
                    }
                });
                try {
                    $name = (string) config('session.cookie');
                    $middlewareClass = EncryptCookies::class;
                    foreach (app('router')->getMiddlewareGroups()['web'] ?? [] as $class) {
                        if (is_string($class) && is_a($class, EncryptCookies::class, true)) {
                            $middlewareClass = $class;
                            break;
                        }
                    }
                    $response = response('');
                    $response->headers->setCookie(new Cookie($name, $auth->cookies[$name]));
                    $response = app($middlewareClass)->handle(Request::create('/'), fn () => $response);
                    $cookies = [];
                    foreach ($response->headers->getCookies() as $cookie) {
                        $cookies[$cookie->getName()] = $cookie->getValue();
                    }

                    return $callback(new AuthenticatedScanContext($cookies));
                } finally {
                    $handler->destroy($issuedId);
                    $active = false;
                }
            });
        } finally {
            if ($session->getId() !== $originalId) {
                $session->getHandler()->destroy($session->getId());
            }
            $session->setId($originalId);
            $session->flush();
            $session->replace($originalData);
        }
    }

    public function available(): bool
    {
        return (bool) config('lens-for-laravel.auth_enabled', false);
    }

    /**
     * Resolve an optional "scan as user" id into browser cookies, run the
     * callback while impersonating that user, then restore the previous auth state.
     *
     * Only the numeric user id comes from the client. Cookies are issued
     * server-side via a guard login, so raw session cookies, tokens, and
     * passwords are never accepted, logged, or stored.
     *
     * @template T
     *
     * @param  callable(AuthenticatedScanContext|null): T  $callback
     * @return T
     *
     * @throws InvalidArgumentException
     */
    public function runAsUser(?int $userId, callable $callback): mixed
    {
        if ($userId === null) {
            return $callback(null);
        }

        if (! $this->available()) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.errors.auth_disabled'));
        }

        $allowed = config('lens-for-laravel.auth_allowed_user_ids', []);
        if (is_array($allowed) && $allowed !== [] && ! in_array($userId, array_map('intval', $allowed), true)) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.errors.auth_not_allowed'));
        }

        // The array session driver cannot share sessions with the separate
        // Chromium process, so authenticated scans would silently hit the login wall.
        if ((string) config('session.driver') === 'array') {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.errors.auth_session_driver'));
        }

        $guardName = (string) config('lens-for-laravel.auth_guard', 'web');

        try {
            $guard = Auth::guard($guardName);
            $user = $guard->getProvider()->retrieveById($userId);
        } catch (Throwable) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.errors.auth_unavailable'));
        }

        if (! $user instanceof Authenticatable) {
            throw new InvalidArgumentException(__('lens-for-laravel::messages.errors.auth_user_not_found'));
        }

        if (! session()->isStarted()) {
            session()->start();
        }

        $originalId = $guard->id();

        $guard->login($user);

        // Persist the session now so the Chromium request (a separate process)
        // can read it. This is a no-op for the normal end-of-request save.
        session()->save();

        $context = new AuthenticatedScanContext([
            (string) config('session.cookie') => (string) session()->getId(),
        ]);

        try {
            return $callback($context);
        } finally {
            if ($originalId === null) {
                $guard->logout();
            } else {
                $guard->loginUsingId($originalId);
            }
        }
    }
}
