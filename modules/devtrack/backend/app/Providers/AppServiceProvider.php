<?php

namespace App\Providers;

use App\Mail\Transport\ExchangeGraphTransport;
use App\Services\ExchangeGraphMailClient;
use App\Support\RuntimeStoreFallback;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ExchangeGraphMailClient::class);
    }

    public function boot(): void
    {
        RuntimeStoreFallback::apply();

        Mail::extend('exchange', function () {
            return new ExchangeGraphTransport(
                $this->app->make(ExchangeGraphMailClient::class),
            );
        });

        Password::defaults(function () {
            return Password::min(10)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();
        });

        // API throttling stored in Redis when CACHE_STORE=redis
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        if (! $this->app->runningInConsole()) {
            $httpRequest = request();
            URL::forceRootUrl($httpRequest->getSchemeAndHttpHost().$httpRequest->getBasePath());
        }
    }
}
