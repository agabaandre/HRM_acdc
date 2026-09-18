#!/usr/bin/env bash
# One-shot production fix for SPA asset HTTP 500s on cbp.africacdc.org.
# Run on the server that serves /staff/staff-portal/ (as a user that can write the tree).
set -euo pipefail

echo "==> Locating staff-portal (looking for spa-static.php / setup-production.sh)"
ROOT=""
for cand in \
  /var/lib/ACDC_SYSTEMS/demo_staff \
  /var/www/html/demo_staff \
  /var/www/html/staff \
  /var/www/staff \
  /var/www/html
 do
  if [[ -f "$cand/staff-portal/spa-static.php" ]] || [[ -f "$cand/staff-portal/setup-production.sh" ]]; then
    ROOT="$cand"
    break
  fi
done

if [[ -z "$ROOT" ]]; then
  # fallback: directory containing this script's repo
  ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fi

PORTAL="$ROOT/staff-portal"
echo "    repo root: $ROOT"
echo "    portal:    $PORTAL"
[[ -d "$PORTAL" ]] || { echo "error: $PORTAL missing"; exit 1; }

# Match URL prefix to folder (demo_staff vs staff)
PARENT="$(basename "$ROOT")"
if [[ "$PARENT" == "demo_staff" ]]; then
  export VITE_STAFF_PORTAL_BASE_PATH="/demo_staff/staff-portal/"
  export VITE_STAFF_PORTAL_API_BASE_URL="/demo_staff/staff-portal/backend"
else
  export VITE_STAFF_PORTAL_BASE_PATH="${VITE_STAFF_PORTAL_BASE_PATH:-/staff/staff-portal/}"
  export VITE_STAFF_PORTAL_API_BASE_URL="${VITE_STAFF_PORTAL_API_BASE_URL:-/staff/staff-portal/backend}"
fi
echo "    Vite base: $VITE_STAFF_PORTAL_BASE_PATH"

if [[ -d "$ROOT/.git" ]]; then
  echo "==> git fetch + reset to origin/main"
  git -C "$ROOT" fetch origin
  git -C "$ROOT" reset --hard origin/main
fi

echo "==> Write frontend/.env.production.local"
cat > "$PORTAL/frontend/.env.production.local" <<EOF
VITE_STAFF_PORTAL_BASE_PATH=${VITE_STAFF_PORTAL_BASE_PATH}
VITE_STAFF_PORTAL_API_BASE_URL=${VITE_STAFF_PORTAL_API_BASE_URL}
EOF

echo "==> Ensure root .htaccess routes assets to spa-static.php"
if ! grep -q 'spa-static.php' "$ROOT/.htaccess" 2>/dev/null; then
  echo "error: $ROOT/.htaccess missing spa-static.php rules — pull latest main" >&2
  exit 1
fi

echo "==> Build frontend"
cd "$PORTAL/frontend"
export NODE_ENV=development
if [[ -f package-lock.json ]]; then
  npm ci --include=dev --legacy-peer-deps || npm install --include=dev --legacy-peer-deps
else
  npm install --include=dev --legacy-peer-deps
fi
NODE_ENV=production npm run build
[[ -f dist-build/index.html ]] || { echo "error: build failed"; exit 1; }

echo "==> Publish SPA files"
cd "$PORTAL"
chmod +x scripts/publish-spa.sh
./scripts/publish-spa.sh

echo "==> Permissions for www-data/_www"
chmod -R a+rX assets public-spa index.html spa-static.php frontend/dist-build 2>/dev/null || true

sample="$(find assets -maxdepth 1 -type f -name '*.js' | head -n 1 || true)"
echo "==> Local file check"
ls -la index.html spa-static.php | awk '{print $1,$5,$9}'
echo "    sample asset: ${sample:-NONE}"
echo "    index asset refs:"
rg -o "${VITE_STAFF_PORTAL_BASE_PATH}assets/[^\" ]+" index.html | head -5 || rg -o 'assets/[^" ]+' index.html | head -5

echo "==> HTTP checks"
SPA_URL="https://cbp.africacdc.org${VITE_STAFF_PORTAL_BASE_PATH}"
for url in \
  "${SPA_URL}" \
  "${SPA_URL}assets/$(basename "${sample:-missing.js}")"
 do
  code="$(curl -sS -o /dev/null -w '%{http_code} %{content_type}' --max-time 15 "$url" 2>/dev/null || echo '000')"
  echo "    $code  $url"
done

echo ""
echo "Done. Open an incognito window at:"
echo "  ${SPA_URL}"
echo "HTML must reference ${VITE_STAFF_PORTAL_BASE_PATH}assets/… (not /staff/… if this is demo_staff)."
