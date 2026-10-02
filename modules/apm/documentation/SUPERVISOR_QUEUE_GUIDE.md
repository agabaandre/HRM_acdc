# Supervisor queue and scheduler (CBP)

Host installs manage Laravel **queue workers** and **schedulers** with [Supervisor](http://supervisord.org/), installed by root `./setup.sh` or `scripts/setup/install-supervisor.sh`.

## Programs

For site slug `WEB_ROOT` (e.g. `staff`):

| Program | Command |
|---------|---------|
| `cbp-{slug}-{app}-queue` | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` |
| `cbp-{slug}-{app}-scheduler` | `php artisan schedule:work` |

Apps: `staff-portal`, `helpdesk`, `finance`, `risk-register`, `apm` (APM queue uses `numprocs=2`).

Confs live in `/etc/supervisor/conf.d/`. Logs: `{app}/storage/logs/supervisor-queue.log` and `supervisor-scheduler.log`.

## Operator commands

Enable the daemon first (required once per host — without it `supervisorctl` fails with `supervisor.sock` missing):

```bash
sudo apt-get install -y supervisor
sudo systemctl enable --now supervisor
sudo systemctl status supervisor --no-pager
```

Then:

```bash
sudo supervisorctl status
sudo supervisorctl restart cbp-staff-:
sudo supervisorctl tail -f cbp-staff-apm-queue
```

If a program is **FATAL** (“Exited too quickly”), check the app log (not only `supervisorctl`):

```bash
sudo tail -n 80 /path/to/staff/modules/helpdesk/backend/storage/logs/supervisor-queue.log
sudo tail -n 80 /path/to/staff/modules/finance/backend/storage/logs/supervisor-queue.log
# Typical causes: storage/logs not writable by www-data, bad .env DB, missing vendor/
sudo chown -R www-data:www-data modules/helpdesk/backend/storage modules/finance/backend/storage
sudo chmod -R ug+rwX modules/helpdesk/backend/storage modules/finance/backend/storage
```

Missing `vendor/autoload.php` (finance API workers) — **not permissions**.
`vendor/` is gitignored; a `git pull` alone will not create it:

```bash
cd /path/to/staff
./scripts/setup/ensure-composer-vendors.sh
# or only finance API:
cd modules/finance/backend && composer install --no-dev --optimize-autoloader --no-interaction
# or re-run module setup: cd modules/finance && ./setup-production.sh --skip-build
sudo supervisorctl restart cbp-staff-finance-queue cbp-staff-finance-scheduler
```

`install-supervisor.sh` now runs `composer install` automatically when `vendor/` is missing.

Reinstall / refresh after path or PHP changes:

```bash
sudo WEB_ROOT=staff PHP_BIN="$(command -v php)" INSTALL_SUPERVISOR=true \
  /path/to/staff/scripts/setup/install-supervisor.sh
```

## Docker

When `./setup.sh` chooses **Docker Compose**, Composer and workers are container-native (`DEPLOY_MODE=docker`). Use Compose workers instead of host Supervisor:

```bash
docker compose --env-file docker/.env --profile workers up -d
```

## Legacy systemd

Systemd unit templates were removed from this repo. Choosing Supervisor in setup **retires** leftover CBP systemd units on the host. See `docs/SETUP.md`.
