# Helpdesk — systemd (boot + auto-restart)

Helpdesk is served over **Apache** (`/staff/helpdesk/` SPA + `/staff/helpdesk/backend/` API). Apache must already be enabled on the server (`apache2` or `httpd`). These units keep **background workers** running and restart them if they crash.

| Unit | Purpose |
|------|---------|
| `helpdesk.target` | Enable the whole stack on boot |
| `helpdesk-queue.service` | `php artisan queue:work` (mail, notifications; `QUEUE_CONNECTION=database`) |
| `helpdesk-scheduler.timer` | Runs `php artisan schedule:run` every minute |
| `helpdesk-health.timer` | Optional `curl` to `/api/v1/health` every 5 minutes |

On **server reboot**, systemd starts `helpdesk.target` → queue worker + timers.  
On **process failure**, `helpdesk-queue.service` uses `Restart=always` (retries every 5s, burst limit 10 per 5 minutes).

Shutdown is normal: systemd stops the queue worker cleanly; nothing special is required.

---

## Install (Linux production)

**Automatic (recommended):** configure `helpdesk/setup.env` and run `./setup.sh` (use `sudo ./setup.sh` so systemd can be installed without a password prompt).

**Manual:**

1. Deploy the app (Apache vhost, `composer install`, `npm run build`, migrations).
2. Ensure Apache serves the health endpoint:

   ```bash
   curl -fsS http://127.0.0.1/staff/helpdesk/backend/api/v1/health
   ```

3. Install units (interactive prompts for paths):

   ```bash
   cd /var/www/staff/helpdesk/deploy/systemd
   sudo chmod +x install.sh ../bin/*.sh
   sudo ./install.sh
   ```

4. **Apache on boot** (if not already):

   ```bash
   # Debian/Ubuntu
   sudo systemctl enable apache2

   # RHEL/Alma/Rocky
   sudo systemctl enable httpd
   ```

5. Uncomment the matching `Wants=apache2.service` or `Wants=httpd.service` line in `helpdesk.target`, then:

   ```bash
   sudo systemctl daemon-reload
   sudo systemctl restart helpdesk.target
   ```

### Config file

`/etc/helpdesk/helpdesk.env` (created by `install.sh`):

```env
HELPDESK_ROOT=/var/www/staff/helpdesk/backend
HELPDESK_USER=www-data
HELPDESK_GROUP=www-data
PHP_BIN=/usr/bin/php
HELPDESK_HEALTH_URL=http://127.0.0.1/staff/helpdesk/backend/api/v1/health
```

Scripts live in `/opt/helpdesk/bin/` and source this file.

---

## Operations

```bash
# Overall stack
systemctl status helpdesk.target

# Queue worker logs
journalctl -u helpdesk-queue.service -f

# Restart worker after deploy
sudo systemctl restart helpdesk-queue.service

# Scheduler / health timers
systemctl list-timers 'helpdesk-*'
```

After code deploy:

```bash
cd /var/www/staff/helpdesk/backend && php artisan migrate --force
cd /var/www/staff/helpdesk/frontend && npm run build
sudo systemctl restart helpdesk-queue.service
```

---

## Optional: Redis (docker-compose)

If you run `helpdesk/docker/docker-compose.yml` for Redis, enable that stack separately (or add a `helpdesk-redis.service` that runs `docker compose up`). The default `.env` uses **database** queues and cache; Redis is optional.

---

## macOS / local dev

Homebrew Apache (`brew services start httpd`) is enough for HTTP. You do **not** need these units locally unless you are testing queue workers:

```bash
cd helpdesk/backend
php artisan queue:work database
```

---

## Files

| Path |
|------|
| `deploy/systemd/*.service`, `*.timer`, `helpdesk.target` |
| `deploy/systemd/install.sh` |
| `deploy/systemd/helpdesk.env.example` |
| `deploy/bin/helpdesk-*.sh` |
