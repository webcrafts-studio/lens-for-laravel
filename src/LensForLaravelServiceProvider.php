<?php

namespace LensForLaravel\LensForLaravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LensForLaravel\LensForLaravel\Console\Commands\LensAuditCommand;

class LensForLaravelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lens-for-laravel.php', 'lens-for-laravel');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerMigrations();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('lens-for-laravel.route_prefix', 'lens-for-laravel'),
            'middleware' => config('lens-for-laravel.middleware', ['web']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register the package views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'lens-for-laravel');
    }

    /**
     * Register the package database migrations.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Register the package's Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LensAuditCommand::class,
            ]);
        }
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/lens-for-laravel.php' => config_path('lens-for-laravel.php'),
            ], 'lens-for-laravel-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/lens-for-laravel'),
            ], 'lens-for-laravel-views');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'lens-for-laravel-migrations');
        }
    }
}
