# Laravel Jobs Cron Port — Implementation Plan

> **For Claude:** execute tasks below; mark checkboxes as you go.

**Goal:** Port CI `jobs` cron into staff-portal `Modules/Jobs` with email templates and Laravel Schedule.

**Architecture:** Services + Artisan commands + Blade emails + `email_notifications` + `PortalMailer`.

## File map

- `Modules/Jobs/config/schedule.php` — times
- `Modules/Jobs/app/Services/EmailNotificationService.php`
- `Modules/Jobs/app/Services/SendQueuedMailService.php`
- `Modules/Jobs/app/Services/ContractReminderService.php`
- `Modules/Jobs/app/Services/PerformanceReminderService.php`
- `Modules/Jobs/app/Services/StaffBirthdayService.php`
- `Modules/Jobs/app/Services/StaffProfileReminderService.php`
- `Modules/Jobs/app/Services/ManageAccountsJobService.php`
- `Modules/Jobs/app/Services/PruneUserLogsService.php`
- `Modules/Jobs/app/Console/*.php` — commands
- `Modules/Jobs/resources/views/emails/*.blade.php`
- `Modules/Jobs/app/Providers/JobsServiceProvider.php` — register commands + schedule
- `routes/console.php` — keep workplan fallback or defer to module

## Tasks

1. [x] Config + email queue/send services + copy templates
2. [x] Contract due/expired + audit commands
3. [x] Performance staff/supervisor/approval reminders
4. [x] Birthday, profile, manage-accounts, prune logs
5. [x] Wire Schedule; document CI cutover
6. [x] Smoke: `php artisan list | grep jobs` + template render

## Cutover

Replace CI crontab `php index.php jobs/run/tick` with:

```bash
* * * * * cd /path/to/staff-portal/backend && php artisan schedule:run >> /dev/null 2>&1
```
