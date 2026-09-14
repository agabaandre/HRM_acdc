<?php

namespace App\Providers;

use App\Mail\Transport\ExchangeGraphTransport;
use App\Services\ExchangeGraphMailClient;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExchangeGraphMailClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('exchange', function () {
            return new ExchangeGraphTransport(
                $this->app->make(ExchangeGraphMailClient::class),
            );
        });
    }
}
