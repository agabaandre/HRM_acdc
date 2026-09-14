#!/usr/bin/env bash
# Install APM queue + scheduler systemd units with modules/apm WorkingDirectory.
# Retires legacy/duplicate APM units first to avoid clashing workers.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck source=systemd-cleanup.sh
source "$ROOT/scripts/setup/systemd-cleanup.sh"

APM_ROOT="${APM_ROOT:-$ROOT/modules/apm}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
SERVICE_USER="${APM_SERVICE_USER:-www-data}"
SERVICE_GROUP="${APM_SERVICE_GROUP:-$SERVICE_USER}"
SYSTEMD_DIR="${SYSTEMD_DIR:-/etc/systemd/system}"

if [[ "$(uname -s)" != "Linux" ]] || ! command -v systemctl >/dev/null 2>&1; then
  echo "Skipping APM systemd (not Linux or systemctl missing)."
  exit 0
fi

if [[ ! -x "$PHP_BIN" ]]; then
  PHP_BIN="$(command -v php || true)"
fi
[[ -n "$PHP_BIN" && -x "$PHP_BIN" ]] || { echo "error: php not found" >&2; exit 1; }
[[ -d "$APM_ROOT" ]] || { echo "error: missing $APM_ROOT" >&2; exit 1; }

write_unit() {
  local name="$1" desc="$2" exec="$3" mem="${4:-512M}"
  local dest="$SYSTEMD_DIR/$name"
  cat >"$dest" <<EOF
[Unit]
Description=${desc}
After=network.target

[Service]
Type=simple
User=${SERVICE_USER}
Group=${SERVICE_GROUP}
WorkingDirectory=${APM_ROOT}
ExecStart=${PHP_BIN} ${exec}
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
SyslogIdentifier=${name%.service}
Environment=APP_ENV=production
LimitNOFILE=65536
MemoryMax=${mem}

[Install]
WantedBy=multi-user.target
EOF
  echo "==> wrote $dest"
}

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Re-running with sudo for systemd install…"
  exec sudo env \
    APM_ROOT="$APM_ROOT" \
    PHP_BIN="$PHP_BIN" \
    APM_SERVICE_USER="$SERVICE_USER" \
    APM_SERVICE_GROUP="$SERVICE_GROUP" \
    SYSTEMD_DIR="$SYSTEMD_DIR" \
    bash "$0"
fi

echo "==> Retiring legacy / duplicate APM systemd units"
# Current targets (stop before rewrite) + obsolete aliases that would double-run queues.
systemd_retire_units \
  laravel-queue-apm.service \
  laravel-scheduler.service \
  laravel-queue-worker.service \
  laravel-queue-cleanup.service \
  laravel12-queue-apm.service

write_unit "laravel-queue-apm.service" \
  "Laravel Queue Worker for Africa CDC APM" \
  "artisan queue:work --sleep=3 --tries=3 --max-time=3600" \
  "512M"

write_unit "laravel-scheduler.service" \
  "Laravel Scheduler for Africa CDC APM" \
  "artisan schedule:work" \
  "256M"

systemctl daemon-reload
systemctl enable --now laravel-queue-apm.service laravel-scheduler.service
systemctl --no-pager --full status laravel-queue-apm.service laravel-scheduler.service || true
echo "==> APM systemd units enabled (WorkingDirectory=$APM_ROOT)"
