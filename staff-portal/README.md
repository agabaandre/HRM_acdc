# Africa CDC Staff Portal (Laravel)

Modern rewrite of the CodeIgniter 3 staff portal, living alongside the legacy app at `../application/` until cutover. Built with **Laravel 12**, **Livewire 4**, **Laravel Sanctum**, and **nwidart/laravel-modules** for a modular structure that mirrors existing CI3 modules.

## Goals

- Same **MySQL `staff` database** and table names (no big-bang data migration).
- Same **look and feel** (shared `../assets` theme via `public/cbp-assets`).
- **SSO JWT** compatible with **APM**, **Finance**, and **Helpdesk** (`JWT_SECRET` + `?token=` hand-off).
- **Audit logging** on `user_logs` with optional ISO hash chain (parity with CI3 `LogUserAccess` / `log_user_action`).
- **Reusable Livewire** layouts and components per module.

## Requirements

- PHP 8.2+
- Composer 2.x
- MySQL 8+ (existing `staff` schema)
- Node.js 18+ (optional, for future Vite module assets)

## Quick start (local, existing `staff` DB)

```bash
cd staff-portal
composer install
cp .env.example .env   # then set DB_* and JWT_SECRET to match parent staff/.env
php artisan key:generate
ln -sfn ../../assets public/cbp-assets   # already done if you used setup below

# Shared staff DB: full migrate is safe (queue uses sp_* tables; skips legacy `user` / `jobs`).
php artisan migrate --force
php artisan module:migrate

composer dump-autoload
php artisan serve --host=127.0.0.1 --port=8081
```

Open: `http://127.0.0.1:8081/login`  
For Apache under `/staff/staff-portal/`, point the vhost or alias at `staff-portal/public` (see [Deployment](#deployment)).

### Fresh database (no legacy tables)

```bash
php artisan staff-portal:install-legacy-schema --force
php artisan migrate --path=database/migrations/2026_06_03_222319_create_personal_access_tokens_table.php --force
php artisan module:migrate
```

Schema source: `database/schema/staff-legacy-structure.sql` (mysqldump `--no-data` from `staff`).

## Environment

| Variable | Purpose |
|----------|---------|
| `DB_*` | Same as CI3 (`staff` database) |
| `JWT_SECRET` | **Must match** parent `.env` and APM/Helpdesk for SSO |
| `STAFF_PORTAL_BASE_URL` | Public URL of this app (trailing slash), e.g. `https://host/staff/staff-portal/public/` |
| `BASE_URL` | Legacy CI3 base (for module links during transition) |
| `STAFF_LEGACY_SCHEMA_SKIP` | `true` on existing DB — skips `staff-portal:install-legacy-schema` |
| `STAFF_AUDIT_INTEGRITY_CHAIN` | `true` when `user_logs` has `audit_row_hash` columns |
| `STAFF_SSO_TOKEN_TTL` | JWT lifetime in seconds (default `7200`) |

## Architecture

```
staff-portal/
├── app/                    # Shared commands, SsoJwt, LegacySchema
├── Modules/
│   ├── Core/               # Layouts, CBP home (module tiles), navigation shell
│   ├── Auth/               # Login (Livewire), Sanctum, SSO API
│   ├── Audit/              # user_logs service + access middleware
│   ├── Lookup/             # Regions, member states (nationalities), contract types, contact status
│   ├── Staff/              # Staff directory (Livewire) — migrate CI3 staff module here
│   ├── Share/              # API for other CBP apps (validate_session, current_staff)
│   ├── Dashboard/          # Dashboard widgets
│   ├── Contracts/          # staff_contracts
│   ├── Permissions/        # user_permissions, groups
│   ├── Leave/              # staff_leave, leave_types
│   ├── Performance/        # PPA / appraisals
│   ├── Attendance/         # attendance integration
│   ├── Tasks/              # tasks module
│   ├── Workplan/           # work planner
│   ├── Workflows/          # workflows
│   ├── Reports/            # reports
│   ├── Settings/           # settings
│   └── Jobs/               # jobs / acting
├── database/schema/        # Full legacy DDL snapshot
└── public/cbp-assets → ../../assets
```

## CI3 module → Laravel module map

| CI3 (`application/modules/`) | Laravel module | Status |
|-----------------------------|----------------|--------|
| `auth` | **Auth** | Login, Sanctum, SSO callback |
| `templates` | **Core** (layouts) | App / guest layouts, header, sidebar |
| `home` | **Core** (`CbpHome`) | Module launcher + SSO links |
| `dashboard` | **Dashboard** | Stub — port widgets |
| `staff` | **Staff** | Staff list Livewire started |
| `contracts` | **Contracts** | Scaffolded |
| `permissions` | **Permissions** | Scaffolded |
| `share` | **Share** | `validate_session`, Sanctum APIs |
| `lists` / lookups | **Lookup** | Region, MemberState, ContractType, ContactStatus models |
| `leave` | **Leave** | Balances, apply, approvals; policy in Settings |
| `settings` (leave_types) | **Settings** → Leave | Policy rules, leave types, accrual config |
| `performance` | **Performance** | Scaffolded |
| `attendance` | **Attendance** | Scaffolded |
| `tasks` / `weektasks` | **Tasks** | Scaffolded |
| `workplan` | **Workplan** | Scaffolded |
| `workflows` | **Workflows** | Scaffolded |
| `reports` | **Reports** | Scaffolded |
| `settings` | **Settings** | Scaffolded |
| `jobs` | **Jobs** | Scaffolded |
| `admanager`, `aleave`, etc. | Add modules as needed | Planned |

## Authentication & SSO

### Web session

- Guard: `web` → `Modules\Auth\Models\PortalUser` (`user` table, `auth_staff_id` → `staff`).
- Login: Livewire `Auth\LoginForm` at `/login` — **Microsoft SSO** (primary, same as CI3) plus optional email/password when `ALLOW_ALTERNATIVE_LOGIN=true` and `user.allow_email_login=1`.
- Microsoft routes: `GET /auth/microsoft` → Entra authorize; `GET /auth/microsoft/callback` → session (register this redirect URI in Azure alongside CI3 `auth/callback` if needed).
- Env: `TENANT_ID`, `CLIENT_ID`, `CLIENT_SEC_VALUE` (or `MICROSOFT_*` aliases); optional `MICROSOFT_REDIRECT_URI` (defaults to `{APP_URL}/auth/microsoft/callback`).

### Sanctum (API / machine clients)

- `POST /api/v1/token/issue` (authenticated) — personal access token + SSO JWT payload.
- `GET /api/v1/session` — session array for integrators.
- `POST /api/v1/sso/validate` — validate JWT for microservices.

### SSO hand-off (APM / Finance / Helpdesk)

Same contract as CI3 `Home::build_sso_jwt()`:

1. Logged-in user opens a CBP module tile on **CbpHome**.
2. App appends `?token=<JWT>` (HS256 with `JWT_SECRET`).
3. Target app decodes token and creates its session (see `apm/routes/web.php`).

Incoming SSO: `GET /sso/callback?token=...` establishes a Laravel session.

**Security practices**

- Keep `JWT_SECRET` out of git; rotate with coordinated deploy across CBP apps.
- Use HTTPS in production; set `SANCTUM_STATEFUL_DOMAINS` for SPA domains if needed.
- Prefer Sanctum tokens for server-to-server; short TTL on SSO JWTs.
- Rate-limit login and SSO validate endpoints (add `Route::middleware('throttle:…')` in hardening pass).
- Session driver: `database` (use existing `sessions` table or create Laravel `sessions` migration on cutover).

## Audit logs

`Modules\Audit\Services\AuditLogService`:

- Mirrors CI3 `log_user_action()` / `LogUserAccess` hook.
- Writes to **`user_logs`** (`http_method`, `request_uri`, `event_type`, old/new JSON).
- Optional **integrity chain** when `STAFF_AUDIT_INTEGRITY_CHAIN=true` and columns exist (`application/sql/add_user_logs_iso_audit_columns.sql`).

Middleware `LogStaffPortalAccess` is appended to the `web` group.

## Lookup data

Eloquent models (legacy table names preserved):

| Model | Table | Notes |
|-------|-------|--------|
| `Region` | `regions` | |
| `MemberState` | `nationalities` | AU member states / nationalities |
| `ContractType` | `contract_types` | |
| `ContactStatus` | `status` | Active, Separated, etc. |

Seed via existing CI3 data or admin UI (to be ported into **Lookup** Livewire CRUD).

## Share API (CI3 compatibility)

| CI3 | Laravel |
|-----|---------|
| `GET /share/validate_session` | `GET /share/validate_session` (Bearer SSO JWT) |
| (APM integration) | `GET /api/share/validate_session` |
| | `GET /api/share/current_staff` (Sanctum) |

## Livewire conventions

- Page components: `Modules\{Module}\Livewire\*` with `#[Layout('core::layouts.app')]`.
- Guest/auth pages: `#[Layout('core::layouts.guest')]`.
- Shared UI: add Blade components under `Modules/Core/resources/views/components/`.
- Prefer small reusable components (`<x-cbp.card>`, `<x-cbp.page-title>`) as you port each CI3 view.

## Artisan commands

| Command | Description |
|---------|-------------|
| `php artisan staff-portal:install-legacy-schema` | Create missing tables from `database/schema/staff-legacy-structure.sql` |
| `php artisan module:migrate` | Run all enabled module migrations |
| `php artisan module:make Name` | New module scaffold |

## Deployment

1. Deploy `staff-portal/` next to `application/` and `apm/`.
2. `composer install --no-dev --optimize-autoloader`
3. Link assets: `ln -sfn ../../assets public/cbp-assets`
4. Configure `.env` on server (`APP_URL`, `DB_*`, `JWT_SECRET`).
5. Apache: `DocumentRoot` → `staff-portal/public` or rewrite via `staff-portal/.htaccess` + `server.php` (same pattern as `apm/`).
6. Run only **Sanctum** + **module** migrations on existing DB (see Quick start).
7. Cutover plan: change `BASE_URL` / portal links from `index.php` to `staff-portal/public`; keep CI3 read-only until sign-off.

## Transition from CodeIgniter

1. **Phase 1 (done in scaffold):** Core, Auth, Audit, Lookup, Share API, Staff list, layouts.
2. **Phase 2:** Port high-traffic modules (staff CRUD, contracts, permissions) into Livewire with audit on write.
3. **Phase 3:** Leave, performance, attendance, reports.
4. **Phase 4:** Decommission CI3 routes; redirect `/staff/` → `staff-portal/public`.

Laravel queue tables use the **`sp_` prefix** (`sp_queue_jobs`, `sp_job_batches`, `sp_failed_jobs`) so they never conflict with CI3 **`jobs`** (staff position titles: `job_id`, `job_name`). Default `php artisan migrate` is safe on the shared `staff` database.

## Testing

```bash
php artisan test
```

Add feature tests for SSO encode/decode, login, and audit row creation.

## Related documentation

- [CBP documentation hub](../documentation/README.md)
- [APM documentation](../apm/documentation/README.md)
- [CI3 auth improvements](../application/modules/auth/README_IMPROVEMENTS.md)
- [Staff portal security notes](../STAFF_PORTAL_SECURITY_README.txt)

## Support

For module-specific porting tasks, work in the matching `Modules/{Name}` directory, register routes in `Modules/{Name}/routes/web.php`, and reuse `core::layouts.app`.
