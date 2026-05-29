#!/usr/bin/env bash
# Apache (Homebrew on macOS) runs as _www; Linux typically uses www-data.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

WEB_GROUP="_www"
if [[ "$(uname -s)" != "Darwin" ]]; then
  WEB_GROUP="www-data"
fi

OWNER="$(whoami)"

run_chown() {
  local targets=(storage bootstrap/cache database)
  if [[ -f database/database.sqlite ]]; then
    targets+=(database/database.sqlite)
  fi
  if chown -R "${OWNER}:${WEB_GROUP}" "${targets[@]}" 2>/dev/null; then
    return 0
  fi
  if [[ -n "${FINANCE_SETUP_SUDO_PASSWORD:-}" ]]; then
    echo "${FINANCE_SETUP_SUDO_PASSWORD}" | sudo -S chown -R "${OWNER}:${WEB_GROUP}" "${targets[@]}"
    return 0
  fi
  if sudo -n chown -R "${OWNER}:${WEB_GROUP}" "${targets[@]}" 2>/dev/null; then
    return 0
  fi
  echo "Need sudo to set ownership. Run:"
  echo "  sudo chown -R ${OWNER}:${WEB_GROUP} storage bootstrap/cache database"
  echo "  sudo chown ${OWNER}:${WEB_GROUP} database/database.sqlite"
  exit 1
}

run_chown
chmod -R ug+rwx storage bootstrap/cache
chmod 775 database 2>/dev/null || true
if [[ -f database/database.sqlite ]]; then
  chmod 664 database/database.sqlite
fi
echo "Permissions OK (${OWNER}:${WEB_GROUP}): storage/, bootstrap/cache/, database/ (+ database.sqlite)."
