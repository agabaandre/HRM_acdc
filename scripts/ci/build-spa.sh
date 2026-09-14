#!/usr/bin/env bash
# Build staff-portal Vue SPA and publish into public-spa/ for Docker bake / Apache.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FRONTEND="${ROOT}/modules/staff-portal/frontend"
PUBLISH="${ROOT}/modules/staff-portal/scripts/publish-spa.sh"

cd "$FRONTEND"

if [[ -f package-lock.json ]]; then
  echo "==> npm ci (staff-portal frontend)"
  npm ci --legacy-peer-deps
else
  echo "==> npm install (no package-lock.json)"
  npm install --legacy-peer-deps
fi

echo "==> npm run build"
npm run build

if [[ ! -x "$PUBLISH" ]]; then
  chmod +x "$PUBLISH"
fi

echo "==> publish-spa.sh"
"$PUBLISH" "${FRONTEND}/dist-build"

if [[ ! -f "${ROOT}/modules/staff-portal/public-spa/index.html" ]]; then
  echo "error: public-spa/index.html missing after publish" >&2
  exit 1
fi

echo "==> build-spa: OK"
