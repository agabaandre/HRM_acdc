<?php

namespace Modules\Settings\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Settings';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();

        // Local Apache keeps bootstrap/cache/routes-v7.php (owned by _www).
        // Laravel then skips module map() and new URIs never appear until that
        // file is rebuilt. Re-register language routes after the cache loads.
        $this->app->booted(function () {
            $this->app->booted(function () {
                $this->ensurePortalLanguageApiRoutes();
            });
        });
    }

    protected function ensurePortalLanguageApiRoutes(): void
    {
        $uris = [];
        foreach (Route::getRoutes() as $route) {
            $uris[$route->uri()] = true;
        }

        $files = [];
        if (! isset($uris['api/v1/languages'])) {
            $files[] = '/routes/languages.php';
        }
        if (! isset($uris['api/v1/settings/ai-providers'])) {
            $files[] = '/routes/ai.php';
        }
        if ($files === []) {
            return;
        }

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1')
            ->group(function () use ($files): void {
                foreach ($files as $file) {
                    require module_path($this->name, $file);
                }
            });
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
