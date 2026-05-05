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
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | The AI provider used for generating code fixes.
    |
    | Supported values: 'gemini', 'openai', 'anthropic'
    |
    */
    'ai_provider' => env('LENS_FOR_LARAVEL_AI_PROVIDER', 'gemini'),

];
