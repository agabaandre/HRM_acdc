<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use iamfarhad\LaravelAuditLog\Contracts\CauserResolverInterface;
use App\Services\CustomCauserResolver;

class AuditLogServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the custom causer resolver
        $this->app->bind(CauserResolverInterface::class, CustomCauserResolver::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
