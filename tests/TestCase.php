<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use LensForLaravel\LensForLaravel\LensForLaravelServiceProvider;
use LensForLaravel\LensForLaravel\Services\AiFixAvailability;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LensForLaravelServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('lens-for-laravel.enabled_environments', ['testing']);
        $app['config']->set('lens-for-laravel.route_prefix', 'lens-for-laravel');
        $app['config']->set('lens-for-laravel.middleware', ['web']);
        $app['config']->set('lens-for-laravel.crawl_max_pages', 5);
        $app['config']->set('lens-for-laravel.editor', 'vscode');
        $app['config']->set('lens-for-laravel.ai_enabled', true);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');

        // The Laravel AI SDK is an optional consumer dependency. Tests exercise
        // the supported-runtime routes without requiring it in this package's
        // development dependency graph.
        $app->singleton(AiFixAvailability::class, fn () => new class extends AiFixAvailability
        {
            protected function sdkInstalled(): bool
            {
                return true;
            }
        });

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF for all POST route tests
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
