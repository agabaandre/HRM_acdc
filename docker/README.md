# Docker (full CBP stack — shared `staff` database)

One Compose project at the **repository root** serves all modules.  
**Every app uses the same MySQL database: `staff`.**

| App | URL (default) | Migration tracker table |
|-----|----------------|-------------------------|
| Staff (CodeIgniter) | http://localhost:8080/ | CI3 `migrations` (if enabled) |
| staff-portal (Laravel) | http://localhost:8080/staff-portal/ | `staff_portal_migrations` |
| APM | http://localhost:8080/apm/ | `apm_migrations` |
| Finance | http://localhost:8080/finance/ | `finance_migrations` |
| Helpdesk | http://localhost:8080/helpdesk/ | `helpdesk_migrations` |

There are **no** separate Docker stacks under `apm/`, `finance/`, or `helpdesk/`.

## Database: host MySQL (default)

```bash
cp docker/compose.env.example .env
# Edit DB_NAME=staff, DB_USER, DB_PASS, JWT_SECRET

./docker/bootstrap.sh
```

`bootstrap.sh` creates module `.env` files, syncs DB settings, runs `composer install`, and executes **`migrate-all.sh`**.

### Host MySQL prerequisites

Ensure the `staff` database exists and Docker can reach your host MySQL:

```sql
CREATE DATABASE IF NOT EXISTS staff CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON staff.* TO 'root'@'%';
FLUSH PRIVILEGES;
```

Set `DB_HOST=host.docker.internal` in root `.env` (auto-fixed if you had `127.0.0.1`).

### Fresh database (no legacy CI3 tables)

```env
STAFF_LEGACY_SCHEMA_SKIP=false
```

Then `./docker/bootstrap.sh --bundled-mysql` or run migrations on host MySQL.  
This loads `staff-portal/database/schema/staff-legacy-structure.sql` first, then all Laravel migrations.

### Existing production-like database

Keep `STAFF_LEGACY_SCHEMA_SKIP=true` (default). Migrations only add **missing** tables/columns and skip conflicts (e.g. CI3 `jobs` vs Laravel queue, `user` vs `users`).

## Optional: bundled MySQL

```bash
./docker/bootstrap.sh --bundled-mysql
```

Sets `DB_HOST=mysql`, creates the `staff` schema, runs all migrations.  
MySQL exposed on host port **33060** (`MYSQL_PUBLISH_PORT`).

## Optional: Redis

```bash
docker compose --profile redis up -d
```

Use `REDIS_HOST=redis` in module `.env` files when needed.

## Commands

```bash
docker compose up -d --build
docker compose exec web sync-module-env.sh
docker compose exec web migrate-all.sh
docker compose logs -f web
```

### Host Apache (no Docker)

```bash
./docker/sync-env-host.sh    # patch apm/finance/helpdesk/staff-portal .env → staff DB
./docker/migrate-host.sh     # run all migrations on host MySQL
```

## Safety on shared `staff` DB

- **Never** let Laravel default migrations overwrite CI3 `jobs` (staff positions) — queue tables use `apm_queue_jobs`, `finance_queue_jobs`, `helpdesk_queue_jobs`, `sp_queue_jobs`.
- Each Laravel app tracks its own migrations in a **separate** `*_migrations` table.
- Helpdesk uses Laravel `users` (CI3 uses `user`) — no name clash.

## Module front-end builds

```bash
cd helpdesk/frontend && npm ci && npm run build
cd finance && npm run build
```
