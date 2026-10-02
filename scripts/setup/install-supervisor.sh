#!/usr/bin/env bash
# Install Supervisor programs for all CBP Laravel modules (queue + scheduler).
# Env: WEB_ROOT, PHP_BIN, SUPERVISOR_USER, INSTALL_SUPERVISOR=true|false|auto
#      CBP_SUPERVISOR_DRY_RUN=1 → write confs under /tmp (no /etc, no package install)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAFF_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
# shellcheck source=systemd-cleanup.sh
source "$SCRIPT_DIR/systemd-cleanup.sh"

TMPL="$SCRIPT_DIR/supervisor/program.conf.tmpl"
[[ -f "$TMPL" ]] || { echo "error: missing template $TMPL" >&2; exit 1; }

INSTALL_SUPERVISOR="${INSTALL_SUPERVISOR:-auto}"
case "$INSTALL_SUPERVISOR" in
  false|0|no)
    echo "Skipping Supervisor (INSTALL_SUPERVISOR=$INSTALL_SUPERVISOR)."
    exit 0
    ;;
  auto)
    if [[ "$(uname -s)" != "Linux" ]]; then
      echo "Skipping Supervisor (auto: not Linux)."
      exit 0
    fi
    ;;
  true|1|yes) ;;
  *)
    echo "Unknown INSTALL_SUPERVISOR=$INSTALL_SUPERVISOR (use auto|true|false)." >&2
    exit 1
    ;;
esac

WEB_ROOT="${WEB_ROOT:-$(basename "$STAFF_ROOT")}"
# Supervisor program names: alphanumeric + hyphen
SLUG="$(printf '%s' "$WEB_ROOT" | tr -c 'A-Za-z0-9_-' '-' | sed 's/-\+/-/g; s/^-//; s/-$//')"
[[ -n "$SLUG" ]] || SLUG="staff"

PHP_BIN="${PHP_BIN:-}"
if [[ -z "$PHP_BIN" || ! -x "$PHP_BIN" ]]; then
  PHP_BIN="$(command -v php || true)"
fi
[[ -n "$PHP_BIN" ]] || { echo "error: PHP binary not found (set PHP_BIN)." >&2; exit 1; }

SUPERVISOR_USER="${SUPERVISOR_USER:-www-data}"
DRY_RUN="${CBP_SUPERVISOR_DRY_RUN:-0}"

if [[ "$DRY_RUN" == "1" ]]; then
  CONF_DIR="${CBP_SUPERVISOR_CONF_DIR:-/tmp/cbp-supervisor-$$}"
  echo "==> Supervisor dry-run (CONF_DIR=$CONF_DIR)"
else
  CONF_DIR="${CBP_SUPERVISOR_CONF_DIR:-/etc/supervisor/conf.d}"
fi

retire_cbp_systemd() {
  [[ "$(uname -s)" == "Linux" ]] || return 0
  command -v systemctl >/dev/null 2>&1 || return 0
  local _retire=(
    staff-portal.target staff-portal-queue.service staff-portal-scheduler.service
    staff-portal-scheduler.timer staff-portal-health.service staff-portal-health.timer
    helpdesk.target helpdesk-queue.service helpdesk-scheduler.service
    helpdesk-scheduler.timer helpdesk-health.service helpdesk-health.timer
    laravel-queue-apm.service laravel-scheduler.service laravel-queue-worker.service
    laravel-queue-cleanup.service laravel12-queue-apm.service
    "laravel-queue-apm-${SLUG}.service" "laravel-scheduler-${SLUG}.service"
  )
  local local_f
  for local_f in \
    /etc/systemd/system/staff-portal-*-"${SLUG}".service \
    /etc/systemd/system/staff-portal-*-"${SLUG}".timer \
    /etc/systemd/system/staff-portal-"${SLUG}".target \
    /etc/systemd/system/helpdesk-*-"${SLUG}".service \
    /etc/systemd/system/helpdesk-*-"${SLUG}".timer \
    /etc/systemd/system/helpdesk-"${SLUG}".target \
    /etc/systemd/system/laravel-*-"${SLUG}".service
  do
    [[ -e "$local_f" ]] || continue
    _retire+=("$(basename "$local_f")")
  done
  echo "==> Retiring CBP systemd units (avoid double workers)"
  systemd_retire_units "${_retire[@]}" || true
}

ensure_supervisor_pkg() {
  if command -v supervisorctl >/dev/null 2>&1; then
    return 0
  fi
  echo "supervisorctl not found — attempting to install supervisor…"
  if [[ "$(id -u)" -eq 0 ]]; then
    if command -v apt-get >/dev/null 2>&1; then
      apt-get install -y supervisor
    elif command -v dnf >/dev/null 2>&1; then
      dnf install -y supervisor
    elif command -v yum >/dev/null 2>&1; then
      yum install -y supervisor
    else
      echo "error: install the supervisor package, then re-run." >&2
      exit 1
    fi
  elif command -v sudo >/dev/null 2>&1; then
    if command -v apt-get >/dev/null 2>&1; then
      sudo apt-get install -y supervisor
    elif command -v dnf >/dev/null 2>&1; then
      sudo dnf install -y supervisor
    elif command -v yum >/dev/null 2>&1; then
      sudo yum install -y supervisor
    else
      echo "error: install the supervisor package, then re-run." >&2
      exit 1
    fi
  else
    echo "error: supervisor not installed and no sudo. Install it, then re-run." >&2
    exit 1
  fi
  command -v supervisorctl >/dev/null 2>&1 || {
    echo "error: supervisorctl still missing after install." >&2
    exit 1
  }
}

# Start supervisord so /var/run/supervisor.sock exists before supervisorctl.
ensure_supervisord_running() {
  local sock sock_candidates=(
    /var/run/supervisor.sock
    /var/run/supervisord.sock
    /run/supervisor.sock
    /run/supervisord.sock
  )
  local s found=0
  for s in "${sock_candidates[@]}"; do
    if [[ -S "$s" ]]; then
      found=1
      break
    fi
  done
  if [[ "$found" -eq 1 ]]; then
    return 0
  fi

  echo "==> Starting supervisord (socket missing)"
  run_svc() {
    if [[ "$(id -u)" -eq 0 ]]; then
      "$@"
    elif command -v sudo >/dev/null 2>&1; then
      sudo "$@"
    else
      "$@"
    fi
  }

  if command -v systemctl >/dev/null 2>&1; then
    run_svc systemctl enable supervisor 2>/dev/null || run_svc systemctl enable supervisord 2>/dev/null || true
    run_svc systemctl start supervisor 2>/dev/null \
      || run_svc systemctl start supervisord 2>/dev/null \
      || true
  fi
  if command -v service >/dev/null 2>&1; then
    run_svc service supervisor start 2>/dev/null \
      || run_svc service supervisord start 2>/dev/null \
      || true
  fi
  # Last resort: start daemon directly (Debian package layout).
  if ! [[ -S /var/run/supervisor.sock || -S /var/run/supervisord.sock || -S /run/supervisor.sock ]]; then
    if [[ -f /etc/supervisor/supervisord.conf ]]; then
      run_svc supervisord -c /etc/supervisor/supervisord.conf 2>/dev/null || true
    elif [[ -f /etc/supervisord.conf ]]; then
      run_svc supervisord -c /etc/supervisord.conf 2>/dev/null || true
    else
      run_svc supervisord 2>/dev/null || true
    fi
  fi

  local i
  for i in 1 2 3 4 5 6 7 8 9 10; do
    for s in "${sock_candidates[@]}"; do
      if [[ -S "$s" ]]; then
        echo "    supervisord ready ($s)"
        return 0
      fi
    done
    sleep 0.5
  done
  echo "warn: supervisord socket still missing — confs written; start with: sudo systemctl start supervisor" >&2
  return 1
}

supervisorctl_safe() {
  if [[ "$(id -u)" -eq 0 ]]; then
    supervisorctl "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo supervisorctl "$@"
  else
    supervisorctl "$@"
  fi
}

render_program() {
  local name="$1" command="$2" directory="$3" logfile="$4" numprocs="$5" stopwait="$6"
  local out="$CONF_DIR/${name}.conf"
  mkdir -p "$CONF_DIR" "$(dirname "$logfile")" 2>/dev/null || mkdir -p "$CONF_DIR"
  # Escape & \ for sed replacement safety
  local esc_cmd esc_dir esc_log
  esc_cmd="$(printf '%s' "$command" | sed 's/[&\\]/\\&/g')"
  esc_dir="$(printf '%s' "$directory" | sed 's/[&\\]/\\&/g')"
  esc_log="$(printf '%s' "$logfile" | sed 's/[&\\]/\\&/g')"
  sed -e "s|__NAME__|${name}|g" \
      -e "s|__COMMAND__|${esc_cmd}|g" \
      -e "s|__DIRECTORY__|${esc_dir}|g" \
      -e "s|__USER__|${SUPERVISOR_USER}|g" \
      -e "s|__NUMPROCS__|${numprocs}|g" \
      -e "s|__LOGFILE__|${esc_log}|g" \
      -e "s|__STOPWAIT__|${stopwait}|g" \
      "$TMPL" > "$out"
  echo "    wrote $out"
}

write_app_programs() {
  local app="$1" rel="$2" numprocs="$3"
  local app_abs="$STAFF_ROOT/$rel"
  if [[ ! -f "$app_abs/artisan" ]]; then
    echo "    skip $app (no artisan at $app_abs)"
    return 0
  fi
  app_abs="$(cd "$app_abs" && pwd)"
  mkdir -p "$app_abs/storage/logs" 2>/dev/null || true

  local qname="cbp-${SLUG}-${app}-queue"
  local sname="cbp-${SLUG}-${app}-scheduler"
  local qcmd="${PHP_BIN} artisan queue:work --sleep=3 --tries=3 --max-time=3600"
  local scmd="${PHP_BIN} artisan schedule:work"

  render_program "$qname" "$qcmd" "$app_abs" "$app_abs/storage/logs/supervisor-queue.log" "$numprocs" "3600"
  render_program "$sname" "$scmd" "$app_abs" "$app_abs/storage/logs/supervisor-scheduler.log" "1" "60"
}

if [[ "$DRY_RUN" != "1" ]]; then
  if [[ "$(id -u)" -ne 0 ]] && [[ ! -w "$(dirname "$CONF_DIR")" ]]; then
    echo "Re-running with sudo for Supervisor install…"
    exec sudo env \
      STAFF_ROOT="$STAFF_ROOT" \
      WEB_ROOT="$WEB_ROOT" \
      PHP_BIN="$PHP_BIN" \
      SUPERVISOR_USER="$SUPERVISOR_USER" \
      INSTALL_SUPERVISOR=true \
      CBP_SUPERVISOR_CONF_DIR="$CONF_DIR" \
      bash "$0"
  fi
  retire_cbp_systemd
  ensure_supervisor_pkg
  ensure_supervisord_running || true
fi

echo "==> Supervisor programs (slug=$SLUG php=$PHP_BIN user=$SUPERVISOR_USER)"
mkdir -p "$CONF_DIR"

# app|relative_dir|queue_numprocs
write_app_programs "staff-portal" "modules/staff-portal/backend" "1"
write_app_programs "helpdesk" "modules/helpdesk/backend" "1"
write_app_programs "finance" "modules/finance/backend" "1"
write_app_programs "risk-register" "modules/risk-register/backend" "1"
write_app_programs "apm" "modules/apm" "2"

if [[ "$DRY_RUN" == "1" ]]; then
  echo "==> Dry-run complete. Confs in $CONF_DIR"
  ls -1 "$CONF_DIR" || true
  exit 0
fi

if command -v supervisorctl >/dev/null 2>&1; then
  ensure_supervisord_running || true
  if supervisorctl_safe reread && supervisorctl_safe update; then
    echo "==> supervisorctl status (cbp-${SLUG}-*):"
    supervisorctl_safe status "cbp-${SLUG}-*" 2>/dev/null || supervisorctl_safe status || true
  else
    echo "warn: supervisorctl could not talk to supervisord — confs are in $CONF_DIR" >&2
    echo "    start daemon: sudo systemctl start supervisor && sudo supervisorctl update" >&2
  fi
else
  echo "warn: supervisorctl missing; confs written to $CONF_DIR" >&2
fi

echo "Done. Manage with: sudo supervisorctl status"
