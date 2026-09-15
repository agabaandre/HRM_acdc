#!/usr/bin/env bash
#
# Install Helpdesk systemd units. Paths scoped by WEB_ROOT (site folder).
# Non-interactive: HELPDESK_INSTALL_NONINTERACTIVE=1
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

NONINTERACTIVE="${HELPDESK_INSTALL_NONINTERACTIVE:-0}"
SITE_SLUG="$(setup_systemd_site_slug "${WEB_ROOT:-staff}")"
DEFAULT_HEALTH="http://127.0.0.1/${SITE_SLUG}/helpdesk/backend/api/v1/health"

if [[ "$NONINTERACTIVE" == "1" ]]; then
  HELPDESK_ROOT="${HELPDESK_ROOT:-$REPO_ROOT/backend}"
  HELPDESK_USER="${HELPDESK_USER:-www-data}"
  HELPDESK_GROUP="${HELPDESK_GROUP:-$HELPDESK_USER}"
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
  HELPDESK_HEALTH_URL="${HELPDESK_HEALTH_URL:-$DEFAULT_HEALTH}"
else
  read -r -p "WEB_ROOT / site slug [$SITE_SLUG]: " input_slug
  SITE_SLUG="$(setup_systemd_site_slug "${input_slug:-$SITE_SLUG}")"
  DEFAULT_HEALTH="http://127.0.0.1/${SITE_SLUG}/helpdesk/backend/api/v1/health"
  read -r -p "HELPDESK_ROOT (Laravel backend) [$REPO_ROOT/backend]: " input_root
  HELPDESK_ROOT="${input_root:-$REPO_ROOT/backend}"
  read -r -p "Web server user [www-data]: " HELPDESK_USER
  HELPDESK_USER="${HELPDESK_USER:-www-data}"
  read -r -p "Web server group [www-data]: " HELPDESK_GROUP
  HELPDESK_GROUP="${HELPDESK_GROUP:-$HELPDESK_USER}"
  read -r -p "PHP binary [/usr/bin/php]: " PHP_BIN
  PHP_BIN="${PHP_BIN:-/usr/bin/php}"
  read -r -p "Health URL [$DEFAULT_HEALTH]: " HELPDESK_HEALTH_URL
  HELPDESK_HEALTH_URL="${HELPDESK_HEALTH_URL:-$DEFAULT_HEALTH}"
fi

HELPDESK_ROOT="$(cd "$HELPDESK_ROOT" && pwd)"
[[ -f "$HELPDESK_ROOT/artisan" ]] || {
  echo "error: no artisan at HELPDESK_ROOT=$HELPDESK_ROOT" >&2
  exit 1
}

TARGET="helpdesk-${SITE_SLUG}.target"
QUEUE="helpdesk-queue-${SITE_SLUG}.service"
SCHED_SVC="helpdesk-scheduler-${SITE_SLUG}.service"
SCHED_TMR="helpdesk-scheduler-${SITE_SLUG}.timer"
HEALTH_SVC="helpdesk-health-${SITE_SLUG}.service"
HEALTH_TMR="helpdesk-health-${SITE_SLUG}.timer"

echo "==> Retiring prior helpdesk systemd units (legacy + this site)"
systemd_retire_units \
  helpdesk.target \
  helpdesk-queue.service \
  helpdesk-scheduler.service \
  helpdesk-scheduler.timer \
  helpdesk-health.service \
  helpdesk-health.timer \
  "$TARGET" "$QUEUE" "$SCHED_SVC" "$SCHED_TMR" "$HEALTH_SVC" "$HEALTH_TMR"

ENV_DEST="/etc/helpdesk/${SITE_SLUG}.env"
DEPLOY_BIN="/opt/helpdesk/${SITE_SLUG}/bin"
mkdir -p /etc/helpdesk "$DEPLOY_BIN"
install -m 0755 \
  "$BIN_SRC/helpdesk-queue.sh" \
  "$BIN_SRC/helpdesk-scheduler.sh" \
  "$BIN_SRC/helpdesk-health.sh" \
  "$DEPLOY_BIN/"

cat >"$ENV_DEST" <<EOF
HELPDESK_ROOT=$HELPDESK_ROOT
HELPDESK_USER=$HELPDESK_USER
HELPDESK_GROUP=$HELPDESK_GROUP
PHP_BIN=$PHP_BIN
HELPDESK_HEALTH_URL=$HELPDESK_HEALTH_URL
HELPDESK_ENV_FILE=$ENV_DEST
WEB_ROOT=$SITE_SLUG
EOF
chmod 0640 "$ENV_DEST"
chown root:"$HELPDESK_GROUP" "$ENV_DEST" 2>/dev/null || true
cp "$ENV_DEST" "/opt/helpdesk/${SITE_SLUG}/helpdesk.env"
chmod 0640 "/opt/helpdesk/${SITE_SLUG}/helpdesk.env"

substitute() {
  local src="$1" dest="$2"
  sed \
    -e "s|@HELPDESK_USER@|$HELPDESK_USER|g" \
    -e "s|@HELPDESK_GROUP@|$HELPDESK_GROUP|g" \
    -e "s|@HELPDESK_DEPLOY_BIN@|$DEPLOY_BIN|g" \
    -e "s|helpdesk\.target|$TARGET|g" \
    -e "s|helpdesk-queue\.service|$QUEUE|g" \
    -e "s|helpdesk-scheduler\.service|$SCHED_SVC|g" \
    -e "s|helpdesk-scheduler\.timer|$SCHED_TMR|g" \
    -e "s|helpdesk-health\.service|$HEALTH_SVC|g" \
    -e "s|helpdesk-health\.timer|$HEALTH_TMR|g" \
    "$src" >"$dest"
  if grep -q '^\[Service\]' "$dest" && ! grep -q "HELPDESK_ENV_FILE=" "$dest"; then
    ENV_DEST="$ENV_DEST" perl -i -pe 'if (/^\[Service\]/ && !$done++) { $_ .= "Environment=HELPDESK_ENV_FILE=$ENV{ENV_DEST}\n" }' "$dest"
  fi
}

substitute "$SYSTEMD_SRC/helpdesk.target" "/etc/systemd/system/$TARGET"
substitute "$SYSTEMD_SRC/helpdesk-queue.service" "/etc/systemd/system/$QUEUE"
substitute "$SYSTEMD_SRC/helpdesk-scheduler.service" "/etc/systemd/system/$SCHED_SVC"
substitute "$SYSTEMD_SRC/helpdesk-scheduler.timer" "/etc/systemd/system/$SCHED_TMR"
substitute "$SYSTEMD_SRC/helpdesk-health.service" "/etc/systemd/system/$HEALTH_SVC"
substitute "$SYSTEMD_SRC/helpdesk-health.timer" "/etc/systemd/system/$HEALTH_TMR"

systemctl daemon-reload
systemctl enable "$TARGET" "$QUEUE" "$SCHED_TMR" "$HEALTH_TMR"
systemctl restart "$TARGET" 2>/dev/null || systemctl start "$TARGET"

echo ""
echo "systemd installed for site=$SITE_SLUG"
echo "  WorkingDirectory/ROOT=$HELPDESK_ROOT"
echo "  EnvFile=$ENV_DEST"
echo "  Health=$HELPDESK_HEALTH_URL"
systemctl is-active "$QUEUE" 2>/dev/null && systemctl status "$QUEUE" --no-pager -l | head -20 || true
