# Staff Portal — optional Docker stack

Same role as [`helpdesk/docker`](../../helpdesk/docker): optional **Redis** (and optional **MySQL**) for local development. Production still runs on host Apache + PHP like Helpdesk.

The main Staff + APM web container lives at the **repo root** (`../docker-compose.yml`). This folder is only for staff-portal sidecar services.

## Quick start

```bash
cd staff-portal/docker
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

Prefer the shared host `staff` database when developing against real CI3/APM data (`DB_HOST=127.0.0.1`, port `3306`).

## Stop

```bash
cd staff-portal/docker
docker compose --profile bundled-mysql down
```

## Related

- [staff-portal README](../README.md)
- [Helpdesk docker compose](../../helpdesk/docker/docker-compose.yml)
- [Repo-root Staff + APM Docker](../../docker/README.md)
