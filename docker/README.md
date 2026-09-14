# Docker (CBP modules)

One Compose project at the **repository root** runs **Apache + PHP** and **Redis**.
**MySQL defaults to the host** (or any reachable server). Bundled MySQL and queue workers are optional profiles.

## Quick start

```bash
cp docker/compose.env.example docker/.env
# Point each module .env at Docker Redis / host MySQL (see below).

docker compose --env-file docker/.env up -d --build
```

| URL | App |
|-----|-----|
| http://localhost:8080/staff/ | Staff portal SPA |
| http://localhost:8080/staff/backend/up | Staff portal Laravel |
| http://localhost:8080/staff/apm/ | APM |
| http://localhost:8080/staff/finance/ | Finance |
| http://localhost:8080/staff/helpdesk/ | Helpdesk |

## Module `.env` for Docker

| Variable | Typical Docker value |
|----------|----------------------|
| `DB_HOST` | `host.docker.internal` (host MySQL) or `mysql` (profile `bundled-db`) |
| `REDIS_HOST` | `redis` |
| `REDIS_PORT` | `6379` |
| Staff Share base (APM/Helpdesk/Finance) | `http://web/staff/backend` for in-network calls |
| Browser `APP_URL` | `http://localhost:8080/staff/...` (keep `/staff`) |

Files:

- `modules/staff-portal/backend/.env`
- `modules/apm/.env`
- `modules/finance/.env`
- `modules/helpdesk/backend/.env`

Do **not** put Compose-only vars in the repo-root `.env`; use `docker/.env`.

## Profiles

```bash
# Queue workers (APM + Helpdesk)
docker compose --env-file docker/.env --profile workers up -d --build

# Bundled MySQL 8 (greenfield)
docker compose --env-file docker/.env --profile bundled-db up -d --build
```

Bundled MySQL creates schemas `staff` and `apm_local` (see `docker/mysql/init/`).

## Production-ish

Bake the tree into the image (no bind-mount):

```bash
docker compose --env-file docker/.env \
  -f docker-compose.yml -f docker-compose.prod.yml \
  --profile workers up -d --build
```

Use external/managed MySQL only (no `bundled-db`). Publish SPA assets before bake.

## PDF annex tools

```bash
docker compose --env-file docker/.env exec web \
  bash -lc "which gs pdftoppm libreoffice && php -m | grep -i redis"
```

## Troubleshooting

- **403 on bind-mount (macOS):** entrypoint runs `chmod -R a+rX /var/www/staff`.
- **Daemon not running:** start Docker Desktop / Colima; `docker info` must show Server.
- **Apache conf changes:** rebuild (`up -d --build`); conf is baked into the image.
