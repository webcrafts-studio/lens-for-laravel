<?php

namespace LensForLaravel\LensForLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLensLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('lens-for-laravel.supported_locales', ['en' => 'English']));
        $fallback = (string) config('lens-for-laravel.fallback_locale', 'en');
        $configured = (string) config('lens-for-laravel.locale', app()->getLocale());
        $sessionLocale = $request->hasSession() ? $request->session()->get('lens-for-laravel.locale') : null;
        $requested = $request->query('lens_locale');

        $locale = $requested ?: $sessionLocale ?: $configured ?: $fallback;

        if (! in_array($locale, $supported, true)) {
            $locale = in_array($fallback, $supported, true) ? $fallback : 'en';
        }

        if ($requested && $request->hasSession()) {
            $request->session()->put('lens-for-laravel.locale', $locale);
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
