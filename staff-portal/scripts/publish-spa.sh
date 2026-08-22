#!/usr/bin/env bash
# Publish Vite build to staff-portal root as REAL files (no symlinks).
# Fixes Apache 500 on /staff/staff-portal/assets/* when rewrite/CI catch-all breaks.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${1:-$ROOT/frontend/dist-build}"

if [[ ! -f "$DIST/index.html" || ! -d "$DIST/assets" ]]; then
  echo "error: missing $DIST/index.html or $DIST/assets — run: cd frontend && npm run build" >&2
  exit 1
fi

echo "==> Removing old published SPA files (including broken symlinks)"
rm -rf "$ROOT/assets" "$ROOT/public-spa" "$ROOT/maps"
# Old mistaken symlink targets
[[ -L "$ROOT/index.html" ]] && rm -f "$ROOT/index.html"

echo "==> Copying dist → public-spa/ + root index.html + assets/"
mkdir -p "$ROOT/public-spa"
cp -a "$DIST/." "$ROOT/public-spa/"
cp -f "$DIST/index.html" "$ROOT/index.html"
cp -a "$DIST/assets" "$ROOT/assets"

rm -rf "$ROOT/maps"
if [[ -d "$DIST/maps" ]]; then
  cp -a "$DIST/maps" "$ROOT/maps"
  cat > "$ROOT/maps/.htaccess" <<'EOF'
# Serve Africa map GeoJSON as a static file.
<IfModule mod_rewrite.c>
    RewriteEngine Off
</IfModule>
EOF
fi

# Ensure Apache never rewrites inside assets/
cat > "$ROOT/assets/.htaccess" <<'EOF'
# Serve Vite hashed assets as static files only (no rewrite / no SPA fallback).
<IfModule mod_rewrite.c>
    RewriteEngine Off
</IfModule>
EOF

chmod -R a+rX "$ROOT/assets" "$ROOT/public-spa" "$ROOT/index.html" "$ROOT/maps" 2>/dev/null || true

js_count="$(find "$ROOT/assets" -type f -name '*.js' | wc -l | tr -d ' ')"
css_count="$(find "$ROOT/assets" -type f -name '*.css' | wc -l | tr -d ' ')"
sample="$(find "$ROOT/assets" -maxdepth 1 -type f -name '*.js' | head -n 1 || true)"

echo "    index.html  → $ROOT/index.html"
echo "    assets/     → $js_count js, $css_count css"
if [[ -n "$sample" ]]; then
  echo "    sample      → /staff/staff-portal/assets/$(basename "$sample")"
fi
echo "==> Done. Hard-refresh the browser (Ctrl+Shift+R)."
