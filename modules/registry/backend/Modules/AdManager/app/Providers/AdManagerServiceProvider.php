<?php

namespace Modules\AdManager\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class AdManagerServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'AdManager';

    protected string $nameLower = 'admanager';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
