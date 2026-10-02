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

```bash
sudo supervisorctl status
sudo supervisorctl restart cbp-staff-:
sudo supervisorctl tail -f cbp-staff-apm-queue
```

Reinstall / refresh after path or PHP changes:

```bash
sudo WEB_ROOT=staff PHP_BIN="$(command -v php)" INSTALL_SUPERVISOR=true \
  /path/to/staff/scripts/setup/install-supervisor.sh
```

## Docker

Use Compose workers instead of host Supervisor:

```bash
docker compose --env-file docker/.env --profile workers up -d
```

## Legacy systemd

Systemd unit templates were removed from this repo. Choosing Supervisor in setup **retires** leftover CBP systemd units on the host. See `docs/SETUP.md`.
