#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/paths.sh
source "$ROOT/scripts/lib/paths.sh"
staff_paths_resolve_from_module "$ROOT"

for f in composer.json artisan package.json public/index.php; do
  if [[ ! -f "$ROOT/$f" ]]; then
    echo "error: missing finance/$f — deploy the full finance/ module from git (cd ${STAFF_ROOT:-$ROOT/..} && git pull)" >&2
    exit 1
  fi
done

SETUP_ENV="$ROOT/setup.env"
if [[ ! -f "$SETUP_ENV" ]]; then
  cp "$ROOT/setup.env.example" "$SETUP_ENV"
  echo "Created $SETUP_ENV"
fi

export FINANCE_SETUP_ENV="$SETUP_ENV"
chmod +x "$ROOT/scripts/configure-env.sh" "$ROOT/fix-storage-permissions.sh" 2>/dev/null || true

echo "==> Configuring .env from setup.env"
"$ROOT/scripts/configure-env.sh"

echo "==> Backend (Composer, migrations)"
composer install --no-interaction
# shellcheck source=/dev/null
source "$ROOT/scripts/lib/dotenv.sh"
if [[ -z "$(dotenv_get .env APP_KEY 2>/dev/null || true)" ]]; then
  php artisan key:generate --no-interaction
fi
php artisan migrate --no-interaction --force
./fix-storage-permissions.sh || echo "Warning: run ./fix-storage-permissions.sh with sudo if Apache cannot write sessions/logs."

echo "==> Frontend (npm install + production build)"
npm install --legacy-peer-deps --cache ./.npm-cache
npm run build

echo ""
echo "Finance (Laravel + Inertia) ready."
echo "Open: http://localhost/staff/finance/?token=… (from Staff home)"
echo "Production: ./setup-production.sh"
