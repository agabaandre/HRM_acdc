# Setup wizard: MySQL, Redis, migrations, and production defaults

**Date:** 2026-10-02  
**Status:** Implemented  
**Approach:** Global MySQL + Docker Redis defaults; always migrate; seed only new DBs (Approach A helpers in `setup.sh`)

## Goals

1. Always surface **MySQL username and password** during `./setup.sh` (never silent “keep current” that leaves empty/`sqlite` module envs).
2. When **Deploy = Docker Compose**, default **Redis to Compose Redis** (`REDIS_HOST=redis`), with an explicit Docker vs external choice.
3. Ask once whether MySQL is **Docker bundled** or **external**, with **external as the default**.
4. Write shared MySQL settings into **every module** with `DB_CONNECTION=mysql` (fix finance/helpdesk falling back to sqlite and “could not find driver”).
5. **Always run migrations** when module installers run (pending only; no per-app migrate prompt).
6. **Run seeders only for new apps** — detect empty database (no tables); existing DBs skip seed.
7. Default **Run module installers = Yes** and **Installer profile = production**.

## Non-goals

- Per-module different MySQL hosts (one global credential set + per-module `DB_DATABASE` name only).
- Interactive per-app migrate/seed prompts (replaced by automatic rules above).
- Changing Laravel migration/seeder PHP content.
- Auto-creating MySQL databases on the server (still operator/DBA responsibility unless a module script already does it).
- Fixing missing `STAFF_API_*` Share credentials beyond clearer warnings (separate from DB wizard).

## Decisions (from brainstorming)

| Topic | Choice |
|-------|--------|
| MySQL scope | **Global** Docker vs external; push same host/user/pass to all modules |
| MySQL default | **External** (even when deploy is Docker) |
| Redis (Docker deploy) | Prompt Docker Redis (**default**) vs external |
| Redis (Host Apache) | Keep today’s host default (`127.0.0.1`); still overridable |
| Migrations | **Always** run when installers run |
| Seeders | **Only if DB has no tables** (new app) |
| Installers | Default **Yes** + **production** |
| “Keep current” DB | Remove as a primary path for new/setup runs; if existing install and operator insists, still prefer re-prompting credentials over silent keep |

## Problem (current behaviour)

- Global `DB_CHOICE` can be **Keep current**, which skips `apply_db_to_file` — modules keep stale `DB_CONNECTION=sqlite` or empty passwords.
- Finance module root `.env` / examples still allow sqlite; production migrate then fails with `could not find driver`.
- Redis is a free-text host prompt; Docker installs do not clearly ask Compose Redis vs external.
- Final step defaults **Run installers = No**; production profile only preferred when `SITE_KIND=production`.
- No empty-DB detection for seeders.

## Wizard flow (target)

```
Site role (production | demo)
Install type (new | existing)
Deploy target (Host Apache | Docker Compose)   # Docker remains a common default

[if Docker]
  Redis: 1) Docker Redis (default)  2) External Redis
  → Docker: REDIS_HOST=redis
  → External: prompt REDIS_HOST / PORT / PASSWORD
[if Host]
  prompt REDIS_HOST (default 127.0.0.1) / PORT / PASSWORD  # unchanged pattern

Database: 1) Docker bundled MySQL  2) External MySQL (default)
  → bundled: DB_HOST=mysql (Compose profile bundled-db); port/user/pass/name
  → external: DB_HOST default host.docker.internal (Docker) or 127.0.0.1 (host);
              always prompt port, user, password, root DB_NAME

… Microsoft / mail / secrets (unchanged) …

Per module (staff-portal, APM, finance, helpdesk, risk-register):
  prompt DB_DATABASE name only (existing defaults)
  apply_db_to_file + DB_CONNECTION=mysql on module env files

Summary
Rebuild SPA? (unchanged)
Run module installers? default Yes
Installer profile: default production
  for each module installer:
    always migrate (no --skip-migrate unless probe impossible and operator set skip — prefer always migrate)
    if setup_db_is_empty(module DB) → seed; else --skip-seed
```

## Empty database detection

Helper e.g. `scripts/setup/db-probe.sh` / function `setup_db_is_empty`:

1. Inputs: host, port, user, password, database name.
2. Use `mysql` or `mariadb` client when available:
   - Connection failure / missing database → treat as **new** (empty) and log a warn.
   - `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?` → **0** means new; **>0** means existing.
3. Prefer probing from the **host** during `./setup.sh`. When deploy is Docker and MySQL is `mysql` (bundled), probe may need `docker compose exec` or `127.0.0.1` mapped port — document fallback: if probe fails, treat as **new** (run seeders) and warn.
4. Do **not** use sqlite for this probe; mysql connection only.

## Module installer wiring

Existing flags:

- `--skip-migrate` / `--skip-seed` on finance, helpdesk, staff-portal, risk-register production scripts.
- APM bootstrap today is ad hoc (`composer` + `key:generate`); extend only as needed to run `php artisan migrate` always and seed when empty (minimal change; do not invent demo seed for APM if none exists).

Root `setup.sh` when `RUN_INSTALL=1` and production profile:

| Module | Migrate | Seed |
|--------|---------|------|
| staff-portal | always | if empty |
| finance | always | if empty |
| helpdesk | always | if empty |
| risk-register | always | if empty |
| APM | always (if artisan present) | if empty and a safe seeder exists |

Production seeders remain **non-demo** (existing `--with-demo-seed` stays off).

## Env write rules

When applying DB to a module file:

- Set `DB_CONNECTION=mysql` (or module-equivalent).
- Set `DB_HOST`, `DB_PORT`, `DB_USERNAME`/`DB_USER`, `DB_PASSWORD`/`DB_PASS`, `DB_DATABASE`.
- Root `.env` mirrors the same global MySQL credentials (`DB_USER` / `DB_PASS` naming as today).

## Files likely touched

- `setup.sh` — Redis choice, MySQL default external, remove silent keep-for-new-installs, installer defaults, per-module empty detection + flags.
- `scripts/setup/prompt.sh` — optional thin wrappers only if needed.
- New: `scripts/setup/db-probe.sh` (or functions sourced by setup).
- Module `setup-production.sh` callers only (flags); avoid rewriting each installer unless APM needs migrate/seed hooks.
- `README.md` / `docker/README.md` — short wizard notes (MySQL external default; Redis Docker default; always migrate; seed if empty).

## Risks

| Risk | Mitigation |
|------|------------|
| Probe from host cannot reach Compose `mysql` hostname | Fall back to published port / `docker compose exec`; on failure treat as new + warn |
| Seeding an “empty” DB that is wrong/misnamed | Print detected database name and “new → seeding” before seed |
| Always migrating on huge prod DBs | Acceptable; Laravel pending migrations are no-ops when current |
| Finance still reading wrong `.env` (module root vs `backend/.env`) | Ensure `apply_db` hits every file configure-env already uses |

## Success criteria

- Fresh Docker install with **external MySQL** shows Redis Docker-vs-external and MySQL Docker-vs-external (external default), and **always** prompts MySQL user/password.
- All five modules receive `DB_CONNECTION=mysql` and shared credentials; finance no longer migrates against sqlite by accident.
- Installers default to production + run; migrations always attempt; seeders only when table count is 0.
- Demo site Supervisor skip behaviour unchanged.
