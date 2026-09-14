# Staff root cleanup: modules/ layout (no backend symlink)

**Date:** 2026-09-14  
**Status:** Approved for planning (sections 1–2)  
**Approach:** Move all CBP apps under `modules/`; keep public URLs unchanged; replace `backend` symlink with Apache rewrites; remove unused root clutter.

## Goals

1. Clean repository root — apps live under `modules/`.
2. Avoid the root `backend` symlink (clarity / deploy hygiene; not a performance requirement).
3. Keep public URLs stable: `/staff/`, `/staff/backend`, `/staff/apm`, `/staff/finance`, `/staff/helpdesk`.
4. Delete unused leftover folders/files from the CodeIgniter era.

## Non-goals

- Changing public URL paths to `/staff/modules/...`.
- Consolidating `docs/` and `documentation/` in this change.
- Moving `uploads/` / host data roots (see `docs/STORAGE.md`).

## Decisions

| Topic | Choice |
|-------|--------|
| Public URLs | Unchanged (Approach A) |
| What moves into `modules/` | All four: staff-portal, apm, finance, helpdesk (Approach B) |
| `/staff/backend` without symlink | Apache rewrite/Alias only (recommended) |
| Overall approach | Modules + Apache maps; delete clutter (Approach 1) |

## Target layout

```
staff/
├── modules/
│   ├── staff-portal/     # Vue SPA + Laravel API
│   ├── apm/
│   ├── finance/
│   └── helpdesk/
├── assets/               # shared CBP JS (session refresh, SSO launch)
├── cache/                # shared JSON cache (web-denied)
├── uploads/              # legacy local uploads (dev)
├── shared/               # StaffStorage.php, etc.
├── scripts/
├── docs/
├── documentation/
├── docker/               # (+ compose/dockerfile if still used)
├── .htaccess             # routing only — no backend symlink
├── index.php             # thin → modules/staff-portal/spa-static.php
├── .env
├── README.md
└── …
```

## URL → filesystem mapping

| Public URL | Internal path |
|------------|---------------|
| `/staff/` and SPA client routes | `modules/staff-portal/spa-static.php` |
| `/staff/assets/<file>` | Vite assets under `modules/staff-portal` (via spa-static / publish) |
| `/staff/backend/*` | `modules/staff-portal/backend` (rewrite; **no** root symlink) |
| `/staff/apm/*` | `modules/apm` |
| `/staff/finance/*` | `modules/finance` |
| `/staff/helpdesk/*` | `modules/helpdesk` |

Legacy compatibility (keep):

- `/staff/staff-portal/*` → 301 `/staff/*` (already present; update if physical path checks change).

## Root clutter

### Remove

- `backend` symlink
- `resources/` (old CI front-end)
- `utils/` (old CI helper)
- `Africa CDC Funding Portfolio.xlsx`
- `000-default.conf`, `nginx-http.conf` (stale snippets; real Docker/Apache notes stay under `docker/` or docs)

### Keep

- `assets/`, `cache/`, `uploads/`, `shared/`
- `scripts/`, `docs/`, `documentation/`
- `docker/`, `docker-compose.yml`, `dockerfile` (if still used)
- `.htaccess`, `index.php`, `.env`, `README.md`, `LICENSE`, `azure-pipelines.yml`

## Migration steps

1. Create `modules/`.
2. `git mv staff-portal apm finance helpdesk modules/`.
3. Remove root `backend` symlink.
4. Update root `.htaccess` and `index.php` to `modules/staff-portal/...` and passthrough rewrites for `apm` / `finance` / `helpdesk` to `modules/...`.
5. Update scripts/docs that hardcode old relative paths (e.g. `scripts/fix-laravel-storage-permissions.sh`, README, deploy examples, `staff-portal` → `modules/staff-portal`).
6. Delete clutter list above.
7. Smoke-test:
   - `GET /staff/` (SPA)
   - `GET /staff/backend/up`
   - `GET /staff/apm/` (or login redirect)
   - `GET /staff/finance/`
   - `GET /staff/helpdesk/`
   - SSO launch from CBP home still posts to `/staff/backend/home/launch_module`

Prefer **one** commit for the move + routing updates.

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Hardcoded paths in `.env` / systemd / Docker | Grep and update; Azure redirect URI stays `/staff/backend/...` (URL unchanged) |
| Apache DocumentRoot still expects sibling dirs | `.htaccess` must rewrite `apm|finance|helpdesk` into `modules/` before SPA catch-all |
| Local absolute paths in tooling | Update `fix-laravel-storage-permissions.sh` app list to `modules/.../backend` |
| Large git history / move noise | Use `git mv`; single focused commit |

## Performance note

Directory symlinks vs Apache rewrites are not a meaningful performance factor for these Laravel/Vue apps. The change is for **structure and deploy reliability**.

## Out of scope follow-ups

- Merge `docs/` + `documentation/`
- Move shared `assets/` under `modules/staff-portal` or `shared/`
- Production Apache vhost Alias examples in ops runbooks
