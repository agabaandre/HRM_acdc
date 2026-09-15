<?php

namespace App\Providers;

use App\Mail\Transport\ExchangeGraphTransport;
use App\Mail\Transport\HttpNotificationsTransport;
use App\Services\ExchangeGraphMailClient;
use App\Services\HttpNotificationsMailClient;
use App\Support\CbpAsset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HttpNotificationsMailClient::class);
    }

    public function boot(): void
    {
        // Subdirectory deploys (/staff/backend, /cbp/backend, …): Apache SCRIPT_NAME
        // remaps can leave Request::root() at the host only, so route() redirects
        // become https://host/auth/spa-bridge (404). Always prefer APP_URL.
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
            $scheme = parse_url($appUrl, PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '') {
                URL::forceScheme($scheme);
            }
        }

        Blade::directive('cbpAsset', function (string $expression): string {
            return "<?php echo \\App\\Support\\CbpAsset::url({$expression}); ?>";
        });

        Mail::extend('exchange', function () {
            return new ExchangeGraphTransport(
                $this->app->make(ExchangeGraphMailClient::class)
            );
        });

        Mail::extend('http', function () {
            return new HttpNotificationsTransport(
                $this->app->make(HttpNotificationsMailClient::class)
            );
        });
    }
}
