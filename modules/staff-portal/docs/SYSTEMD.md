# Staff Portal — systemd (boot + auto-restart)

Staff Portal is served over **Apache** (`/staff/staff-portal/` SPA + `/staff/staff-portal/backend/` API). These units keep **background workers** running.

| Unit | Purpose |
|------|---------|
| `staff-portal.target` | Enable the whole stack on boot |
| `staff-portal-queue.service` | `php artisan queue:work` (`QUEUE_CONNECTION=database`, `sp_*` tables) |
| `staff-portal-scheduler.timer` | Runs `php artisan schedule:run` every minute |
| `staff-portal-health.timer` | Optional `curl` to `/up` every 5 minutes |

## Install (Linux production)

**Automatic:** configure `staff-portal/setup.env` and run `./setup-production.sh` (use `sudo` so systemd can install).

**Manual:**

```bash
cd /var/www/staff/staff-portal/deploy/systemd
sudo chmod +x install.sh ../bin/*.sh
sudo ./install.sh
```

Config: `/etc/staff-portal/staff-portal.env`

## Operations

```bash
systemctl status staff-portal.target
journalctl -u staff-portal-queue.service -f
sudo systemctl restart staff-portal-queue.service
```

After code deploy:

```bash
cd /var/www/staff/staff-portal && ./setup-production.sh
```

## Optional Redis

See `staff-portal/docker/` — same optional Redis sidecar pattern as Helpdesk.
