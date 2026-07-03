<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix for the Lens For Laravel dashboard routes.
    | Default: 'lens-for-laravel'
    |
    */
    'route_prefix' => 'lens-for-laravel',

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware that should be applied to the Lens For Laravel routes.
    | You might want to add 'auth' to restrict access in production.
    |
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Enabled Environments
    |--------------------------------------------------------------------------
    |
    | The environments where Lens For Laravel is allowed to run.
    | Usually, you only want this enabled in local development.
    |
    */
    'enabled_environments' => [
        'local',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    |
    | The default language for Lens UI. Users can override this in the dashboard
    | with the built-in switcher; the override is stored in their session.
    |
    */
    'locale' => env('LENS_FOR_LARAVEL_LOCALE', app()->getLocale()),

    'fallback_locale' => env('LENS_FOR_LARAVEL_FALLBACK_LOCALE', 'en'),

    'supported_locales' => [
        'en' => 'English',
        'pl' => 'Polski',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor / IDE Integration
    |--------------------------------------------------------------------------
    |
    | When a source location is found, clicking it in the dashboard will open
    | your editor at the exact file and line. Set to 'none' to disable.
    |
    | Supported values: 'vscode', 'cursor', 'phpstorm', 'sublime', 'none'
    |
    */
    'editor' => env('LENS_FOR_LARAVEL_EDITOR', 'vscode'),

    /*
    |--------------------------------------------------------------------------
    | Crawl Max Pages
    |--------------------------------------------------------------------------
    |
    | Maximum number of pages to discover and scan in WHOLE_WEBSITE mode.
    | Increase this if your site has many pages. The crawl phase uses a plain
    | HTTP client (not headless Chrome), so higher limits are fast and safe.
    |
    */
    'crawl_max_pages' => env('LENS_FOR_LARAVEL_CRAWL_MAX_PAGES', 50),

    /*
    |--------------------------------------------------------------------------
    | Render JavaScript During Crawling
    |--------------------------------------------------------------------------
    |
    | Enable this for SPA/Inertia applications where internal links are rendered
    | by React or Vue after the initial HTML response. The HTTP crawler remains
    | the default because it is much faster for traditional Laravel pages.
    |
    */
    'crawler_render_javascript' => env('LENS_FOR_LARAVEL_CRAWLER_RENDER_JAVASCRIPT', false),

    /*
    |--------------------------------------------------------------------------
    | Scan Wait Time
    |--------------------------------------------------------------------------
    |
    | Extra milliseconds to wait after network idle before axe-core runs. This is
    | useful for Livewire, Inertia, React, or Vue screens with delayed hydration.
    |
    */
    'scan_wait_ms' => env('LENS_FOR_LARAVEL_SCAN_WAIT_MS', 0),

    /*
    |--------------------------------------------------------------------------
    | Accessibility Baseline Path
    |--------------------------------------------------------------------------
    |
    | The default JSON file used by the CLI baseline quality gate. Existing
    | applications with a published config can omit this key and Lens will use
    | the same storage path fallback.
    |
    */
    'baseline_path' => env('LENS_FOR_LARAVEL_BASELINE_PATH', storage_path('app/lens-for-laravel/baseline.json')),

    /*
    |--------------------------------------------------------------------------
    | Ignore HTTPS Errors
    |--------------------------------------------------------------------------
    |
    | If true, the scanner will ignore HTTPS errors such as self-signed certificates.
    | This is useful for local development environments like DDEV or Laravel Valet.
    |
    */
    'ignore_https_errors' => env('LENS_FOR_LARAVEL_IGNORE_HTTPS_ERRORS', false),

    /*
    |--------------------------------------------------------------------------
    | AI Fix
    |--------------------------------------------------------------------------
    |
    | AI Fix is optional and requires PHP 8.3+, Laravel 12+, and the optional
    | laravel/ai Composer package. Core scanning remains available when this is
    | disabled or unsupported by the host application's runtime.
    |
    | Supported values: 'gemini', 'openai', 'anthropic'
    |
    */
    'ai_enabled' => env('LENS_FOR_LARAVEL_AI_ENABLED', true),

    'ai_provider' => env('LENS_FOR_LARAVEL_AI_PROVIDER', 'gemini'),

];
