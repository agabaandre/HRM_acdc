# Laravel Jobs Module (CI Cron Port) — Design

**Date:** 2026-08-14  
**Status:** Approved  
**Decision:** Laravel-only cron (`schedule:run`). Disable CI `jobs/run/tick` after cutover.

## Goal

Port `application/modules/jobs` scheduled work into `Modules/Jobs`: Artisan commands, Blade email templates, `email_notifications` queue, `PortalMailer` send.

## Scope

| Job | Schedule |
|-----|----------|
| `jobs:send-instant-mails` | every minute |
| `jobs:send-mails` | every 15 minutes |
| `jobs:performance-notifications` | daily 07:00 |
| `jobs:performance-approval-reminder` | daily 10:00 |
| `jobs:mark-due-contracts` | daily 23:00 |
| `jobs:audit-extended-contracts` | daily 23:05 |
| `jobs:staff-birthday` | daily 03:00 (+ hourly 03–09) |
| `jobs:staff-profile-completion-reminder` | daily 08:30 |
| `jobs:manage-accounts` | hourly :00 |
| `jobs:prune-user-logs-get-access` | Tue 00:00 |
| `workplan:sync-pra` | already daily 00:05 |

## Out of scope

- Settings UI for schedule editing (config file only)
- CI schema one-off migration CLI methods
- Activity approval emails (APM) unless already queued elsewhere

## Architecture

- `EmailNotificationService` — queue/dedupe via `email_notifications.entry_id`
- `SendQueuedMailService` — lock/send via `PortalMailer`
- Domain services: contracts, performance reminders, birthday, accounts, profile
- Templates under `Modules/Jobs/resources/views/emails/`
- Config: `Modules/Jobs/config/schedule.php`
