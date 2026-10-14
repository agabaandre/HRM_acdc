<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Finance Azure SAP dump poll is registered in bootstrap/app.php (every 4 hours).
// Cron on the Finance API host:
//   * * * * * cd /path/to/modules/finance/backend && php artisan schedule:run
// Manual run: php artisan finance:fetch-azure-sap-dumps
