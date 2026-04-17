<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// -------------------------------------------------------------------------
// Production server paths (Africa CDC):
//   Staff (CodeIgniter): /var/lib/ACDC_SYSTEMS/staff
//   APM (Laravel):       /var/lib/ACDC_SYSTEMS/staff/apm
//   PHP CLI:             /usr/bin/php
//
// Copy the lines between CUT lines into: crontab -e
//
// -----8<----- CUT BELOW -----8<-----
// Staff portal — single scheduler (edit times in Run.php → tick_schedule)
// * * * * * /usr/bin/php /var/lib/ACDC_SYSTEMS/staff/index.php jobs/run/tick >> /var/lib/ACDC_SYSTEMS/staff/application/logs/cron-tick.log 2>&1
//
// APM Laravel — queue worker (your existing command)
// * * * * * cd /var/lib/ACDC_SYSTEMS/staff/apm && /usr/bin/php artisan queue:work --quiet >> /dev/null 2>&1
//
// Optional: Biotime / heavy jobs master (only if you use it)
// * * * * * /usr/bin/php /var/lib/ACDC_SYSTEMS/staff/index.php jobs master >> /var/lib/ACDC_SYSTEMS/staff/application/logs/cron-jobs-master.log 2>&1
// -----8<----- CUT ABOVE -----8<-----
//
// Note: queue:work every minute starts a new worker each run; many teams use
// Supervisor/systemd for one long-lived worker instead. This is your requested shape.
// -------------------------------------------------------------------------
