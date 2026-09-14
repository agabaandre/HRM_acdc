#!/usr/bin/env bash
#
# Install Staff Portal systemd units (queue worker, scheduler, health checks).
# Non-interactive (from ./setup.sh): set STAFF_PORTAL_INSTALL_NONINTERACTIVE=1 and paths.
# Interactive: sudo ./install.sh
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SYSTEMD_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BIN_SRC="$REPO_ROOT/deploy/bin"
ENV_EXAMPLE="$SYSTEMD_SRC/staff-portal.env.example"
ENV_DEST="/etc/staff-portal/staff-portal.env"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo $0" >&2
  exit 1
fi

NONINTERACTIVE="${STAFF_PORTAL_INSTALL_NONINTERACTIVE:-0}"

if [[ "$NONINTERACTIVE" == "1" ]]; then
  STAFF_PORTAL_ROOT="${STAFF_PORTAL_ROOT:-$REPO_ROOT/backend}"
  STAFF_PORTAL_USER="${STAFF_PORTAL_USER:-www-data}"
  STAFF_PORTAL_GROUP="${STAFF_PORTAL_GROUP:-$STAFF_PORTAL_USER}"
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
  STAFF_PORTAL_HEALTH_URL="${STAFF_PORTAL_HEALTH_URL:-http://127.0.0.1/staff/staff-portal/backend/up}"
else
  read -r -p "STAFF_PORTAL_ROOT (Laravel backend) [$REPO_ROOT/backend]: " input_root
  STAFF_PORTAL_ROOT="${input_root:-$REPO_ROOT/backend}"
  read -r -p "Web server user [www-data]: " STAFF_PORTAL_USER
  STAFF_PORTAL_USER="${STAFF_PORTAL_USER:-www-data}"
  read -r -p "Web server group [www-data]: " STAFF_PORTAL_GROUP
  STAFF_PORTAL_GROUP="${STAFF_PORTAL_GROUP:-$STAFF_PORTAL_USER}"
  read -r -p "PHP binary [/usr/bin/php]: " PHP_BIN
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
  read -r -p "Health URL [http://127.0.0.1/staff/staff-portal/backend/up]: " STAFF_PORTAL_HEALTH_URL
  STAFF_PORTAL_HEALTH_URL="${STAFF_PORTAL_HEALTH_URL:-http://127.0.0.1/staff/staff-portal/backend/up}"
fi

STAFF_PORTAL_ROOT="$(cd "$STAFF_PORTAL_ROOT" && pwd)"

DEPLOY_BIN="/opt/staff-portal/bin"
mkdir -p /etc/staff-portal "$DEPLOY_BIN"
install -m 0755 "$BIN_SRC/staff-portal-queue.sh" "$BIN_SRC/staff-portal-scheduler.sh" "$BIN_SRC/staff-portal-health.sh" "$DEPLOY_BIN/"

# shellcheck disable=SC2016
cat >"$ENV_DEST" <<EOF
STAFF_PORTAL_ROOT=$STAFF_PORTAL_ROOT
STAFF_PORTAL_USER=$STAFF_PORTAL_USER
STAFF_PORTAL_GROUP=$STAFF_PORTAL_GROUP
PHP_BIN=$PHP_BIN
STAFF_PORTAL_HEALTH_URL=$STAFF_PORTAL_HEALTH_URL
EOF
chmod 0640 "$ENV_DEST"
chown root:"$STAFF_PORTAL_GROUP" "$ENV_DEST" 2>/dev/null || true

substitute() {
  local src="$1" dest="$2"
  sed \
    -e "s|@STAFF_PORTAL_USER@|$STAFF_PORTAL_USER|g" \
    -e "s|@STAFF_PORTAL_GROUP@|$STAFF_PORTAL_GROUP|g" \
    -e "s|@STAFF_PORTAL_DEPLOY_BIN@|$DEPLOY_BIN|g" \
    "$src" >"$dest"
}

for unit in staff-portal.target staff-portal-queue.service staff-portal-scheduler.service staff-portal-scheduler.timer staff-portal-health.service staff-portal-health.timer; do
  substitute "$SYSTEMD_SRC/$unit" "/etc/systemd/system/$unit"
done

systemctl daemon-reload
systemctl enable staff-portal.target staff-portal-queue.service staff-portal-scheduler.timer staff-portal-health.timer
systemctl restart staff-portal.target 2>/dev/null || systemctl start staff-portal.target

echo ""
echo "systemd installed for $STAFF_PORTAL_ROOT"
systemctl is-active staff-portal-queue.service 2>/dev/null && systemctl status staff-portal-queue.service --no-pager -l | head -15 || true

# Keep shellcheck happy about unused example path
: "${ENV_EXAMPLE:=}"
