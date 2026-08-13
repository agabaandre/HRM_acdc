<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled jobs live in Modules/Jobs (and Workplan). Prefer:
//   * * * * * cd /path/to/staff-portal/backend && php artisan schedule:run
// Disable CI `php index.php jobs/run/tick` after cutover to avoid duplicate emails.
