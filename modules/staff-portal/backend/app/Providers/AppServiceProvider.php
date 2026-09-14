<?php

namespace App\Providers;

use App\Mail\Transport\ExchangeGraphTransport;
use App\Services\ExchangeGraphMailClient;
use App\Support\CbpAsset;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Blade::directive('cbpAsset', function (string $expression): string {
            return "<?php echo \\App\\Support\\CbpAsset::url({$expression}); ?>";
        });

        Mail::extend('exchange', function () {
            return new ExchangeGraphTransport(
                $this->app->make(ExchangeGraphMailClient::class)
            );
        });
    }
}
