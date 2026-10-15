<?php

namespace Modules\Workplan\Providers;

use Illuminate\Routing\Events\Routing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Modules\Workplan\Console\SyncPraWorkplanCommand;
use Modules\Workplan\Http\Controllers\Api\V1\PraWorkplanSettingsController;
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

    public function boot(): void
    {
        parent::boot();

        Event::listen(Routing::class, function (): void {
            $this->registerPraSettingsRoutesIfMissing();
        });
    }

    /**
     * Stale compiled route caches (often not writable by deploy user) omit new URIs.
     * Re-attach GET/PUT settings/workplan-pra after compiled routes load.
     */
    protected function registerPraSettingsRoutesIfMissing(): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        try {
            $matched = app('router')->getRoutes()->match(
                \Illuminate\Http\Request::create('/api/v1/settings/workplan-pra', 'GET')
            );
            if ($matched->uri() === 'api/v1/settings/workplan-pra') {
                $registered = true;

                return;
            }
        } catch (\Throwable) {
            // Compiled cache does not include this URI yet.
        }

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1')
            ->group(function (): void {
                Route::get('settings/workplan-pra', [PraWorkplanSettingsController::class, 'show']);
                Route::put('settings/workplan-pra', [PraWorkplanSettingsController::class, 'update']);
            });
        $registered = true;
    }
}
