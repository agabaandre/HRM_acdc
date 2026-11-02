#!/usr/bin/env bash
# Publish IT Vite build for /staff/it/
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${1:-$ROOT/frontend/dist-build}"

if [[ ! -f "$DIST/index.html" || ! -d "$DIST/assets" ]]; then
  echo "error: missing $DIST/index.html or $DIST/assets — run: cd frontend && npm run build" >&2
  exit 1
fi

echo "==> Publishing IT SPA from $DIST"
rm -rf "$ROOT/public-spa"
mkdir -p "$ROOT/public-spa"
cp -a "$DIST/." "$ROOT/public-spa/"

# Apache .htaccess prefers frontend/dist-user when present — keep it in sync.
rm -rf "$ROOT/frontend/dist-user"
mkdir -p "$ROOT/frontend/dist-user"
cp -a "$DIST/." "$ROOT/frontend/dist-user/"

# Keep root index.html in sync for spa-static.php fallbacks.
cp -f "$DIST/index.html" "$ROOT/index.html"

# Guard: reject builds that point at the wrong module asset base.
if grep -EEq 'src="/(staff|demo_staff|cbp|demo_cbp)[^"]*/assets/' "$ROOT/index.html"; then
  if ! grep -EEq 'src="[^"]*/it/assets/' "$ROOT/index.html"; then
    echo "error: index.html asset URLs are not under …/it/assets/ — Vite base is wrong." >&2
    echo "       Rebuild with VITE_STAFF_PORTAL_BASE_PATH=/{web}/it/" >&2
    grep -E 'assets/' "$ROOT/index.html" | head -5 >&2 || true
    exit 1
  fi
fi

if [[ -d "$ROOT/assets" ]]; then
  rm -rf "$ROOT/assets"
fi
cp -a "$DIST/assets" "$ROOT/assets"
cat > "$ROOT/assets/.htaccess" <<'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ - [L]
</IfModule>
EOF

chmod -R a+rX "$ROOT/assets" "$ROOT/public-spa" "$ROOT/index.html" 2>/dev/null || true
echo "==> Done. Open /staff/it/ (hard refresh)."
