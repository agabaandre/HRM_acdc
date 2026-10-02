# Docker PHP-FPM performance tuning (8 CPU / ~32 GB)

**Date:** 2026-10-02  
**Status:** Implemented  
**Approach:** Apache event MPM + PHP-FPM in the same `web` image (Approach A)  
**Reference:** [PHP_laravel_Codeigniter_wordpress_server_optimisation_enterprise](https://github.com/agabaandre/PHP_laravel_Codeigniter_wordpress_server_optimisation_enterprise) (tier **L / 32 GB**, adjusted for **8 CPUs**)

## Goals

1. Make the Compose **Docker** runtime feel production-capable on a host with **8× ~3.2 GHz CPUs and ~31.3 GB RAM**.
2. Replace **mod_php** (`php:8.2-apache`) with **Apache event MPM + PHP-FPM** (proxy_fcgi), matching the enterprise guide’s architecture.
3. Ship tunable PHP / OPcache / Apache / Redis settings sized for this hardware.
4. Keep **prod bake** (`docker-compose.prod.yml`) fast: OPcache without timestamp revalidation; avoid slow bind-mount behaviour on prod.
5. Leave MySQL on **host / external** by default; document optional bundled-db knobs only.

## Non-goals

- Host-native Apache/PHP-FPM install from the guide (option 2 — deferred).
- Separate `php-fpm` Compose service (Approach B).
- Full observability stack (SigNoz / Prometheus / Grafana).
- Application PHP/Vue code changes.
- Changing module URL layout or SSO.

## Problem (current Docker)

| Area | Today | Impact |
|------|--------|--------|
| SAPI | `php:8.2-apache` (mod_php) | Poor concurrency vs FPM + event MPM |
| OPcache | 128 MB, `validate_timestamps=1` | Constant revalidation; small cache for five Laravel apps |
| Apache | Default prefork/mpm from image | Not sized for 8 cores |
| Redis | Untuned alpine defaults | No maxmemory / eviction policy |
| Memory limit | 512M per request | Inflates FPM child RAM; fewer workers fit |

## Hardware sizing (this deploy)

| Resource | Value | Guide mapping |
|----------|-------|----------------|
| CPUs | 8 × ~3.2 GHz | Between tier L (4 CPU) and XL (8 CPU) |
| RAM | ~31.3 GB | Closest to **`--tier 32`** |

**Chosen knobs (hybrid):** tier **32** memory for OPcache / MySQL docs; FPM children between L (28) and XL (56) → **~40**.

## Architecture

```
Browser
  → host :APP_PORT → web container
       Apache 2.4 (mpm_event) — static / Alias /staff
       proxy_fcgi → php-fpm (unix socket or 127.0.0.1:9000)
       → Laravel modules under /var/www/staff/modules/*

redis container (maxmemory + allkeys-lru)

workers container (optional profile) — Supervisor queues/schedulers
  (CPU/memory limits so they do not starve FPM)

MySQL — host.docker.internal / external (default)
```

Entrypoint starts **php-fpm** (background) then **apache2-foreground** when CMD is Apache. Workers profile still runs `supervisord` only (no Apache).

## Target settings

### PHP-FPM (`docker/php/www.conf`)

| Setting | Value |
|---------|--------|
| `pm` | `dynamic` |
| `pm.max_children` | `40` |
| `pm.start_servers` | `6` |
| `pm.min_spare_servers` | `6` |
| `pm.max_spare_servers` | `12` |
| `pm.max_requests` | `500` |
| `request_terminate_timeout` | `120s` |

Listen: unix socket under `/var/run/php/php-fpm.sock` (www-data).

### PHP / OPcache (`docker/php/staff.ini` + prod overlay)

| Setting | Dev (bind-mount) | Prod bake |
|---------|------------------|-----------|
| `memory_limit` | `256M` | `256M` |
| `upload_max_filesize` / `post_max_size` | `128M` | `128M` |
| `opcache.memory_consumption` | `512` | `512` |
| `opcache.interned_strings_buffer` | `64` | `64` |
| `opcache.max_accelerated_files` | `100000` | `100000` |
| `opcache.validate_timestamps` | `1` | `0` |
| `realpath_cache_size` | `4096K` | `4096K` |

### Apache (`docker/apache/mpm_event.conf` + site)

| Setting | Value |
|---------|--------|
| MPM | `event` (disable prefork/worker) |
| `ServerLimit` | `12` |
| `MaxRequestWorkers` | `250` |
| `ThreadsPerChild` | `25` |
| `KeepAlive` | `On` |
| `KeepAliveTimeout` | `5` |
| PHP handler | `SetHandler` / `FilesMatch` → `proxy:unix:…\|fcgi://localhost/` |

Preserve existing `Alias /staff` and `AllowOverride All` behaviour from `000-staff.conf`.

### Redis (`docker-compose.yml`)

| Setting | Value |
|---------|--------|
| `maxmemory` | `2gb` (env-overridable) |
| `maxmemory-policy` | `allkeys-lru` |
| Persistence | keep AOF/RDB light or default alpine; document trade-offs |

### Workers

Optional Compose `deploy.resources` / `mem_limit` / `cpus` so queue containers cannot take the whole host (e.g. ~2–4 CPUs / 4–6 GB combined — exact values in plan).

### MySQL (documentation only unless `bundled-db`)

If using Compose MySQL profile: suggest `innodb_buffer_pool_size≈8G`, `max_connections≈200` via command/config mount. Default remains **host MySQL**.

## Files to add / change

| Path | Role |
|------|------|
| `docker/Dockerfile` | Base on FPM-capable image; install Apache + enable proxy_fcgi + event; copy PHP/Apache confs |
| `docker/entrypoint.sh` | Start php-fpm before Apache; skip FPM for workers CMD |
| `docker/php/www.conf` | FPM pool |
| `docker/php/staff.ini` | Shared PHP/OPcache (dev-friendly validate) |
| `docker/php/staff-prod.ini` | Overlay: `validate_timestamps=0` (prod target only) |
| `docker/apache/mpm_event.conf` | Event MPM knobs |
| `docker/apache/000-staff.conf` | Add FPM proxy handler for `\.php$` |
| `docker-compose.yml` | Redis command args; optional resource hints |
| `docker-compose.prod.yml` | Ensure prod PHP ini overlay; no bind-mount |
| `docker/README.md` / `docs/SETUP.md` | Rebuild + Laravel `config:cache` / Redis drivers notes |

## Laravel / ops (docs, not auto-forced)

On production after deploy:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Prefer `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` with `REDIS_HOST=redis` inside Compose.

After PHP/conf image changes: rebuild `web` (and `workers`).

## Risks

| Risk | Mitigation |
|------|------------|
| FPM socket not ready when Apache starts | Entrypoint waits for socket file |
| Mac bind-mount + FPM still slow | Document prod bake / named volume; tuning targets Linux server |
| Too many FPM children → OOM | Cap at 40 × 256M ≈ 10 GB headroom for Apache/Redis/OS/MySQL client |
| Breaking existing local Compose | Keep same ports/Alias; smoke `curl /staff/` + one API `/up` |
| mod_php remnants | Explicitly disable `php` module if present |

## Success criteria

- `web` container runs **php-fpm** + **mpm_event** (verify via `apachectl -V` / `ps`).
- Concurrent Laravel requests no longer serialize behind a tiny mod_php worker model.
- Prod OPcache does not revalidate timestamps on every request.
- Redis enforces maxmemory with LRU.
- Documented rebuild path for the 8 CPU / 32 GB host; no regression to `/staff/` URL shape.

## Decisions

| Topic | Choice |
|-------|--------|
| Runtime | Docker full stack (user option **1**) |
| SAPI | PHP-FPM + Apache event in one `web` image |
| Tier | Hybrid **32 GB** + **8 CPU** FPM children (~40) |
| MySQL | Stay external/host by default |
| Monitoring extras | Out of scope |
