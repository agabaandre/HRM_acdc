<?php

namespace Modules\Workplan\Providers;

use Modules\Workplan\Console\SyncPraWorkplanCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class WorkplanServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Workplan';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'workplan';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SyncPraWorkplanCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
