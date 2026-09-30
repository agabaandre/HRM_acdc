# Docker (CBP modules)

One Compose project at the **repository root** runs **Apache + PHP** and **Redis**.
**MySQL defaults to the host** (or any reachable server). Bundled MySQL and queue workers are optional profiles.

## Quick start

```bash
cp docker/compose.env.example docker/.env
# Point each module .env at Docker Redis / host MySQL (see below).

docker compose --env-file docker/.env up -d --build
```

If Docker Desktop **Mounts denied** for `/opt/homebrew/...` (common), use the named-volume path instead:

```bash
./docker/sync-code.sh
docker compose --env-file docker/.env \
  -f docker-compose.yml -f docker-compose.volume.yml \
  up -d --no-build
# Re-run ./docker/sync-code.sh after local code changes.
```

Or add `/opt/homebrew/var/www` under Docker Desktop → Settings → Resources → File Sharing, then the normal bind-mount compose works.

| URL | App |
|-----|-----|
| http://localhost:8088/staff/ | Staff portal SPA |
| http://localhost:8088/staff/backend/up | Staff portal Laravel |
| http://localhost:8088/staff/apm/ | APM |
| http://localhost:8088/staff/finance/ | Finance |
| http://localhost:8088/staff/helpdesk/ | Helpdesk |

Port comes from `APP_PORT` in `docker/.env` (default **8088**).


## Module `.env` for Docker

| Variable | Typical Docker value |
|----------|----------------------|
| `DB_HOST` | `host.docker.internal` (host MySQL) or `mysql` (profile `bundled-db`) |
| `REDIS_HOST` | `redis` |
| `REDIS_PORT` | `6379` |
| Staff Share base (APM/Helpdesk/Finance) | `http://web/staff/backend` for in-network calls |
| Browser `APP_URL` | `http://localhost:8088/staff/...` (match `APP_PORT`) |

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

## PDF annex tools (optional — slow to build)

LibreOffice / Ghostscript / Poppler are **off by default** so local rebuilds stay fast.

```bash
# One-off image with Office/PDF tools (APM Word→PDF annex)
INSTALL_PDF_TOOLS=1 docker compose --env-file docker/.env build web
# or set INSTALL_PDF_TOOLS=1 in docker/.env then:
docker compose --env-file docker/.env up -d --build
```

```bash
docker compose --env-file docker/.env exec web \
  bash -lc "php -m | grep -i redis; command -v gs pdftoppm libreoffice || true"
```

## Troubleshooting

- **403 on bind-mount (macOS):** set `STAFF_CHMOD_TREE=1` on the `web` service env (entrypoint will `chmod -R a+rX`). Default startup only fixes Laravel `storage/` dirs.
- **Daemon not running:** start Docker Desktop / Colima; `docker info` must show Server.
- **Apache conf changes:** rebuild (`up -d --build`); conf is baked into the image.
- **Slow builds:** keep `INSTALL_PDF_TOOLS=0` (default). First build still compiles PHP extensions once; later rebuilds use BuildKit apt cache.