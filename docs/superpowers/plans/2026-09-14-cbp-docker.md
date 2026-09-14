# CBP Docker stack Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `docker compose` run the full CBP stack under `modules/` with always-on Redis, external MySQL by default, optional bundled MySQL + queue workers, and a production compose override.

**Architecture:** One Apache/PHP `web` container serves the staff repo at `/var/www/staff` with Apache `Alias /staff` so public URLs stay `/staff/...`. Redis is a default Compose service. Queue workers reuse the web image. Bundled MySQL is opt-in via profile `bundled-db`.

**Tech Stack:** Docker Compose v2, `php:8.2-apache-bookworm`, Redis 7, MySQL 8 (optional), Apache mod_rewrite + existing root `.htaccess`.

**Spec:** `docs/superpowers/specs/2026-09-14-cbp-docker-design.md`

## Global Constraints

- Public URLs: `/staff/`, `/staff/backend`, `/staff/apm`, `/staff/finance`, `/staff/helpdesk` (unchanged).
- PHP base image: `php:8.2-apache-bookworm`.
- Redis always in Compose; MySQL external by default; profile name `bundled-db` (not `bundled-mysql`).
- Compose env file: `docker/.env` (from `docker/compose.env.example`) — do **not** overwrite repo-root `.env`.
- Preferred Apache layout (Option A): repo at `/var/www/staff`, `Alias /staff → /var/www/staff`.
- Workers: `queue-apm` + `queue-helpdesk` only (profile `workers`).
- Single Dockerfile source of truth: `docker/Dockerfile` (deprecate root `dockerfile` / `Dockerfile`).
- Out of scope: Azure Pipelines image push, Helm, Helpdesk L11→12, shared vendor.

## File map

| File | Responsibility |
|------|----------------|
| `docker/Dockerfile` | PHP 8.2 + extensions (incl. redis) + PDF tools; optional `prod` stage that `COPY`s the tree |
| `docker/apache/000-staff.conf` | `/staff` Alias + Directory; logs to stdout/stderr |
| `docker/entrypoint.sh` | chmod bind-mount; wait for Redis; fix storage perms for all Laravel apps; run CMD |
| `docker-compose.yml` | `web` + `redis` default; `mysql` (`bundled-db`); workers (`workers`) |
| `docker-compose.prod.yml` | No bind-mount; bake image; restart policies |
| `docker/compose.env.example` | `APP_PORT`, DB notes, Redis notes, MySQL profile vars |
| `docker/mysql/init/01-databases.sh` | Create `staff` + `apm_local` (+ grants) for bundled MySQL |
| `docker/README.md` | Operator docs for local + prod |
| `.dockerignore` | Exclude node_modules, vendors optional, git, logs under `modules/` |
| `dockerfile` / `Dockerfile` (root) | Short deprecation stub pointing to `docker/Dockerfile` |

---

### Task 1: Apache vhost for `/staff` (Option A)

**Files:**
- Modify: `docker/apache/000-staff.conf`
- Test: rebuild not required yet; configtest in Task 3

**Interfaces:**
- Consumes: none
- Produces: Apache serves filesystem `/var/www/staff` at URL prefix `/staff`

- [ ] **Step 1: Replace `docker/apache/000-staff.conf` with:**

```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    ServerName localhost

    # Parent DocumentRoot so Alias /staff matches production URL shape.
    DocumentRoot /var/www

    <Directory /var/www>
        Options FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    Alias /staff /var/www/staff

    <Directory /var/www/staff>
        DirectoryIndex index.php index.html
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
    LogLevel warn
</VirtualHost>
```

- [ ] **Step 2: Commit**

```bash
git add docker/apache/000-staff.conf
git commit -m "$(cat <<'EOF'
Point Docker Apache Alias at /staff modules layout.

EOF
)"
```

---

### Task 2: Dockerfile — PHP 8.2 + redis + modules paths

**Files:**
- Modify: `docker/Dockerfile`
- Modify: `.dockerignore`

**Interfaces:**
- Consumes: `docker/apache/000-staff.conf`, `docker/entrypoint.sh` (updated in Task 3)
- Produces: image with `WORKDIR /var/www/staff`; build targets `runtime` (default) and `prod`

- [ ] **Step 1: Replace `.dockerignore` with:**

```gitignore
.git
.github
**/.DS_Store
**/Thumbs.db
**/npm-debug.log
**/.phpunit.result.cache
.gitignore

# Node / frontend junk (SPA is published into public-spa separately)
**/node_modules
modules/**/frontend/node_modules
modules/**/frontend/dist
modules/apm/node_modules

# Runtime noise
**/storage/logs/*
**/storage/framework/cache/data/*
**/storage/framework/sessions/*
**/storage/framework/views/*
**/storage/debugbar
!**/.gitkeep

# Tests / docs not needed in prod image bake
**/tests
**/phpunit.xml
docs/superpowers
```

- [ ] **Step 2: Replace `docker/Dockerfile` with:**

```dockerfile
# syntax=docker/dockerfile:1

FROM php:8.2-apache-bookworm AS runtime

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

LABEL org.opencontainers.image.description="Africa CDC CBP (staff-portal, APM, finance, helpdesk)"

# PDF annex (APM): Ghostscript + Poppler; LibreOffice for Word attachments.
# redis-tools: entrypoint health wait via redis-cli.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    $PHPIZE_DEPS \
    unzip \
    git \
    curl \
    mariadb-client \
    redis-tools \
    ghostscript \
    poppler-utils \
    libreoffice-writer \
    fonts-liberation \
    fonts-dejavu-core \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mysqli \
        opcache \
        pdo_mysql \
        pcntl \
        bcmath \
        exif \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis opcache \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'upload_max_filesize=64M'; \
    echo 'post_max_size=64M'; \
    echo 'memory_limit=512M'; \
} > /usr/local/etc/php/conf.d/staff.ini

RUN a2enmod rewrite headers

COPY docker/apache/000-staff.conf /etc/apache2/sites-available/000-staff.conf
RUN a2dissite 000-default.conf 2>/dev/null || true \
    && a2ensite 000-staff.conf

WORKDIR /var/www/staff

COPY docker/entrypoint.sh /usr/local/bin/staff-entrypoint.sh
RUN chmod +x /usr/local/bin/staff-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/staff-entrypoint.sh"]
CMD ["apache2-foreground"]

# Production bake: copy tree into the image (no bind-mount).
FROM runtime AS prod
COPY . /var/www/staff
RUN chown -R www-data:www-data \
    /var/www/staff/modules/apm/storage \
    /var/www/staff/modules/apm/bootstrap/cache \
    /var/www/staff/modules/finance/storage \
    /var/www/staff/modules/finance/bootstrap/cache \
    /var/www/staff/modules/helpdesk/backend/storage \
    /var/www/staff/modules/helpdesk/backend/bootstrap/cache \
    /var/www/staff/modules/staff-portal/backend/storage \
    /var/www/staff/modules/staff-portal/backend/bootstrap/cache \
    2>/dev/null || true
```

- [ ] **Step 3: Commit**

```bash
git add docker/Dockerfile .dockerignore
git commit -m "$(cat <<'EOF'
Rebuild CBP Docker image for PHP 8.2, redis, and modules paths.

EOF
)"
```

---

### Task 3: Entrypoint — Redis wait + all module storage paths

**Files:**
- Modify: `docker/entrypoint.sh`

**Interfaces:**
- Consumes: env `REDIS_HOST` (default `redis`), `REDIS_PORT` (default `6379`), `SKIP_REDIS_WAIT` (optional `1`)
- Produces: writable Laravel storage under `/var/www/staff/modules/...`; then `exec` of CMD

- [ ] **Step 1: Replace `docker/entrypoint.sh` with:**

```bash
#!/usr/bin/env bash
set -euo pipefail

STAFF_ROOT="${STAFF_ROOT:-/var/www/staff}"
REDIS_HOST="${REDIS_HOST:-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"

# Bind mounts from macOS often use restrictive modes; Apache runs as www-data.
if [[ -d "${STAFF_ROOT}" ]]; then
    echo "staff-entrypoint: chmod -R a+rX on ${STAFF_ROOT} (bind mount; first run may take ~30s)..."
    chmod -R a+rX "${STAFF_ROOT}" 2>/dev/null || true
fi

fix_laravel_writable() {
    local app_root="$1"
    if [[ -d "${app_root}/storage" ]]; then
        mkdir -p "${app_root}/storage" "${app_root}/bootstrap/cache"
        chown -R www-data:www-data "${app_root}/storage" "${app_root}/bootstrap/cache" 2>/dev/null || true
        chmod -R ug+rwx "${app_root}/storage" "${app_root}/bootstrap/cache" 2>/dev/null || true
    fi
}

fix_laravel_writable "${STAFF_ROOT}/modules/apm"
fix_laravel_writable "${STAFF_ROOT}/modules/finance"
fix_laravel_writable "${STAFF_ROOT}/modules/helpdesk/backend"
fix_laravel_writable "${STAFF_ROOT}/modules/staff-portal/backend"

if [[ "${SKIP_REDIS_WAIT:-0}" != "1" ]]; then
    echo "staff-entrypoint: waiting for Redis at ${REDIS_HOST}:${REDIS_PORT}..."
    for _ in $(seq 1 60); do
        if redis-cli -h "${REDIS_HOST}" -p "${REDIS_PORT}" ping 2>/dev/null | grep -qi PONG; then
            echo "staff-entrypoint: Redis is up"
            break
        fi
        sleep 1
    done
fi

# Workers / one-shots skip Apache configtest when CMD is not apache.
if [[ "${1:-}" == "apache2-foreground" ]] || [[ "${1:-}" == "apache2ctl" ]]; then
    apache2ctl configtest
fi

exec "$@"
```

- [ ] **Step 2: Commit**

```bash
git add docker/entrypoint.sh
git commit -m "$(cat <<'EOF'
Wait for Redis and fix storage perms for all CBP modules.

EOF
)"
```

---

### Task 4: Compose stack — web, redis, workers, bundled-db

**Files:**
- Modify: `docker-compose.yml`
- Modify: `docker/compose.env.example`
- Modify: `docker/mysql/init/01-databases.sh`
- Create: `docker-compose.prod.yml`

**Interfaces:**
- Consumes: image from Task 2; mount `.:/var/www/staff`
- Produces: services `web`, `redis`, `mysql` (profile `bundled-db`), `queue-apm` / `queue-helpdesk` (profile `workers`)

- [ ] **Step 1: Replace `docker-compose.yml` with:**

```yaml
# CBP (staff-portal, APM, finance, helpdesk) — Apache/PHP + Redis.
#
#   cp docker/compose.env.example docker/.env
#   docker compose --env-file docker/.env up -d --build
#
# Optional workers:
#   docker compose --env-file docker/.env --profile workers up -d --build
#
# Optional bundled MySQL (set DB_HOST=mysql in each module .env):
#   docker compose --env-file docker/.env --profile bundled-db up -d --build
#
# Open: http://localhost:8080/staff/

services:
  redis:
    image: redis:7-alpine
    ports:
      - "${REDIS_PUBLISH_PORT:-6379}:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 10

  web:
    build:
      context: .
      dockerfile: docker/Dockerfile
      target: runtime
    ports:
      - "${APP_PORT:-8080}:80"
    volumes:
      - .:/var/www/staff
    extra_hosts:
      - "host.docker.internal:host-gateway"
    environment:
      REDIS_HOST: redis
      REDIS_PORT: "6379"
    depends_on:
      redis:
        condition: service_healthy

  queue-apm:
    profiles: ["workers"]
    build:
      context: .
      dockerfile: docker/Dockerfile
      target: runtime
    volumes:
      - .:/var/www/staff
    extra_hosts:
      - "host.docker.internal:host-gateway"
    environment:
      REDIS_HOST: redis
      REDIS_PORT: "6379"
    depends_on:
      redis:
        condition: service_healthy
      web:
        condition: service_started
    command:
      [
        "php",
        "/var/www/staff/modules/apm/artisan",
        "queue:work",
        "--sleep=1",
        "--tries=3",
        "--timeout=90",
      ]

  queue-helpdesk:
    profiles: ["workers"]
    build:
      context: .
      dockerfile: docker/Dockerfile
      target: runtime
    volumes:
      - .:/var/www/staff
    extra_hosts:
      - "host.docker.internal:host-gateway"
    environment:
      REDIS_HOST: redis
      REDIS_PORT: "6379"
    depends_on:
      redis:
        condition: service_healthy
      web:
        condition: service_started
    command:
      [
        "php",
        "/var/www/staff/modules/helpdesk/backend/artisan",
        "queue:work",
        "--sleep=1",
        "--tries=3",
        "--timeout=90",
      ]

  mysql:
    profiles: ["bundled-db"]
    image: mysql:8.0
    command: >
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --default-authentication-plugin=mysql_native_password
    ports:
      - "${MYSQL_PUBLISH_PORT:-33060}:3306"
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-root}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-staff}
      MYSQL_USER: ${MYSQL_USER:-staff}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-staff}
      STAFF_DB_NAME: ${STAFF_DB_NAME:-staff}
      APM_DB_NAME: ${APM_DB_NAME:-apm_local}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./docker/mysql/init:/docker-entrypoint-initdb.d:ro
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -uroot -p$$MYSQL_ROOT_PASSWORD || exit 1"]
      interval: 5s
      timeout: 5s
      retries: 15
      start_period: 35s

volumes:
  redis_data:
  mysql_data:
```

- [ ] **Step 2: Create `docker-compose.prod.yml`:**

```yaml
# Production-ish override: bake image, no source bind-mount, restart policies.
#
#   docker compose --env-file docker/.env \
#     -f docker-compose.yml -f docker-compose.prod.yml \
#     --profile workers up -d --build
#
# Requires module .env files present in the build context (or inject secrets
# via your orchestrator). Do not use profile bundled-db in production.

services:
  web:
    build:
      target: prod
    volumes: !reset []
    restart: unless-stopped

  redis:
    restart: unless-stopped

  queue-apm:
    build:
      target: prod
    volumes: !reset []
    restart: unless-stopped

  queue-helpdesk:
    build:
      target: prod
    volumes: !reset []
    restart: unless-stopped
```

**Compose merge note:** Prefer `volumes: []` if `!reset` is unsupported on the installed Compose. Verify:

```bash
docker compose version
# If config fails on !reset, change each `volumes: !reset []` to `volumes: []`
```

- [ ] **Step 3: Replace `docker/compose.env.example` with:**

```bash
# Copy to docker/.env (Compose only — does NOT replace repo-root .env):
#
#   cp docker/compose.env.example docker/.env
#   docker compose --env-file docker/.env up -d --build
#
COMPOSE_PROJECT_NAME=cbp-staff
APP_PORT=8080
REDIS_PUBLISH_PORT=6379

# -----------------------------------------------------------------------------
# Module Laravel .env (each app) — set manually for Docker:
# -----------------------------------------------------------------------------
# DB_HOST=host.docker.internal          # default: MySQL on the physical host
# REDIS_HOST=redis
# REDIS_PORT=6379
# For APM/Helpdesk/Finance calling Share inside the network:
#   STAFF_API_INTERNAL_BASE_URL=http://web/staff/backend
#   (or the app-specific equivalent staff API base)
# Browser URLs stay:
#   APP_URL=http://localhost:8080/staff/...
#
# -----------------------------------------------------------------------------
# Optional: docker compose --profile bundled-db
# Then set each module DB_HOST=mysql (and matching credentials below).
# -----------------------------------------------------------------------------
# MYSQL_ROOT_PASSWORD=root
# MYSQL_DATABASE=staff
# MYSQL_USER=staff
# MYSQL_PASSWORD=staff
# MYSQL_PUBLISH_PORT=33060
# STAFF_DB_NAME=staff
# APM_DB_NAME=apm_local
```

- [ ] **Step 4: Replace `docker/mysql/init/01-databases.sh` with:**

```bash
#!/bin/bash
set -e

STAFF_DB="${STAFF_DB_NAME:-staff}"
APM_DB="${APM_DB_NAME:-apm_local}"

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
CREATE DATABASE IF NOT EXISTS \`${STAFF_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`${APM_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${STAFF_DB}\`.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON \`${APM_DB}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL
```

- [ ] **Step 5: Commit**

```bash
git add docker-compose.yml docker-compose.prod.yml docker/compose.env.example docker/mysql/init/01-databases.sh
git commit -m "$(cat <<'EOF'
Add CBP Compose services for Redis, workers, and bundled MySQL.

EOF
)"
```

---

### Task 5: Docs + deprecate root Dockerfiles

**Files:**
- Modify: `docker/README.md`
- Modify: root `dockerfile` and `Dockerfile` (same content; both exist)
- Modify: root `README.md` only if it still documents CI-era `/apm` Docker URLs (search and fix those lines)

**Interfaces:**
- Consumes: compose commands from Task 4
- Produces: operator-facing docs matching success criteria in the spec

- [ ] **Step 1: Replace `docker/README.md` with full operator docs covering:**
  - Quick start: `cp docker/compose.env.example docker/.env` then `docker compose --env-file docker/.env up -d --build`
  - URL table: `/staff/`, `/staff/backend/up`, `/staff/apm/`, `/staff/finance/`, `/staff/helpdesk/`
  - Module `.env` table: `DB_HOST=host.docker.internal|mysql`, `REDIS_HOST=redis`, Share base `http://web/staff/backend`, browser `APP_URL` keeps `/staff`
  - Profiles: `workers`, `bundled-db` (creates `staff` + `apm_local`)
  - Prod: `-f docker-compose.yml -f docker-compose.prod.yml --profile workers`
  - PDF/redis verify command and 403 / daemon troubleshooting

Write the complete markdown file in the working tree (do not leave stubs). Include the exact commands from this plan’s Task 4 header comments.
- [ ] **Step 2: Replace root `dockerfile` and `Dockerfile` each with:**

```dockerfile
# DEPRECATED — use docker/Dockerfile (Compose build.dockerfile: docker/Dockerfile).
# Kept so accidental `docker build -f dockerfile .` fails loudly with a hint.
FROM scratch
LABEL org.opencontainers.image.description="DEPRECATED: use docker/Dockerfile"
```

(If `FROM scratch` with no CMD confuses local tooling, instead use a one-line comment file is invalid for Docker — prefer:

```dockerfile
# DEPRECATED: build with docker/Dockerfile via docker compose.
FROM php:8.2-apache-bookworm
RUN echo "DEPRECATED: use docker/Dockerfile via docker compose" >&2 && exit 1
```

)

- [ ] **Step 3: Grep root `README.md` for Docker / `/apm` URL docs; update any stale paths to `/staff/apm` and `docker/.env`.**

```bash
rg -n "docker compose|/apm|compose.env" README.md docker/README.md
```

- [ ] **Step 4: Commit**

```bash
git add docker/README.md dockerfile Dockerfile README.md
git commit -m "$(cat <<'EOF'
Document CBP Docker usage and deprecate root Dockerfiles.

EOF
)"
```

---

### Task 6: Smoke verification

**Files:**
- None (runtime only)

**Interfaces:**
- Consumes: full stack from Tasks 1–5
- Produces: pass/fail against spec success criteria

- [ ] **Step 1: Start default stack**

```bash
cp -n docker/compose.env.example docker/.env
docker compose --env-file docker/.env up -d --build
```

Expected: `web` and `redis` healthy/running; no `mysql` / queue containers.

- [ ] **Step 2: HTTP smoke**

```bash
curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8080/staff/
curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8080/staff/backend/up
curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8080/staff/apm/
curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8080/staff/finance/
curl -sS -o /dev/null -w "%{http_code}\n" http://localhost:8080/staff/helpdesk/
```

Expected: `200` or auth redirect (`302`/`301`) — not `404`/`500` from missing Alias.

- [ ] **Step 3: Redis from PHP**

```bash
docker compose --env-file docker/.env exec web \
  php -r 'echo (new Redis())->connect("redis", 6379) ? "ok\n" : "fail\n";'
```

Expected: `ok`

- [ ] **Step 4: Workers profile**

```bash
docker compose --env-file docker/.env --profile workers up -d --build
docker compose --env-file docker/.env ps
docker compose --env-file docker/.env logs --tail=30 queue-apm queue-helpdesk
```

Expected: both workers running; no fatal “artisan not found” / Redis connection errors in the first 30 lines.

- [ ] **Step 5: Bundled DB profile (optional if host MySQL already works)**

```bash
docker compose --env-file docker/.env --profile bundled-db up -d
docker compose --env-file docker/.env exec mysql \
  mysql -ustaff -pstaff -e "SHOW DATABASES;"
```

Expected: `staff` and `apm_local` listed.

- [ ] **Step 6: Prod override config check (build may be heavy — at least validate merge)**

```bash
docker compose --env-file docker/.env \
  -f docker-compose.yml -f docker-compose.prod.yml \
  --profile workers config >/tmp/cbp-compose-prod.yml
rg -n "volumes:|/var/www/staff|target: prod" /tmp/cbp-compose-prod.yml | head -40
```

Expected: `web` build `target: prod`; no host bind of `.` onto `/var/www/staff` for `web`.

- [ ] **Step 7: Commit only if smoke caused doc tweaks; otherwise done**

If README needed a fix after smoke, commit it. Do not commit `docker/.env`.

---

## Spec coverage checklist (self-review)

| Spec requirement | Task |
|------------------|------|
| One web + optional workers | Task 4 |
| Redis always on | Task 4 |
| External MySQL default / `bundled-db` | Task 4 |
| Option A `/staff` Alias | Task 1 |
| PHP 8.2 + redis + PDF tools | Task 2 |
| Entrypoint Redis wait + multi-app storage | Task 3 |
| `docker/.env` not overwriting root `.env` | Task 4–5 |
| Share URL `http://web/staff/backend` documented | Task 5 |
| `docker-compose.prod.yml` bake, no bind-mount | Task 4 |
| Deprecate root dockerfile | Task 5 |
| Success criteria smoke | Task 6 |
| Out of scope (Azure/Helm/L12/vendor) | Not planned |

## Plan complete
