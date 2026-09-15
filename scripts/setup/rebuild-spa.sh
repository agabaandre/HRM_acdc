#!/usr/bin/env bash
# Force Vite public base to WEB_ROOT, build staff-portal SPA, publish assets.
# Usage (from repo root or via ./setup.sh):
#   WEB_ROOT=demo_staff ./scripts/setup/rebuild-spa.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PORTAL="$ROOT/modules/staff-portal"
FRONTEND="$PORTAL/frontend"
PUBLISH="$PORTAL/scripts/publish-spa.sh"

# shellcheck source=env-upsert.sh
source "$ROOT/scripts/setup/env-upsert.sh"

WEB_ROOT="${WEB_ROOT:-$(basename "$ROOT")}"
WEB_ROOT="$(printf '%s' "$WEB_ROOT" | sed -E 's#^/##; s#/$##')"
[[ -n "$WEB_ROOT" ]] || WEB_ROOT=staff

PUBLIC_PATH="/${WEB_ROOT}"
VITE_BASE="${VITE_STAFF_PORTAL_BASE_PATH:-${PUBLIC_PATH}/}"
VITE_API="${VITE_STAFF_PORTAL_API_BASE_URL:-${PUBLIC_PATH}/backend}"
# Normalize trailing slash on base
case "$VITE_BASE" in
  */) ;;
  *) VITE_BASE="${VITE_BASE}/" ;;
esac

echo "==> SPA rebuild for WEB_ROOT=${WEB_ROOT}"
echo "    VITE_STAFF_PORTAL_BASE_PATH=${VITE_BASE}"
echo "    VITE_STAFF_PORTAL_API_BASE_URL=${VITE_API}"

if [[ ! -d "$FRONTEND" ]]; then
  echo "error: missing $FRONTEND" >&2
  exit 1
fi
command -v npm >/dev/null 2>&1 || {
  echo "error: npm not found on PATH" >&2
  exit 1
}

# Force paths everywhere Vite / setup might read them (never leave stale cbpdemo/staff).
for f in \
  "$PORTAL/setup.env" \
  "$FRONTEND/.env.production" \
  "$FRONTEND/.env.production.local"
do
  env_ensure_file "$f" ""
  env_set "$f" VITE_STAFF_PORTAL_BASE_PATH "$VITE_BASE"
  env_set "$f" VITE_STAFF_PORTAL_API_BASE_URL "$VITE_API"
done

# Writable dist for non-root deploy user
if [[ -d "$FRONTEND/dist-build" ]] && [[ ! -w "$FRONTEND/dist-build" ]]; then
  echo "warn: fixing ownership on frontend/dist-build" >&2
  chown -R "$(id -un):$(id -gn)" "$FRONTEND/dist-build" 2>/dev/null \
    || sudo chown -R "$(id -un):$(id -gn)" "$FRONTEND/dist-build" 2>/dev/null \
    || true
fi

(
  cd "$FRONTEND"
  # Build needs vite + plugins from devDependencies.
  export NODE_ENV=development
  if [[ -f package-lock.json ]]; then
    if ! npm ci --include=dev --cache ./.npm-cache --legacy-peer-deps; then
      echo "warn: npm ci failed — falling back to npm install" >&2
      npm install --include=dev --cache ./.npm-cache --legacy-peer-deps
    fi
  else
    npm install --include=dev --cache ./.npm-cache --legacy-peer-deps
  fi
  export NODE_ENV=production
  # loadEnv reads process env + .env.production*
  export VITE_STAFF_PORTAL_BASE_PATH="$VITE_BASE"
  export VITE_STAFF_PORTAL_API_BASE_URL="$VITE_API"
  npm run build
)

chmod +x "$PUBLISH" 2>/dev/null || true
"$PUBLISH" "$FRONTEND/dist-build"

# Sanity: built index must reference this web root, not a previous folder.
if grep -E 'src="/[^"]+/assets/' "$PORTAL/index.html" >/dev/null 2>&1; then
  if ! grep -F "\"${PUBLIC_PATH}/assets/" "$PORTAL/index.html" >/dev/null 2>&1 \
    && ! grep -F "'${PUBLIC_PATH}/assets/" "$PORTAL/index.html" >/dev/null 2>&1 \
    && ! grep -F "${PUBLIC_PATH}/assets/" "$PORTAL/index.html" >/dev/null 2>&1; then
    echo "error: published index.html does not reference ${PUBLIC_PATH}/assets/ — build base wrong" >&2
    grep -E 'assets/' "$PORTAL/index.html" | head -5 >&2 || true
    exit 1
  fi
fi

echo "==> SPA OK for ${PUBLIC_PATH}/ (hard-refresh the browser)"
