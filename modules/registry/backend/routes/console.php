<?php

use App\Services\LicenseExpiryAlertService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('it:license-expiry-alerts', function (LicenseExpiryAlertService $alerts) {
    $result = $alerts->sendDue();
    if (! $result['enabled']) {
        $this->warn('License expiry reminders are turned off.');

        return;
    }
    $this->info('Sent '.$result['sent'].' reminder(s). Skipped '.$result['skipped'].'.');
    foreach ($result['errors'] as $error) {
        $this->error($error);
    }
})->purpose('Email responsible people about licenses that are expiring or expired');

// Finance Azure SAP dump poll is registered in bootstrap/app.php (every 4 hours).
// Cron on the Finance API host:
//   * * * * * cd /path/to/modules/finance/backend && php artisan schedule:run
// Manual run: php artisan finance:fetch-azure-sap-dumps
