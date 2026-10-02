# Docker PHP-FPM tuning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the CBP Compose `web` image on Apache event MPM + PHP-FPM with 8 CPU / ~32 GB sized configs so Docker stops feeling slow under multi-module Laravel load.

**Architecture:** Base image `php:8.2-fpm-bookworm`; install Apache 2.4 with `proxy_fcgi` + `mpm_event`; FPM listens on a unix socket; entrypoint starts php-fpm then Apache. Prod overlay disables OPcache timestamp validation. Redis gets maxmemory + LRU.

**Tech Stack:** Docker / Compose, PHP 8.2 FPM, Apache 2.4 event, Redis 7, existing Supervisor workers profile.

**Spec:** `docs/superpowers/specs/2026-10-02-docker-php-fpm-tuning-design.md`

## Global Constraints

- Hardware target: **8 CPUs / ~31.3 GB RAM** (hybrid tier 32 + 8-core FPM).
- SAPI: **PHP-FPM + Apache event** in one `web` image (not mod_php, not separate FPM service).
- FPM: `max_children=40`, start/min_spare=6, max_spare=12, `max_requests=500`.
- OPcache: 512 MB / interned 64 / 100000 files; prod `validate_timestamps=0`; bind-mount/dev `=1`.
- PHP `memory_limit=256M`; uploads 128M.
- Apache: `MaxRequestWorkers≈250`, KeepAlive on.
- Redis: `maxmemory≈2gb`, `allkeys-lru`.
- MySQL stays host/external by default.
- Preserve `/staff/` Alias URL shape.
- Commit subjects under ~72 chars; no Conventional Commit prefixes.

## File map

| Path | Responsibility |
|------|----------------|
| `docker/php/www.conf` | FPM pool |
| `docker/php/staff.ini` | Shared PHP + OPcache (dev validate on) |
| `docker/php/staff-prod.ini` | Prod-only OPcache validate off |
| `docker/apache/mpm_event.conf` | Event MPM + KeepAlive |
| `docker/apache/000-staff.conf` | Site + proxy_fcgi for PHP |
| `docker/Dockerfile` | FPM base + Apache + confs |
| `docker/entrypoint.sh` | Start FPM when serving Apache |
| `docker-compose.yml` | Redis tuning; optional worker limits |
| `docker-compose.prod.yml` | Bake prod PHP ini |
| `docker/README.md` | Rebuild + Laravel cache notes |

---

### Task 1: PHP-FPM and PHP/OPcache config files

**Files:**
- Create: `docker/php/www.conf`
- Create: `docker/php/staff.ini`
- Create: `docker/php/staff-prod.ini`

**Interfaces:**
- Produces: confs copied into image at `/usr/local/etc/php-fpm.d/zz-staff-www.conf`, `/usr/local/etc/php/conf.d/staff.ini`, and (prod) `z-staff-prod.ini`

- [ ] **Step 1: Write `docker/php/www.conf`**

```ini
[www]
user = www-data
group = www-data

listen = /var/run/php/php-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 40
pm.start_servers = 6
pm.min_spare_servers = 6
pm.max_spare_servers = 12
pm.max_requests = 500
pm.process_idle_timeout = 10s

request_terminate_timeout = 120s
clear_env = no
catch_workers_output = yes
decorate_workers_output = no
```

- [ ] **Step 2: Write `docker/php/staff.ini`**

```ini
expose_php = Off
max_execution_time = 120
max_input_time = 120
memory_limit = 256M
post_max_size = 128M
upload_max_filesize = 128M
max_file_uploads = 50
max_input_vars = 10000
default_socket_timeout = 120
display_errors = Off
log_errors = On
realpath_cache_size = 4096K
realpath_cache_ttl = 600

[opcache]
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=512
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=100000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.save_comments=1
opcache.enable_file_override=1
opcache.max_wasted_percentage=5
```

- [ ] **Step 3: Write `docker/php/staff-prod.ini`**

```ini
; Overlay for baked prod images (docker-compose.prod.yml / target prod)
opcache.validate_timestamps=0
opcache.revalidate_freq=0
```

- [ ] **Step 4: Commit**

```bash
git add docker/php/
git commit -m "Add Docker PHP-FPM and OPcache configs for 32GB hosts."
```

---

### Task 2: Apache event MPM + FPM vhost

**Files:**
- Create: `docker/apache/mpm_event.conf`
- Modify: `docker/apache/000-staff.conf`

**Interfaces:**
- Consumes: FPM socket `/var/run/php/php-fpm.sock`
- Produces: Apache proxies `*.php` via `proxy:unix:…|fcgi://localhost/`

- [ ] **Step 1: Write `docker/apache/mpm_event.conf`**

```apache
# Event MPM — sized for ~8 CPU / 32 GB CBP Docker host
<IfModule mpm_event_module>
    ServerLimit             12
    StartServers            4
    MinSpareThreads         50
    MaxSpareThreads         150
    ThreadLimit             64
    ThreadsPerChild         25
    MaxRequestWorkers       250
    MaxConnectionsPerChild  10000
</IfModule>

KeepAlive On
MaxKeepAliveRequests 500
KeepAliveTimeout 5
```

- [ ] **Step 2: Update `docker/apache/000-staff.conf`**

Keep existing DocumentRoot / Alias `/staff` / Directory blocks. Add inside the VirtualHost (after Alias block):

```apache
    # PHP via FPM (not mod_php)
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/var/run/php/php-fpm.sock|fcgi://localhost/"
    </FilesMatch>

    <Proxy "unix:/var/run/php/php-fpm.sock|fcgi://localhost/" timeout=120>
        # empty — required for some Apache versions
    </Proxy>
```

Ensure `DirectoryIndex` still prefers `index.php`.

- [ ] **Step 3: Commit**

```bash
git add docker/apache/
git commit -m "Proxy Docker Apache PHP to FPM over unix socket."
```

---

### Task 3: Dockerfile — FPM base + Apache

**Files:**
- Modify: `docker/Dockerfile`

**Interfaces:**
- Consumes: confs from Tasks 1–2
- Produces: `runtime` and `prod` targets with php-fpm + apache2

- [ ] **Step 1: Replace runtime stage base and Apache install**

Use this structure (preserve `INSTALL_PDF_TOOLS`, `install-php-extensions` list, composer copy, supervisor install, WORKDIR, entrypoint):

```dockerfile
# syntax=docker/dockerfile:1
FROM php:8.2-fpm-bookworm AS runtime

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

ARG INSTALL_PDF_TOOLS=0

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    set -eux; \
    rm -f /etc/apt/apt.conf.d/docker-clean; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        apache2 \
        unzip git curl \
        mariadb-client redis-tools; \
    if [ "$INSTALL_PDF_TOOLS" = "1" ]; then \
        apt-get install -y --no-install-recommends \
            ghostscript poppler-utils libreoffice-writer \
            fonts-liberation fonts-dejavu-core; \
    fi; \
    install-php-extensions \
        gd intl mysqli opcache pdo_mysql pcntl bcmath exif zip redis; \
    a2dismod mpm_prefork mpm_worker 2>/dev/null || true; \
    a2enmod mpm_event proxy proxy_fcgi rewrite headers setenvif; \
    mkdir -p /var/run/php; \
    chown www-data:www-data /var/run/php

# Remove default FPM pool that may conflict; use our pool
RUN rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf.default 2>/dev/null || true

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-staff-www.conf
COPY docker/php/staff.ini /usr/local/etc/php/conf.d/staff.ini
COPY docker/apache/mpm_event.conf /etc/apache2/conf-available/cbp-mpm-event.conf
COPY docker/apache/000-staff.conf /etc/apache2/sites-available/000-staff.conf

RUN a2enconf cbp-mpm-event \
    && a2dissite 000-default.conf 2>/dev/null || true \
    && a2ensite 000-staff.conf \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

WORKDIR /var/www/staff
COPY docker/entrypoint.sh /usr/local/bin/staff-entrypoint.sh
RUN chmod +x /usr/local/bin/staff-entrypoint.sh

RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update \
    && apt-get install -y --no-install-recommends supervisor \
    && mkdir -p /var/log/supervisor \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/staff-entrypoint.sh"]
CMD ["apache2-foreground"]

FROM runtime AS prod
COPY docker/php/staff-prod.ini /usr/local/etc/php/conf.d/z-staff-prod.ini
COPY . /var/www/staff
RUN chown -R www-data:www-data \
    /var/www/staff/modules/apm/storage \
    /var/www/staff/modules/apm/bootstrap/cache \
    /var/www/staff/modules/finance/backend/storage \
    /var/www/staff/modules/finance/backend/bootstrap/cache \
    /var/www/staff/modules/helpdesk/backend/storage \
    /var/www/staff/modules/helpdesk/backend/bootstrap/cache \
    /var/www/staff/modules/staff-portal/backend/storage \
    /var/www/staff/modules/staff-portal/backend/bootstrap/cache \
    /var/www/staff/modules/risk-register/backend/storage \
    /var/www/staff/modules/risk-register/backend/bootstrap/cache \
    2>/dev/null || true
```

Provide a small `apache2-foreground` helper if the FPM image lacks it — either:

```dockerfile
RUN printf '%s\n' '#!/bin/bash' 'set -e' \
  'rm -f /var/run/apache2/apache2.pid' \
  'exec apachectl -D FOREGROUND' \
  > /usr/local/bin/apache2-foreground \
  && chmod +x /usr/local/bin/apache2-foreground
```

or copy from the official apache image pattern. Place this RUN **before** ENTRYPOINT.

- [ ] **Step 2: Commit**

```bash
git add docker/Dockerfile
git commit -m "Rebuild Docker web image on PHP-FPM plus Apache event."
```

---

### Task 4: Entrypoint — start php-fpm before Apache

**Files:**
- Modify: `docker/entrypoint.sh`

**Interfaces:**
- When `$1` is `apache2-foreground` / `apachectl`: start `php-fpm`, wait for socket, then exec Apache
- When `$1` is `supervisord`: do **not** start Apache; optionally skip php-fpm (workers use CLI PHP via artisan)

- [ ] **Step 1: Add FPM start helper before `exec "$@"`**

After Redis wait, before configtest:

```bash
start_php_fpm_if_needed() {
  case "${1:-}" in
    apache2-foreground|apachectl|apache2)
      ;;
    *)
      return 0
      ;;
  esac
  mkdir -p /var/run/php
  chown www-data:www-data /var/run/php 2>/dev/null || true
  if ! pgrep -x php-fpm >/dev/null 2>&1; then
    echo "staff-entrypoint: starting php-fpm..."
    php-fpm --daemonize
  fi
  local i
  for i in $(seq 1 50); do
    if [[ -S /var/run/php/php-fpm.sock ]]; then
      echo "staff-entrypoint: php-fpm socket ready"
      return 0
    fi
    sleep 0.2
  done
  echo "staff-entrypoint: error — php-fpm socket missing" >&2
  return 1
}

start_php_fpm_if_needed "${1:-}"
```

Keep existing Laravel writable fixes and Redis wait. Keep `apache2ctl configtest` only for Apache CMDs.

- [ ] **Step 2: Commit**

```bash
git add docker/entrypoint.sh
git commit -m "Start php-fpm from Docker entrypoint before Apache."
```

---

### Task 5: Compose Redis + worker resource hints + prod overlay

**Files:**
- Modify: `docker-compose.yml`
- Modify: `docker-compose.prod.yml`
- Modify: `docker/compose.env.example` (optional `REDIS_MAXMEMORY=2gb`)

- [ ] **Step 1: Redis command in `docker-compose.yml`**

```yaml
  redis:
    image: redis:7-alpine
    command:
      [
        "redis-server",
        "--maxmemory",
        "${REDIS_MAXMEMORY:-2gb}",
        "--maxmemory-policy",
        "allkeys-lru",
      ]
    # keep ports, volumes, healthcheck
```

- [ ] **Step 2: Soft limits on workers (Compose v2 compatible)**

Under `workers:` add:

```yaml
    mem_limit: ${WORKERS_MEM_LIMIT:-6g}
    cpus: ${WORKERS_CPUS:-4}
```

(Document that these are optional caps so queues do not starve FPM on a 32 GB host.)

- [ ] **Step 3: `docker-compose.prod.yml`**

Ensure `web` / `workers` `build.target: prod` (already). No bind mounts (already). No extra change required if Dockerfile prod stage copies `staff-prod.ini`.

- [ ] **Step 4: Commit**

```bash
git add docker-compose.yml docker-compose.prod.yml docker/compose.env.example
git commit -m "Tune Compose Redis memory and cap worker resources."
```

---

### Task 6: Docs + rebuild smoke

**Files:**
- Modify: `docker/README.md`
- Modify: `docs/superpowers/specs/2026-10-02-docker-php-fpm-tuning-design.md` — Status → Implemented after verify
- Optional one line in root `README.md` Docker blurb

- [ ] **Step 1: Document in `docker/README.md`**

Add a short **Performance (8 CPU / 32 GB)** section:

- Image uses Apache event + PHP-FPM (not mod_php).
- Rebuild: `docker compose --env-file docker/.env up -d --build`
- Prod: `-f docker-compose.yml -f docker-compose.prod.yml`
- After deploy, per module backend: `php artisan config:cache && php artisan route:cache`
- Prefer Redis for `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` inside Compose (`REDIS_HOST=redis`)
- Verify: `docker compose exec web bash -lc 'apachectl -V | grep MPM; pgrep -a php-fpm; ls -l /var/run/php/php-fpm.sock'`

- [ ] **Step 2: Local/CI smoke (where Docker available)**

```bash
docker compose --env-file docker/.env build web
docker compose --env-file docker/.env up -d web redis
docker compose exec web bash -lc 'apachectl -V | head -20; test -S /var/run/php/php-fpm.sock && echo FPM_OK'
curl -sI "http://localhost:${APP_PORT:-8088}/staff/" | head -5
```

Expected: MPM event (or event in compiled-in modules), `FPM_OK`, HTTP 200/302/301 (not 502).

- [ ] **Step 3: Commit**

```bash
git add docker/README.md docs/superpowers/specs/2026-10-02-docker-php-fpm-tuning-design.md README.md
git commit -m "Document Docker PHP-FPM performance rebuild steps."
```

---

## Spec coverage

| Spec item | Task |
|-----------|------|
| FPM + event MPM | 2, 3, 4 |
| Pool sizes ~40 | 1 |
| OPcache 512 / prod validate off | 1, 3 |
| Redis maxmemory | 5 |
| Worker caps | 5 |
| Preserve /staff | 2 |
| Docs + Laravel cache notes | 6 |
| MySQL external default | unchanged (docs only in 6 if needed) |

## Placeholder / consistency review

- Socket path consistent: `/var/run/php/php-fpm.sock` in www.conf, vhost, entrypoint.
- Prod ini filename `z-staff-prod.ini` loads after `staff.ini`.
- Workers CMD remains supervisord (no FPM required for artisan CLI; FPM not started).
