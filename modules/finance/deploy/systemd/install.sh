#!/usr/bin/env bash
#
# Install Staff Portal systemd units (queue worker, scheduler, health checks).
# Paths are scoped by WEB_ROOT so /cbp and /cbpdemo do not share WorkingDirectory.
# Non-interactive (from ./setup.sh): set STAFF_PORTAL_INSTALL_NONINTERACTIVE=1 and paths.
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SYSTEMD_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BIN_SRC="$REPO_ROOT/deploy/bin"
STAFF_ROOT="$(cd "$REPO_ROOT/../.." && pwd)"

# shellcheck source=../../../../scripts/setup/systemd-cleanup.sh
if [[ -f "$STAFF_ROOT/scripts/setup/systemd-cleanup.sh" ]]; then
  # shellcheck disable=SC1091
  source "$STAFF_ROOT/scripts/setup/systemd-cleanup.sh"
else
  systemd_retire_units() { :; }
fi
# shellcheck source=../../../../scripts/setup/systemd-site.sh
if [[ -f "$STAFF_ROOT/scripts/setup/systemd-site.sh" ]]; then
  # shellcheck disable=SC1091
  source "$STAFF_ROOT/scripts/setup/systemd-site.sh"
else
  setup_systemd_site_slug() { printf '%s' "${1:-staff}"; }
fi

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo $0" >&2
  exit 1
fi

NONINTERACTIVE="${STAFF_PORTAL_INSTALL_NONINTERACTIVE:-0}"
SITE_SLUG="$(setup_systemd_site_slug "${WEB_ROOT:-staff}")"
DEFAULT_HEALTH="http://127.0.0.1/${SITE_SLUG}/backend/up"

if [[ "$NONINTERACTIVE" == "1" ]]; then
  STAFF_PORTAL_ROOT="${STAFF_PORTAL_ROOT:-$REPO_ROOT/backend}"
  STAFF_PORTAL_USER="${STAFF_PORTAL_USER:-www-data}"
  STAFF_PORTAL_GROUP="${STAFF_PORTAL_GROUP:-$STAFF_PORTAL_USER}"
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
  STAFF_PORTAL_HEALTH_URL="${STAFF_PORTAL_HEALTH_URL:-$DEFAULT_HEALTH}"
else
  read -r -p "WEB_ROOT / site slug [$SITE_SLUG]: " input_slug
  SITE_SLUG="$(setup_systemd_site_slug "${input_slug:-$SITE_SLUG}")"
  DEFAULT_HEALTH="http://127.0.0.1/${SITE_SLUG}/backend/up"
  read -r -p "STAFF_PORTAL_ROOT (Laravel backend) [$REPO_ROOT/backend]: " input_root
  STAFF_PORTAL_ROOT="${input_root:-$REPO_ROOT/backend}"
  read -r -p "Web server user [www-data]: " STAFF_PORTAL_USER
  STAFF_PORTAL_USER="${STAFF_PORTAL_USER:-www-data}"
  read -r -p "Web server group [www-data]: " STAFF_PORTAL_GROUP
  STAFF_PORTAL_GROUP="${STAFF_PORTAL_GROUP:-$STAFF_PORTAL_USER}"
  read -r -p "PHP binary [/usr/bin/php]: " PHP_BIN
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
  read -r -p "Health URL [$DEFAULT_HEALTH]: " STAFF_PORTAL_HEALTH_URL
  STAFF_PORTAL_HEALTH_URL="${STAFF_PORTAL_HEALTH_URL:-$DEFAULT_HEALTH}"
fi

STAFF_PORTAL_ROOT="$(cd "$STAFF_PORTAL_ROOT" && pwd)"
[[ -f "$STAFF_PORTAL_ROOT/artisan" ]] || {
  echo "error: no artisan at STAFF_PORTAL_ROOT=$STAFF_PORTAL_ROOT" >&2
  exit 1
}

TARGET="staff-portal-${SITE_SLUG}.target"
QUEUE="staff-portal-queue-${SITE_SLUG}.service"
SCHED_SVC="staff-portal-scheduler-${SITE_SLUG}.service"
SCHED_TMR="staff-portal-scheduler-${SITE_SLUG}.timer"
HEALTH_SVC="staff-portal-health-${SITE_SLUG}.service"
HEALTH_TMR="staff-portal-health-${SITE_SLUG}.timer"

echo "==> Retiring prior staff-portal systemd units (legacy + this site)"
systemd_retire_units \
  staff-portal.target \
  staff-portal-queue.service \
  staff-portal-scheduler.service \
  staff-portal-scheduler.timer \
  staff-portal-health.service \
  staff-portal-health.timer \
  "$TARGET" "$QUEUE" "$SCHED_SVC" "$SCHED_TMR" "$HEALTH_SVC" "$HEALTH_TMR"

ENV_DEST="/etc/staff-portal/${SITE_SLUG}.env"
DEPLOY_BIN="/opt/staff-portal/${SITE_SLUG}/bin"
mkdir -p /etc/staff-portal "$DEPLOY_BIN"
install -m 0755 \
  "$BIN_SRC/staff-portal-queue.sh" \
  "$BIN_SRC/staff-portal-scheduler.sh" \
  "$BIN_SRC/staff-portal-health.sh" \
  "$DEPLOY_BIN/"

cat >"$ENV_DEST" <<EOF
STAFF_PORTAL_ROOT=$STAFF_PORTAL_ROOT
STAFF_PORTAL_USER=$STAFF_PORTAL_USER
STAFF_PORTAL_GROUP=$STAFF_PORTAL_GROUP
PHP_BIN=$PHP_BIN
STAFF_PORTAL_HEALTH_URL=$STAFF_PORTAL_HEALTH_URL
STAFF_PORTAL_ENV_FILE=$ENV_DEST
WEB_ROOT=$SITE_SLUG
EOF
chmod 0640 "$ENV_DEST"
chown root:"$STAFF_PORTAL_GROUP" "$ENV_DEST" 2>/dev/null || true

# Also keep a copy beside the bins for resolution by path.
cp "$ENV_DEST" "/opt/staff-portal/${SITE_SLUG}/staff-portal.env"
chmod 0640 "/opt/staff-portal/${SITE_SLUG}/staff-portal.env"

substitute() {
  local src="$1" dest="$2"
  sed \
    -e "s|@STAFF_PORTAL_USER@|$STAFF_PORTAL_USER|g" \
    -e "s|@STAFF_PORTAL_GROUP@|$STAFF_PORTAL_GROUP|g" \
    -e "s|@STAFF_PORTAL_DEPLOY_BIN@|$DEPLOY_BIN|g" \
    -e "s|staff-portal\.target|$TARGET|g" \
    -e "s|staff-portal-queue\.service|$QUEUE|g" \
    -e "s|staff-portal-scheduler\.service|$SCHED_SVC|g" \
    -e "s|staff-portal-scheduler\.timer|$SCHED_TMR|g" \
    -e "s|staff-portal-health\.service|$HEALTH_SVC|g" \
    -e "s|staff-portal-health\.timer|$HEALTH_TMR|g" \
    "$src" >"$dest"
  # Ensure worker scripts load the site-scoped env file.
  if grep -q '^\[Service\]' "$dest" && ! grep -q "STAFF_PORTAL_ENV_FILE=" "$dest"; then
    ENV_DEST="$ENV_DEST" perl -i -pe 'if (/^\[Service\]/ && !$done++) { $_ .= "Environment=STAFF_PORTAL_ENV_FILE=$ENV{ENV_DEST}\n" }' "$dest"
  fi
}

substitute "$SYSTEMD_SRC/staff-portal.target" "/etc/systemd/system/$TARGET"
substitute "$SYSTEMD_SRC/staff-portal-queue.service" "/etc/systemd/system/$QUEUE"
substitute "$SYSTEMD_SRC/staff-portal-scheduler.service" "/etc/systemd/system/$SCHED_SVC"
substitute "$SYSTEMD_SRC/staff-portal-scheduler.timer" "/etc/systemd/system/$SCHED_TMR"
substitute "$SYSTEMD_SRC/staff-portal-health.service" "/etc/systemd/system/$HEALTH_SVC"
substitute "$SYSTEMD_SRC/staff-portal-health.timer" "/etc/systemd/system/$HEALTH_TMR"

systemctl daemon-reload
systemctl enable "$TARGET" "$QUEUE" "$SCHED_TMR" "$HEALTH_TMR"
systemctl restart "$TARGET" 2>/dev/null || systemctl start "$TARGET"

echo ""
echo "systemd installed for site=$SITE_SLUG"
echo "  WorkingDirectory/ROOT=$STAFF_PORTAL_ROOT"
echo "  EnvFile=$ENV_DEST"
echo "  Health=$STAFF_PORTAL_HEALTH_URL"
systemctl is-active "$QUEUE" 2>/dev/null && systemctl status "$QUEUE" --no-pager -l | head -20 || true
