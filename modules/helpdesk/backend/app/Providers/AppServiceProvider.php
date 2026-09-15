<?php

namespace App\Providers;

use App\Mail\Transport\ExchangeGraphTransport;
use App\Mail\Transport\HttpNotificationsTransport;
use App\Services\ExchangeGraphMailClient;
use App\Services\HttpNotificationsMailClient;
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
        $this->app->singleton(HttpNotificationsMailClient::class);
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

        Mail::extend('http', function () {
            return new HttpNotificationsTransport(
                $this->app->make(HttpNotificationsMailClient::class),
            );
        });
    }
}
