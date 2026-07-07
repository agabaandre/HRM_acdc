#!/usr/bin/env bash
# Reset CodeIgniter application/cache and application/logs for web server + CLI (macOS + Ubuntu).
# Run from repo root: ./scripts/fix-ci-app-permissions.sh
#
# macOS Apache (_www) and Ubuntu www-data need write access to cache JSON files
# used by cache_helper.php (dashboard lookups, SSO codes, job schedules, etc.).

set -euo pipefail
cd "$(cd "$(dirname "$0")/.." && pwd)"

OWNER="${SUDO_USER:-$USER}"
if [[ -z "$OWNER" || "$OWNER" == "root" ]]; then
  OWNER="$(id -un)"
fi

if [[ "$(uname -s)" == "Darwin" ]]; then
  GROUP="${CI_APP_GROUP:-staff}"
else
  GROUP="${CI_APP_GROUP:-www-data}"
fi

DIRS=(
  application/cache
  application/cache/cbp_sso
  application/cache/temp
  application/cache/login_attempts
  application/logs
)

echo "Creating CI writable directories…"
for dir in "${DIRS[@]}"; do
  mkdir -p "$dir"
done

# Preserve index.html / .htaccess if missing
[[ -f application/cache/index.html ]] || echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > application/cache/index.html
[[ -f application/logs/index.html ]] || echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access is forbidden.</p></body></html>' > application/logs/index.html

echo "Using owner: ${OWNER}:${GROUP}"

needs_sudo_chown=false
if [[ -d application/cache ]]; then
  cache_owner="$(stat -f '%Su' application/cache 2>/dev/null || stat -c '%U' application/cache)"
  [[ "$cache_owner" == "root" || ( "$cache_owner" != "$OWNER" && ! -w application/cache ) ]] && needs_sudo_chown=true
fi

if [[ "$needs_sudo_chown" == "true" ]]; then
  sudo chown -R "${OWNER}:${GROUP}" application/cache application/logs
else
  chown -R "${OWNER}:${GROUP}" application/cache application/logs 2>/dev/null || sudo chown -R "${OWNER}:${GROUP}" application/cache application/logs
fi

# ug+rwX for dev user + group; o+rwx so macOS _www / Ubuntu www-data can write
chmod -R ug+rwX,o+rwx application/cache application/logs

echo "Done. Reload http://localhost/staff/ and retry the dashboard."
echo "Re-run after sudo migrations if cache becomes root-owned again."
