#!/usr/bin/env bash
# Reset Laravel storage/bootstrap cache ownership and permissions (macOS + Apache/_www + CLI).
# Run from apm/:  ./fix-storage-permissions.sh
# You will be prompted for your sudo password once.

set -euo pipefail
cd "$(dirname "$0")"

OWNER="${SUDO_USER:-$USER}"
if [[ -z "$OWNER" || "$OWNER" == "root" ]]; then
  OWNER="$(id -un)"
fi

echo "Using owner: $OWNER:staff"
sudo chown -R "$OWNER:staff" storage bootstrap/cache
chmod -R ug+rwX,o+rwx storage bootstrap/cache

echo "Done. Reload http://localhost and clear compiled views if needed:"
echo "  php artisan view:clear"
