#!/usr/bin/env bash
# Install Supervisor programs for all CBP Laravel modules (queue + scheduler).
# Env: WEB_ROOT, PHP_BIN, SUPERVISOR_USER, INSTALL_SUPERVISOR=true|false|auto
#      CBP_SUPERVISOR_DRY_RUN=1 → write confs under /tmp (no /etc, no package install)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAFF_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
export STAFF_ROOT
# shellcheck source=systemd-cleanup.sh
source "$SCRIPT_DIR/systemd-cleanup.sh"
# shellcheck source=docker-compose.sh
source "$SCRIPT_DIR/docker-compose.sh"

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
  local need_install=0
  if ! command -v supervisorctl >/dev/null 2>&1 || ! command -v supervisord >/dev/null 2>&1; then
    need_install=1
  fi
  if [[ "$need_install" -eq 0 ]]; then
    return 0
  fi
  echo "supervisor package incomplete — attempting to install…"
  if [[ "$(id -u)" -eq 0 ]]; then
    if command -v apt-get >/dev/null 2>&1; then
      apt-get update -qq || true
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
      sudo apt-get update -qq || true
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
  command -v supervisord >/dev/null 2>&1 || {
    echo "error: supervisord still missing after install." >&2
    exit 1
  }
}

run_svc() {
  if [[ "$(id -u)" -eq 0 ]]; then
    "$@"
  elif command -v sudo >/dev/null 2>&1; then
    sudo "$@"
  else
    "$@"
  fi
}

run_as_supervisor_user() {
  if [[ "$(id -u)" -eq 0 ]]; then
    if command -v runuser >/dev/null 2>&1; then
      runuser -u "$SUPERVISOR_USER" -- "$@"
      return $?
    fi
    if command -v su >/dev/null 2>&1; then
      su -s /bin/bash "$SUPERVISOR_USER" -c "$(printf '%q ' "$@")"
      return $?
    fi
  elif command -v sudo >/dev/null 2>&1; then
    sudo -u "$SUPERVISOR_USER" "$@"
    return $?
  fi
  "$@"
}

supervisor_sock_path() {
  local s
  for s in \
    /var/run/supervisor.sock \
    /run/supervisor.sock \
    /var/run/supervisord.sock \
    /run/supervisord.sock
  do
    if [[ -S "$s" ]]; then
      printf '%s\n' "$s"
      return 0
    fi
  done
  return 1
}

# Start supervisord so the unix socket exists before supervisorctl.
ensure_supervisord_running() {
  local sock
  if sock="$(supervisor_sock_path)"; then
    echo "    supervisord already running ($sock)"
    return 0
  fi

  echo "==> Enabling and starting supervisord (socket missing)"

  # Debian/Ubuntu unit is usually "supervisor"; RHEL often "supervisord".
  if command -v systemctl >/dev/null 2>&1; then
    run_svc systemctl unmask supervisor 2>/dev/null || true
    run_svc systemctl unmask supervisord 2>/dev/null || true
    run_svc systemctl daemon-reload 2>/dev/null || true
    run_svc systemctl enable --now supervisor 2>/dev/null \
      || run_svc systemctl enable --now supervisord 2>/dev/null \
      || true
    # Some hosts have the unit installed but inactive
    run_svc systemctl start supervisor 2>/dev/null \
      || run_svc systemctl start supervisord 2>/dev/null \
      || true
    run_svc systemctl restart supervisor 2>/dev/null \
      || run_svc systemctl restart supervisord 2>/dev/null \
      || true
  fi
  if command -v service >/dev/null 2>&1; then
    run_svc service supervisor start 2>/dev/null \
      || run_svc service supervisord start 2>/dev/null \
      || true
  fi

  # Direct start if systemd did not create the socket (conf present, daemon down).
  if ! supervisor_sock_path >/dev/null; then
    if [[ -f /etc/supervisor/supervisord.conf ]]; then
      run_svc supervisord -c /etc/supervisor/supervisord.conf 2>/dev/null || true
    elif [[ -f /etc/supervisord.conf ]]; then
      run_svc supervisord -c /etc/supervisord.conf 2>/dev/null || true
    else
      run_svc supervisord 2>/dev/null || true
    fi
  fi

  local i
  for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
    if sock="$(supervisor_sock_path)"; then
      echo "    supervisord ready ($sock)"
      return 0
    fi
    sleep 0.4
  done

  echo "error: supervisord is not running (no unix socket)." >&2
  echo "    Fix on this host:" >&2
  echo "      sudo apt-get install -y supervisor" >&2
  echo "      sudo systemctl enable --now supervisor" >&2
  echo "      sudo systemctl status supervisor --no-pager" >&2
  echo "      ls -l /var/run/supervisor.sock /run/supervisor.sock 2>/dev/null" >&2
  if command -v systemctl >/dev/null 2>&1; then
    run_svc systemctl status supervisor --no-pager -l 2>/dev/null \
      || run_svc systemctl status supervisord --no-pager -l 2>/dev/null \
      || true
  fi
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
  ensure_app_log_writable "$app_abs"

  # Fail fast with a clear message if artisan cannot boot as the worker user.
  if ! preflight_artisan "$app" "$app_abs"; then
    echo "    warn: $app artisan preflight failed — Supervisor may mark this app FATAL" >&2
  fi

  local qname="cbp-${SLUG}-${app}-queue"
  local sname="cbp-${SLUG}-${app}-scheduler"
  local qcmd="${PHP_BIN} artisan queue:work --sleep=3 --tries=3 --max-time=3600"
  local scmd="${PHP_BIN} artisan schedule:work"

  render_program "$qname" "$qcmd" "$app_abs" "$app_abs/storage/logs/supervisor-queue.log" "$numprocs" "3600"
  render_program "$sname" "$scmd" "$app_abs" "$app_abs/storage/logs/supervisor-scheduler.log" "1" "60"
}

# Ensure storage/logs exists and is writable by SUPERVISOR_USER (FATAL often = cannot open logfile).
ensure_app_log_writable() {
  local app_abs="$1"
  local log_dir="$app_abs/storage/logs"
  mkdir -p "$log_dir" 2>/dev/null || true
  touch "$log_dir/supervisor-queue.log" "$log_dir/supervisor-scheduler.log" 2>/dev/null || true
  # Non-interactive only — never block setup on a sudo password prompt.
  if [[ "$(id -u)" -eq 0 ]]; then
    chown -R "${SUPERVISOR_USER}:${SUPERVISOR_USER}" "$app_abs/storage" "$app_abs/bootstrap/cache" 2>/dev/null || true
    chmod -R ug+rwX "$app_abs/storage" "$app_abs/bootstrap/cache" 2>/dev/null || true
  elif command -v sudo >/dev/null 2>&1; then
    sudo -n chown -R "${SUPERVISOR_USER}:${SUPERVISOR_USER}" "$app_abs/storage" "$app_abs/bootstrap/cache" 2>/dev/null || true
    sudo -n chmod -R ug+rwX "$app_abs/storage" "$app_abs/bootstrap/cache" 2>/dev/null || true
  fi
  chmod -R a+rwX "$log_dir" 2>/dev/null || true
}

# vendor/ is gitignored — git pull alone leaves workers FATAL with
# "Failed opening required …/vendor/autoload.php" (not a permissions issue).
ensure_composer_vendor() {
  local app="$1" app_abs="$2"
  if [[ -f "$app_abs/vendor/autoload.php" ]]; then
    return 0
  fi
  if [[ ! -f "$app_abs/composer.json" ]]; then
    echo "    preflight $app FAILED: missing composer.json at $app_abs" >&2
    return 1
  fi
  if staff_use_docker_composer; then
    export STAFF_COMPOSER_VIA_DOCKER=1
    export PATH="$STAFF_ROOT/scripts/setup/bin:$PATH"
  elif ! command -v composer >/dev/null 2>&1; then
    echo "    preflight $app FAILED: missing vendor/autoload.php and composer is not installed" >&2
    echo "    Fix: cd $app_abs && composer install --no-dev --optimize-autoloader --no-interaction" >&2
    echo "    Or: DEPLOY_MODE=docker $STAFF_ROOT/scripts/setup/ensure-composer-vendors.sh" >&2
    return 1
  fi
  echo "    preflight $app: vendor/ missing — running composer install (not a permissions issue)"
  if [[ "$(id -u)" -eq 0 ]]; then
    export COMPOSER_ALLOW_SUPERUSER=1
  fi
  if ! (cd "$app_abs" && staff_composer install --no-dev --optimize-autoloader --no-interaction); then
    echo "    preflight $app FAILED: composer install did not create vendor/autoload.php" >&2
    return 1
  fi
  if [[ ! -f "$app_abs/vendor/autoload.php" ]]; then
    echo "    preflight $app FAILED: vendor/autoload.php still missing after composer install" >&2
    return 1
  fi
  echo "    preflight $app: vendor/ installed"
  return 0
}

preflight_artisan() {
  local app="$1" app_abs="$2"
  local out
  if ! ensure_composer_vendor "$app" "$app_abs"; then
    return 1
  fi
  if out="$(cd "$app_abs" && run_as_supervisor_user "$PHP_BIN" artisan about --only=environment 2>&1)"; then
    echo "    preflight $app OK"
    return 0
  fi
  # Fallback as current user (still useful for diagnostics).
  if out="$(cd "$app_abs" && "$PHP_BIN" artisan about --only=environment 2>&1)"; then
    echo "    preflight $app OK as current user (ensure $SUPERVISOR_USER can write storage/)"
    return 0
  fi
  echo "    preflight $app FAILED:" >&2
  echo "$out" | head -20 >&2
  return 1
}

# Remove obsolete APM/legacy program confs that fight the cbp-{slug}-* names.
retire_legacy_supervisor_programs() {
  local f base
  local -a legacy_globs=(
    "$CONF_DIR"/staff-apm-*.conf
    "$CONF_DIR"/staff-portal-*.conf
    "$CONF_DIR"/laravel-*-apm*.conf
    "$CONF_DIR"/supervisor-laravel-*.conf
  )
  for f in "${legacy_globs[@]}"; do
    [[ -e "$f" ]] || continue
    base="$(basename "$f")"
    # Keep current cbp-* programs
    [[ "$base" == cbp-* ]] && continue
    echo "    removing legacy Supervisor conf: $f"
    run_svc rm -f "$f" 2>/dev/null || rm -f "$f" 2>/dev/null || true
  done
}

print_cbp_status() {
  echo "==> supervisorctl status (cbp-${SLUG}-*):"
  # supervisorctl does not expand shell globs; filter the full status list.
  if supervisorctl_safe status 2>/dev/null | grep -E "^cbp-${SLUG}-" || true; then
    :
  fi
  local fatal
  fatal="$(supervisorctl_safe status 2>/dev/null | grep -E "^cbp-${SLUG}-" | grep -E 'FATAL|BACKOFF|EXITED' || true)"
  if [[ -n "$fatal" ]]; then
    echo "==> FATAL/BACKOFF programs — last log lines:" >&2
    echo "$fatal" >&2
    local line prog app_rel log
    while IFS= read -r line; do
      prog="$(printf '%s' "$line" | awk '{print $1}' | cut -d: -f1)"
      case "$prog" in
        *-helpdesk-*) app_rel="modules/helpdesk/backend" ;;
        *-finance-*) app_rel="modules/finance/backend" ;;
        *-staff-portal-*) app_rel="modules/staff-portal/backend" ;;
        *-risk-register-*) app_rel="modules/risk-register/backend" ;;
        *-apm-*) app_rel="modules/apm" ;;
        *) continue ;;
      esac
      if [[ "$prog" == *-queue ]]; then
        log="$STAFF_ROOT/$app_rel/storage/logs/supervisor-queue.log"
      else
        log="$STAFF_ROOT/$app_rel/storage/logs/supervisor-scheduler.log"
      fi
      echo "---- $prog ($log) ----" >&2
      if [[ -f "$log" ]]; then
        tail -n 40 "$log" >&2 || true
      else
        echo "(log missing — check directory ownership for $SUPERVISOR_USER)" >&2
        supervisorctl_safe tail -100 "$prog" 2>/dev/null >&2 || true
      fi
    done <<< "$fatal"
  fi
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
  ensure_supervisord_running
fi

echo "==> Supervisor programs (slug=$SLUG php=$PHP_BIN user=$SUPERVISOR_USER)"
mkdir -p "$CONF_DIR"
retire_legacy_supervisor_programs

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
  if ! ensure_supervisord_running; then
    echo "error: enable Supervisor before reread/update. Confs are in $CONF_DIR" >&2
    exit 1
  fi
  supervisorctl_safe reread
  supervisorctl_safe update
  # Clear FATAL state and try a clean start after ownership fixes.
  supervisorctl_safe start "cbp-${SLUG}-:" 2>/dev/null || true
  sleep 2
  print_cbp_status
else
  echo "warn: supervisorctl missing; confs written to $CONF_DIR" >&2
fi

echo "Done. Manage with: sudo supervisorctl status"
echo "If helpdesk/finance stay FATAL, inspect:"
echo "  sudo tail -n 80 modules/helpdesk/backend/storage/logs/supervisor-queue.log"
echo "  sudo tail -n 80 modules/finance/backend/storage/logs/supervisor-queue.log"
