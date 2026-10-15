# Jobs cron cutover (CI → Laravel)

## What moved

Staff Portal now owns cron via `Modules/Jobs` Artisan commands + Laravel Schedule (same defaults as CI `jobs/run/tick`).

Email templates: `staff-portal/backend/Modules/Jobs/resources/views/emails/`

Config: `staff-portal/backend/Modules/Jobs/config/schedule.php`

## Crontab

**Disable** CI:

```bash
# * * * * * /usr/bin/php /path/to/staff/index.php jobs/run/tick
```

**Enable** Laravel:

```bash
* * * * * cd /path/to/staff/staff-portal/backend && php artisan schedule:run >> /dev/null 2>&1
```

## Manual commands

```bash
php artisan jobs:send-instant-mails
php artisan jobs:send-mails
php artisan jobs:performance-notifications
php artisan jobs:performance-approval-reminder
php artisan jobs:mark-due-contracts
php artisan jobs:audit-extended-contracts
php artisan jobs:staff-birthday
php artisan jobs:staff-profile-completion-reminder
php artisan jobs:manage-accounts
php artisan jobs:prune-user-logs-get-access
php artisan schedule:list
```

## Env overrides

- `JOBS_MAIL_LOGO_URL`
- `JOBS_PORTAL_BASE_URL` (deep links in emails)
- `JOBS_SYSTEM_EMAIL` (audit BCC; defaults to `system@africacdc.org` via `MAIL_CC_ADDRESS` — never `registry@`)
- `JOBS_CONTRACTS_COPIED_EMAILS`
