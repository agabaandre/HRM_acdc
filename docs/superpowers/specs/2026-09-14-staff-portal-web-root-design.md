# Staff Portal as web root (CodeIgniter removed)

**Date:** 2026-09-14  
**Status:** Implemented  
**Approach:** Root `.htaccess` + thin `index.php` serve `staff-portal/`; CI deleted

## Decision

- Delete the CodeIgniter staff app (not publicly reachable).
- Keep `staff-portal/` in place as the application folder.
- Make `/staff/` the portal SPA and `/staff/backend/` the Laravel API.
- Keep `apm/`, `finance/`, `helpdesk/` as sibling apps.

## Layout after cutover

| Path | Role |
|------|------|
| `/staff/` | Vue SPA (`staff-portal/spa-static.php`) |
| `/staff/backend/` | Symlink → `staff-portal/backend` |
| `/staff/assets/<hash>` | Vite build assets via `spa-static.php` |
| `/staff/apm`, `/finance`, `/helpdesk` | Unchanged |
| `cache/` | Shared JSON (jobs schedule, approver ids); web-denied |
| `old-staff-portal/` | Removed |

## Config

Portal base path / API URLs move from `/staff/staff-portal/…` to `/staff/…`.  
Azure OAuth redirect URIs must include `/staff/backend/auth/microsoft/callback`.

## Legacy URLs

`/staff/staff-portal/*` 301-redirects to `/staff/*`.
