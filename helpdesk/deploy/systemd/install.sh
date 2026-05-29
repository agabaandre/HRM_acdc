#!/usr/bin/env bash
#
# Install Helpdesk systemd units (queue worker, scheduler, health checks).
# Non-interactive (from ./setup.sh): set HELPDESK_INSTALL_NONINTERACTIVE=1 and paths.
# Interactive: sudo ./install.sh
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SYSTEMD_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BIN_SRC="$REPO_ROOT/deploy/bin"
ENV_EXAMPLE="$SYSTEMD_SRC/helpdesk.env.example"
ENV_DEST="/etc/helpdesk/helpdesk.env"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo $0" >&2
  exit 1
fi

NONINTERACTIVE="${HELPDESK_INSTALL_NONINTERACTIVE:-0}"

if [[ "$NONINTERACTIVE" == "1" ]]; then
  HELPDESK_ROOT="${HELPDESK_ROOT:-$REPO_ROOT/backend}"
  HELPDESK_USER="${HELPDESK_USER:-www-data}"
  HELPDESK_GROUP="${HELPDESK_GROUP:-$HELPDESK_USER}"
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
  HELPDESK_HEALTH_URL="${HELPDESK_HEALTH_URL:-http://127.0.0.1/staff/helpdesk/backend/api/v1/health}"
else
  read -r -p "HELPDESK_ROOT (Laravel backend) [$REPO_ROOT/backend]: " input_root
  HELPDESK_ROOT="${input_root:-$REPO_ROOT/backend}"
  read -r -p "Web server user [www-data]: " HELPDESK_USER
  HELPDESK_USER="${HELPDESK_USER:-www-data}"
  read -r -p "Web server group [www-data]: " HELPDESK_GROUP
  HELPDESK_GROUP="${HELPDESK_GROUP:-$HELPDESK_USER}"
  read -r -p "PHP binary [/usr/bin/php]: " PHP_BIN
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
  read -r -p "Health URL [http://127.0.0.1/staff/helpdesk/backend/api/v1/health]: " HELPDESK_HEALTH_URL
  HELPDESK_HEALTH_URL="${HELPDESK_HEALTH_URL:-http://127.0.0.1/staff/helpdesk/backend/api/v1/health}"
fi

HELPDESK_ROOT="$(cd "$HELPDESK_ROOT" && pwd)"

DEPLOY_BIN="/opt/helpdesk/bin"
mkdir -p /etc/helpdesk "$DEPLOY_BIN"
install -m 0755 "$BIN_SRC/helpdesk-queue.sh" "$BIN_SRC/helpdesk-scheduler.sh" "$BIN_SRC/helpdesk-health.sh" "$DEPLOY_BIN/"

# shellcheck disable=SC2016
cat >"$ENV_DEST" <<EOF
HELPDESK_ROOT=$HELPDESK_ROOT
HELPDESK_USER=$HELPDESK_USER
HELPDESK_GROUP=$HELPDESK_GROUP
PHP_BIN=$PHP_BIN
HELPDESK_HEALTH_URL=$HELPDESK_HEALTH_URL
EOF
chmod 0640 "$ENV_DEST"
chown root:"$HELPDESK_GROUP" "$ENV_DEST" 2>/dev/null || true

substitute() {
  local src="$1" dest="$2"
  sed \
    -e "s|@HELPDESK_USER@|$HELPDESK_USER|g" \
    -e "s|@HELPDESK_GROUP@|$HELPDESK_GROUP|g" \
    -e "s|@HELPDESK_DEPLOY_BIN@|$DEPLOY_BIN|g" \
    "$src" >"$dest"
}

for unit in helpdesk.target helpdesk-queue.service helpdesk-scheduler.service helpdesk-scheduler.timer helpdesk-health.service helpdesk-health.timer; do
  substitute "$SYSTEMD_SRC/$unit" "/etc/systemd/system/$unit"
done

systemctl daemon-reload
systemctl enable helpdesk.target helpdesk-queue.service helpdesk-scheduler.timer helpdesk-health.timer
systemctl restart helpdesk.target 2>/dev/null || systemctl start helpdesk.target

echo ""
echo "systemd installed for $HELPDESK_ROOT"
systemctl is-active helpdesk-queue.service 2>/dev/null && systemctl status helpdesk-queue.service --no-pager -l | head -15 || true
