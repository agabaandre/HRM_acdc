# Staff Portal — optional Docker sidecar

Optional **Redis** (and optional **MySQL**) for local development of this module only — same idea as `modules/helpdesk/docker`.

For the **full CBP stack** (Apache + PHP + Redis for all apps), use the **repository root** Compose project instead:

- [../../docker/README.md](../../docker/README.md)
- [../../docker-compose.yml](../../docker-compose.yml)

Production still typically runs on host Apache + PHP; root Compose is the supported container path for local/prod-ish runs.

## Quick start (sidecar)

```bash
cd modules/staff-portal/docker
docker compose up -d
```

Redis: `localhost:6379` (override with `STAFF_PORTAL_REDIS_PORT`).

### Optional bundled MySQL

```bash
docker compose --profile bundled-mysql up -d
```

MySQL: `localhost:33070` → container `3306` (override with `STAFF_PORTAL_MYSQL_PORT`).

Default credentials: user/pass/db `staff` / `staff` / `staff` (root password `root`).

## Wire into `backend/.env`

### Redis (queues / cache)

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

When using **root** Compose, set `REDIS_HOST=redis` instead (Docker network DNS).

Default staff-portal setup can keep `database` queue/cache; Redis is optional.

### Bundled MySQL

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=33070
DB_DATABASE=staff
DB_USERNAME=staff
DB_PASSWORD=staff
```

Prefer the shared host `staff` database when developing against real CBP data (`DB_HOST=127.0.0.1`, port `3306`).

## Stop

```bash
cd modules/staff-portal/docker
docker compose --profile bundled-mysql down
```

## Related

- [staff-portal README](../README.md)
- [Helpdesk docker](../../helpdesk/docker/)
- [Repo-root CBP Docker](../../docker/README.md)
