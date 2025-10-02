<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use AgabaandreOffice365\ExchangeEmailService\ExchangeOAuth;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register the exchange mail transport
        Mail::extend('exchange', function (array $config) {
            $exchangeConfig = config('exchange-email');
            return new ExchangeOAuth(
                $exchangeConfig['tenant_id'],
                $exchangeConfig['client_id'],
                $exchangeConfig['client_secret'],
                $exchangeConfig['redirect_uri'],
                $exchangeConfig['scope'],
                $exchangeConfig['auth_method']
            );
        });
    }
}
