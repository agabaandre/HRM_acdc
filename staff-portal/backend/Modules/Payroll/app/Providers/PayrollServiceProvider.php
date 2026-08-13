<?php

namespace Modules\Payroll\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class PayrollServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Payroll';

    protected string $nameLower = 'payroll';

    protected array $commands = [];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
