#!/usr/bin/env bash
# Publish Risk Register Vite build for /staff/risk-register/
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${1:-$ROOT/frontend/dist-build}"

if [[ ! -f "$DIST/index.html" || ! -d "$DIST/assets" ]]; then
  echo "error: missing $DIST/index.html or $DIST/assets — run: cd frontend && npm run build" >&2
  exit 1
fi

echo "==> Publishing Risk Register SPA from $DIST"
rm -rf "$ROOT/public-spa"
mkdir -p "$ROOT/public-spa"
cp -a "$DIST/." "$ROOT/public-spa/"

# Keep root index.html in sync for spa-static.php fallbacks (prefer dist-build via .htaccess).
cp -f "$DIST/index.html" "$ROOT/index.html"

# Remove clone Staff Portal assets that steal /staff/risk-register/assets via wrong base.
if [[ -d "$ROOT/assets" ]]; then
  # Only replace if assets are risk-register build copies under this module
  rm -rf "$ROOT/assets"
fi
cp -a "$DIST/assets" "$ROOT/assets"
cat > "$ROOT/assets/.htaccess" <<'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine Off
</IfModule>
EOF

chmod -R a+rX "$ROOT/assets" "$ROOT/public-spa" "$ROOT/index.html" 2>/dev/null || true
echo "==> Done. Open /staff/risk-register/ (hard refresh)."
