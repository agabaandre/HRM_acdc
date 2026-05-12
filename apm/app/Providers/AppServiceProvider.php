<?php

namespace App\Providers;

use App\Models\WeeklyBriefingReport;
use App\Services\CbpPlatformMenuService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
      
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap for pagination styling
        Paginator::useBootstrap();

        Route::bind('report', function (string $value) {
            return WeeklyBriefingReport::query()->findOrFail($value);
        });

        View::composer('layouts.partials.nav', function ($view) {
            $view->with('cbpPlatformNavItems', CbpPlatformMenuService::primaryNavItems());
        });
    }
}