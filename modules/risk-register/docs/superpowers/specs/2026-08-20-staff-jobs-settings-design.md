# Staff jobs settings (SPA) — design

**Date:** 2026-08-20  
**Status:** Approved (pending implementation)  
**Related:** CI3 `settings/staff_jobs`, Laravel `Modules/Jobs`, `docs/superpowers/specs/2026-08-14-laravel-jobs-cron-design.md`

## Problem

Operators control staff background jobs from CI3 at `/staff/settings/staff_jobs`. Staff-portal already runs the same jobs via Laravel’s scheduler (`Modules/Jobs`), but:

1. There is no SPA UI to enable/disable jobs or change times.
2. Profile completion reminder emails are **on by default** in schedule defaults; product wants them **off by default**.
3. CI3 and Laravel must not diverge on “is this job enabled?” during the SPA migration.

## Goals

- Full port of CI3 Staff jobs into staff-portal: **cron schedule** (toggles + times) and **Run now**.
- Single shared schedule file used by CI3 `jobs/run/tick` and Laravel `JobsServiceProvider`.
- Default for `staff_profile_completion_reminder` is **disabled** (`false`).
- Settings permission **15** (same as other settings hub cards).

## Non-goals

- Changing crontab on the host.
- Rewriting job business logic (mail content, eligibility, etc.).
- Forcing CI3 → SPA redirect in the first ship (optional follow-up).

## Source of truth

| Layer | Role |
|--------|------|
| Defaults | Same keys as CI3 `staff_jobs_schedule_defaults()` / Laravel `Modules/Jobs/config/schedule.php` merge base |
| Overrides | `application/cache/staff_jobs_schedule.json` (existing CI3 path) |
| Resolved schedule | defaults ⊕ JSON (normalize/validate keys the same way as CI3 helper) |

**Default change:** `staff_profile_completion_reminder` → `false` (was `{ hour: 8, minute: 30 }`).

**Migration note:** Servers that already wrote JSON with the reminder enabled keep that until an admin saves. New installs / missing key after default change → off. Optionally document that ops can set `"staff_profile_completion_reminder": false` once on production if the key is absent from JSON but they want to silence the old config default before deploy.

Path resolution for Laravel: resolve from staff repo root (same parent as `staff-portal/`), e.g. `{STAFF_ROOT}/application/cache/staff_jobs_schedule.json`, with `STAFF_ROOT` / existing path helpers already used by the portal.

## SPA UX

**Route:** `/settings/staff-jobs`  
**Hub:** card on Settings hub (“Staff jobs”, timer icon), permission 15.

### Section A — Cron schedule

Parity with CI3 `staff_jobs.php`:

- Instant mail every-minute toggle  
- Full mail queue interval (minutes; 0 = disabled)  
- Manage accounts hourly minute (or disabled)  
- Daily/weekly jobs: enable checkbox + hour/minute (+ weekday where meta says so)

**Save** → `PUT` API → write shared JSON → flash/toast success. Next Laravel schedule tick / CI3 tick uses resolved values (Laravel: reload config or read file on each `schedule:` build — prefer reading resolved schedule inside `configureSchedules` so no cache clear is required after Save).

### Section B — Run now

Buttons matching CI3 instant job list. Each triggers `POST` with `job_key`; backend maps to Artisan commands in `Modules/Jobs` (or `workplan:sync-pra` for PRA). Do **not** call CI3 `Modules::run`.

Show success/error toast; include truncated command output when useful for ops.

## API

Base: `/api/v1/settings/staff-jobs` (Settings module or Jobs module; prefer Settings for hub consistency, Jobs service for schedule I/O).

| Method | Purpose |
|--------|---------|
| `GET` | Resolved schedule, path (display), job meta (labels/help), instant job definitions |
| `PUT` | Body = schedule payload; validate/normalize; write JSON |
| `POST /run` | `{ "job_key": "..." }` → Artisan; 403 without permission 15 |

Auth: Sanctum + permission 15.

## Laravel scheduler wiring

`JobsServiceProvider::configureSchedules` must use **resolved** schedule (defaults + JSON), not only static `config('jobs.schedule')`.

Semantics unchanged:

- `false` / non-array → job not registered  
- array `{hour, minute[, weekday]}` → register at that time  
- booleans / intervals for mail and manage-accounts as today  

Keep `schedule.php` for non-schedule keys (mail logo, portal URL, system email) and as the PHP defaults source that mirrors CI3 defaults (including profile reminder **false**).

## CI3

- Keep `settings/staff_jobs` and helpers writing the same JSON.
- Update CI3 `staff_jobs_schedule_defaults()` so profile completion reminder default is `false` (same as Laravel).
- Optional follow-up: redirect CI3 URL to SPA.

## Permissions & security

- Read/write/run: settings permission **15** only.
- Run-now must not accept arbitrary shell; whitelist `job_key` → command map.
- JSON write must be atomic (temp file + rename) and refuse unknown keys.

## Testing

- Unit: schedule resolve/normalize (enabled/disabled, weekday, invalid keys); default profile reminder is false.
- Feature: GET/PUT/run require auth + permission; PUT persists JSON; run maps known keys and rejects unknown.
- Manual: toggle profile reminder off → not in `php artisan schedule:list`; Run now for a safe job (e.g. prune dry or birthday with care).

## Rollout

1. Ship default-off + shared resolve in Laravel + SPA page.  
2. Deploy SPA build.  
3. On production, confirm JSON path writable by web/php user; if reminder still firing, set key to `false` in JSON or Save from UI.  
4. Later: point CI3 settings nav to SPA.
