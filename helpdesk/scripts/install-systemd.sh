#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# shellcheck source=lib/dotenv.sh
source "$REPO_ROOT/scripts/lib/dotenv.sh"

SETUP_ENV="${HELPDESK_SETUP_ENV:-$REPO_ROOT/setup.env}"
dotenv_load_file "$SETUP_ENV"

INSTALL_SYSTEMD="${INSTALL_SYSTEMD:-auto}"
case "$INSTALL_SYSTEMD" in
    true|1|yes) ;;
    false|0|no)
        echo "Skipping systemd (INSTALL_SYSTEMD=false in setup.env)."
        exit 0
        ;;
    auto)
        if [[ "$(uname -s)" != "Linux" ]] || ! command -v systemctl >/dev/null 2>&1; then
            echo "Skipping systemd (not Linux or systemctl missing)."
            exit 0
        fi
        ;;
    *)
        echo "Unknown INSTALL_SYSTEMD=$INSTALL_SYSTEMD (use auto|true|false)." >&2
        exit 1
        ;;
esac

PHP_BIN="${PHP_BIN:-/usr/bin/php}"
if [[ ! -x "$PHP_BIN" ]]; then
    PHP_BIN="$(command -v php || true)"
fi
[[ -n "$PHP_BIN" ]] || { echo "PHP binary not found." >&2; exit 1; }

HELPDESK_USER="${HELPDESK_USER:-www-data}"
HELPDESK_GROUP="${HELPDESK_GROUP:-$HELPDESK_USER}"
HELPDESK_HEALTH_URL="${HELPDESK_HEALTH_URL:-http://127.0.0.1/staff/helpdesk/backend/api/v1/health}"

export HELPDESK_INSTALL_NONINTERACTIVE=1
export HELPDESK_ROOT="$REPO_ROOT/backend"
export HELPDESK_USER HELPDESK_GROUP PHP_BIN HELPDESK_HEALTH_URL

INSTALLER="$REPO_ROOT/deploy/systemd/install.sh"
if [[ ! -f "$INSTALLER" ]]; then
    echo "Missing $INSTALLER" >&2
    exit 1
fi

run_install() {
    bash "$INSTALLER"
}

if [[ "$(id -u)" -eq 0 ]]; then
    run_install
elif command -v sudo >/dev/null 2>&1; then
    echo "Installing systemd units (sudo required)…"
    sudo -E HELPDESK_INSTALL_NONINTERACTIVE=1 \
        HELPDESK_ROOT="$REPO_ROOT/backend" \
        HELPDESK_USER="$HELPDESK_USER" \
        HELPDESK_GROUP="$HELPDESK_GROUP" \
        PHP_BIN="$PHP_BIN" \
        HELPDESK_HEALTH_URL="$HELPDESK_HEALTH_URL" \
        bash "$INSTALLER"
else
    echo "Run as root or with sudo to install systemd:" >&2
    echo "  sudo -E $INSTALLER" >&2
    exit 1
fi
