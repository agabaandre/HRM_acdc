# Production deployment (Apache + PHP-FPM + MySQL 8)

> **Preferred production path:** edit `docker/.env` + `backend/.env` on the server, then run **[`./setup.sh`](../setup.sh)** (see **[README.md](../README.md#deploy-with-setupsh-recommended)**).  
> Template: [`docker/.env.example`](../docker/.env.example) and [`backend/.env.example`](../backend/.env.example).  
> Host Nginx: [`deploy/configs/nginx-notifications.africacdc.org.conf`](configs/nginx-notifications.africacdc.org.conf)  
> Host security headers snippet: [`deploy/configs/nginx-security-headers.conf`](configs/nginx-security-headers.conf) (anti-spoofing + CSP).  
> SSL: Certbot is already installed — `setup.sh` runs it, or use `sudo certbot --nginx -d notifications.africacdc.org --redirect`.

Configs in `deploy/configs/` are copied from the [enterprise optimisation guide](https://github.com/agabaandre/PHP_laravel_Codeigniter_wordpress_server_optimisation_enterprise/blob/main/docs/MANUAL-OPTIMIZATION-PHP82-MYSQL8.md) and adapted for **PHP 8.4** and this Laravel app.

Target capacity: **400+ Apache workers**, **56 PHP-FPM children**, **300 MySQL connections** — suitable for **1000+ concurrent HTTP requests** when combined with **Redis queues** for email (Graph/SMTP never blocks the request thread).

---

## Docker (local / without host Nginx)

```bash
cp docker/.env.example docker/.env
cp backend/.env.example backend/.env
chmod 600 docker/.env backend/.env
# Edit ADMIN_PASSWORD, JWT_SECRET, DB passwords, Exchange creds in both files

./setup.sh
# or: cd docker && docker compose up -d --build
```

| Service | Port | Role |
|---------|------|------|
| nginx | 8089 | API (Swagger only when `APP_ENV` ≠ production) |
| frontend | 3006 | Admin UI |
| mysql | 3309 | Host mapping (`MYSQL_HOST_PORT`); containers use `mysql:3306` on the compose network |
| queue | — | Async email (`SendEmailJob`) |
| redis | — | Queue, cache, sessions, rate limits |
| storage | host path | `${EMAIL_SERVER_DATA_PATH}/storage` → `/var/www/backend/storage` |

---

## Bare metal (Apache 2.4 + PHP 8.4 FPM)

Follow the [manual optimisation guide](https://github.com/agabaandre/PHP_laravel_Codeigniter_wordpress_server_optimisation_enterprise/blob/main/docs/MANUAL-OPTIMIZATION-PHP82-MYSQL8.md), substituting **8.4** for 8.2 where paths differ.

### 1. Copy configs

```bash
sudo cp deploy/configs/mysqld-production.cnf /etc/mysql/mysql.conf.d/99-email-server.cnf
sudo cp deploy/configs/php-production.ini /etc/php/8.4/fpm/conf.d/99-email-server.ini
sudo cp deploy/configs/php-fpm-www.conf /etc/php/8.4/fpm/pool.d/www.conf
sudo sed -i 's|127.0.0.1:9000|/run/php/php8.4-fpm.sock|g' /etc/php/8.4/fpm/pool.d/www.conf
sudo cp deploy/configs/apache-mpm.conf /etc/apache2/mods-available/mpm_event.conf
sudo cp deploy/configs/apache-vhost-laravel.conf /etc/apache2/sites-available/email-server.conf
```

Edit vhost `ServerName` and `DocumentRoot` to your deploy path.

### 2. Enable Apache modules

```bash
sudo a2dismod mpm_prefork 2>/dev/null || true
sudo a2enmod mpm_event ssl rewrite headers proxy proxy_fcgi setenvif http2 expires deflate
sudo a2enconf php8.4-fpm
sudo a2ensite email-server
sudo a2dissite 000-default
```

### 3. Redis (required)

Docker includes Redis automatically. For bare metal:

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
```

`.env` must use separate Redis databases:

```
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2
REDIS_QUEUE_CONNECTION=queue
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

Health check: `GET /api/v1/health` or `GET /api/health`

### 4. Laravel app

```bash
cd /var/www/email_server/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

Set in `.env`:

```
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

### 5. Queue workers (Supervisor)

```ini
[program:email-server-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/email_server/backend/artisan queue:work redis --queue=emails,default --sleep=1 --tries=3 --timeout=120
autostart=true
autorestart=true
numprocs=4
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/email-server/queue.log
```

```bash
sudo mkdir -p /var/log/email-server
sudo supervisorctl reread && sudo supervisorctl update
```

### 6. After code deploy

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
sudo systemctl reload php8.4-fpm   # OPcache validate_timestamps=0
```

### 7. SSL (Certbot — already installed on servers)

**Docker + host Nginx (preferred for `notifications.africacdc.org`):**

```bash
# HTTP site must be live first (see README §6)
sudo certbot --nginx \
  -d notifications.africacdc.org \
  --agree-tos \
  --redirect \
  -m andrewa@africacdc.org \
  --non-interactive

sudo certbot renew --dry-run
curl -I https://notifications.africacdc.org/api/v1/health
```

Certificates:

- `/etc/letsencrypt/live/notifications.africacdc.org/fullchain.pem`
- `/etc/letsencrypt/live/notifications.africacdc.org/privkey.pem`

**Bare-metal Apache:** use Certbot’s Apache plugin (`certbot --apache -d …`) per guide Step 13, then reload Apache.

---

## Security checklist

- [ ] `APP_DEBUG=false`, strong `APP_KEY` and `JWT_SECRET`
- [ ] `.env` not web-accessible (Apache/Nginx blocks it)
- [ ] `RUN_SEEDER=false` in production Docker
- [ ] TLS via Certbot for `notifications.africacdc.org`; renew timer verified (`certbot renew --dry-run`)
- [ ] UFW: allow 80/443 only
- [ ] Fail2ban on Nginx/Apache where applicable
- [ ] Integration IP allowlists where possible
- [ ] Rotate integration API keys periodically

---

## Email flow (async)

```
POST /api/v1/integrations/send  → 201/pending log  →  HTTP returns immediately
                              ↓
                    Redis queue (emails)
                              ↓
              queue:work → SendEmailJob → Graph/SMTP
                              ↓
              GET /api/v1/integrations/logs/{id}  →  sent | failed
```

This prevents Exchange Graph latency from hanging PHP-FPM workers under load.
