# Docker (Staff + APM)

One Compose project at the **repository root** runs **Apache + PHP** only by default.  
**MySQL is expected on your physical machine** (or another server you can reach from the container).

## Requirements

- [Docker Engine](https://docs.docker.com/engine/install/) or **Docker Desktop** must be running.

### macOS (Docker Desktop)

- Install **Docker Desktop for Mac** (Apple Silicon or Intel). Start it from **Applications** and wait until the menu bar icon shows the engine is running.
- The Docker CLI should use the **`desktop-linux`** context (socket under `~/.docker/run/docker.sock`). If you ran `docker context use default`, commands may try **`/var/run/docker.sock`** and fail with *Cannot connect to the Docker daemon*. Fix with:
  ```bash
  docker context use desktop-linux
  docker info
  ```
- `host.docker.internal` is supported in Docker Desktop for Mac, so the app container can reach **MySQL on your Mac** when `DB_HOST=host.docker.internal` in `.env`.

#### “Cannot connect to the Docker daemon” (client works, no server)

If `docker version` shows **Client** but **“Cannot connect…”** for the daemon, the **engine is not running**. That is not fixed by this repository.

1. Start **Docker Desktop** (Applications) and wait until it is fully up, or run: `open -a Docker`
2. Then: `docker context use desktop-linux` and `docker info` (must show **Server** without errors).

**Still broken?** Use a different engine (CLI only, no paid Desktop required for basic use) — [Colima](https://github.com/abiosoft/colima):

```bash
brew install colima docker docker-compose
colima start
docker context use colima
docker run --rm hello-world
```

If `hello-world` works, from the `staff` repo run `docker compose up -d --build` as usual. For DB on the host, `host.docker.internal` usually works with Colima; if not, see the [Colima](https://github.com/abiosoft/colima) docs.

## Database on the physical host (default)

The web container connects using:

| Variable | Typical value |
|----------|----------------|
| `DB_HOST` | `host.docker.internal` — resolves to the machine running Docker (your laptop/server). |
| `DB_PORT` | `3306` (or whatever MySQL uses on the host). |

**Repo root `.env`** (copy from `docker/compose.env.example`) must set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APM_DB_NAME` to match **real databases and users on that MySQL**.

**APM `apm/.env`** must use the same database settings so Laravel can connect:

| Variable | Example |
|----------|---------|
| `DB_HOST` | `host.docker.internal` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | same as `APM_DB_NAME` / your APM schema name |
| `DB_USERNAME` / `DB_PASSWORD` | same as root `.env` `DB_USER` / `DB_PASS` |

Other URLs (unchanged):

| Variable | Example |
|----------|---------|
| `APP_URL` | `http://localhost:8080/apm` |
| `BASE_URL` / `CI_BASE_URL` | `http://localhost:8080/` |

### Allow MySQL to accept connections from Docker

1. **Listen on the network interface** — In `my.cnf`, ensure MySQL is not only bound to `127.0.0.1` if required for your setup. Often `bind-address = 0.0.0.0` or the host’s LAN IP is used so the Docker bridge can connect.

2. **User grants** — The DB user must be allowed to connect from the Docker subnet, e.g.:

   ```sql
   CREATE USER 'your_user'@'%' IDENTIFIED BY 'your_password';
   GRANT ALL ON staff_ci.* TO 'your_user'@'%';
   GRANT ALL ON apm_local.* TO 'your_user'@'%';
   FLUSH PRIVILEGES;
   ```

   Or a dedicated user whose host is `%`.

3. **Firewall** — Allow TCP `DB_PORT` from Docker (e.g. on Linux, rules for `docker0` or the host forwarding path).

4. **Remote DB server** — If MySQL is on **another** machine, set `DB_HOST` to that host’s IP or DNS name (must be reachable from inside the container; VPN/routing must allow it). Do not use `host.docker.internal` in that case.

### Linux

`docker-compose.yml` sets `extra_hosts: host.docker.internal:host-gateway` on the `web` service so `host.docker.internal` works like Docker Desktop.

### 403 Forbidden on `http://localhost:8080/`

Usually **bind-mount permissions**: Apache runs as `www-data`; files from macOS often look like mode `700` to the container. The entrypoint runs **`chmod -R a+rX /var/www/html`** on startup (may take a short while). Restart the web container after pulling changes:

```bash
docker compose up -d --build
docker compose logs -f web
```

Apache errors are written to **stderr** so they appear in **`docker compose logs web`**.

The Apache vhost is **baked into the image** (`docker/Dockerfile`). After editing `docker/apache/000-staff.conf`, run **`docker compose up -d --build`** (bind-mounting that file over `sites-available` fails on some Docker setups with a file/symlink mismatch).

## Quick start

```bash
cp docker/compose.env.example .env
# Edit .env — DB_* must match your host MySQL.
# Edit apm/.env — DB_HOST=host.docker.internal (or your DB server), same credentials/schema names.

docker compose up -d --build
```

- Staff portal: `http://localhost:8080/`
- APM: `http://localhost:8080/apm`

Generate APM keys if needed:

```bash
docker compose run --rm web bash -lc "cd apm && composer install && php artisan key:generate && php artisan jwt:secret --force"
```

## Optional: MySQL inside Docker

If you prefer a containerized DB for local dev:

1. In root `.env`, set **`DB_HOST=mysql`** (the Compose service name).
2. Start with the profile:

   ```bash
   docker compose --profile bundled-mysql up -d --build
   ```

   First boot creates `staff_ci` and `apm_local` and grants both to `MYSQL_USER`.

## Useful commands

```bash
docker compose logs -f web
docker compose exec web bash
# With bundled-mysql profile:
docker compose --profile bundled-mysql exec mysql mysql -ustaff -pstaff staff_ci
```

## Legacy files

The previous root `docker-compose.yml` and root `dockerfile` were replaced by `docker/Dockerfile` and this layout.
