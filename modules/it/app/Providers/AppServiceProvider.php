<?php

namespace App\Providers;

use App\Services\StaffPortalShareClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StaffPortalShareClient::class);
    }

    public function boot(): void
    {
        if ($url = config('app.url')) {
            \Illuminate\Support\Facades\URL::forceRootUrl(rtrim((string) $url, '/'));
        }
    }
}
