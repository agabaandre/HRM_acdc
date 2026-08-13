#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

SETUP_ENV="$ROOT/setup.env"
if [[ ! -f "$SETUP_ENV" ]]; then
  cp "$ROOT/setup.env.example" "$SETUP_ENV"
  echo "Created $SETUP_ENV"
  echo "Edit DB_* (MySQL) and JWT_SECRET, then run: ./setup.sh"
  exit 0
fi

export STAFF_PORTAL_SETUP_ENV="$SETUP_ENV"
chmod +x "$ROOT/scripts/configure-env.sh" "$ROOT/scripts/install-systemd.sh" 2>/dev/null || true
chmod +x "$ROOT/deploy/systemd/install.sh" "$ROOT/deploy/bin/"*.sh 2>/dev/null || true

# Composer disables plugins when run as root unless this is set — that breaks
# wikimedia/composer-merge-plugin (Modules\* autoload) and causes:
#   Class "Modules\AdManager\Providers\AdManagerServiceProvider" not found
if [[ "$(id -u)" -eq 0 ]]; then
  export COMPOSER_ALLOW_SUPERUSER=1
  echo "warning: running as root — COMPOSER_ALLOW_SUPERUSER=1 so module autoload plugins stay enabled" >&2
fi

echo "==> Configuring backend .env from setup.env"
"$ROOT/scripts/configure-env.sh"

echo "==> Backend (Composer, migrations)"
cd "$ROOT/backend"
composer install --no-interaction
composer dump-autoload -o --no-interaction
# shellcheck source=/dev/null
source "$ROOT/scripts/lib/dotenv.sh"
if ! php -r 'require "vendor/autoload.php"; exit(class_exists("Modules\\AdManager\\Providers\\AdManagerServiceProvider") ? 0 : 1);'; then
  echo "error: Modules autoload missing after composer install (merge-plugin)." >&2
  echo "Fix: cd backend && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o" >&2
  echo "Avoid: sudo composer … without COMPOSER_ALLOW_SUPERUSER=1" >&2
  exit 1
fi
if [[ -z "$(dotenv_get .env APP_KEY 2>/dev/null || true)" ]]; then
  php artisan key:generate --no-interaction
fi
php artisan migrate --no-interaction --force
php artisan module:migrate --no-interaction --force 2>/dev/null || php artisan module:migrate --force
php artisan storage:link --no-interaction 2>/dev/null || true

echo "==> Frontend (npm install + production build)"
cd "$ROOT/frontend"
npm install --cache ./.npm-cache --legacy-peer-deps

# shellcheck source=/dev/null
source "$ROOT/scripts/lib/dotenv.sh"
dotenv_load_file "$SETUP_ENV"
PROD_ENV="$ROOT/frontend/.env.production.local"
VITE_ENV_PREEXISTED=0
[[ -f "$PROD_ENV" ]] && VITE_ENV_PREEXISTED=1
if [[ -n "${VITE_STAFF_PORTAL_API_BASE_URL:-}" ]]; then
  dotenv_apply_if_missing "$PROD_ENV" VITE_STAFF_PORTAL_API_BASE_URL \
    "$VITE_STAFF_PORTAL_API_BASE_URL" "$VITE_ENV_PREEXISTED"
fi
if [[ -n "${VITE_STAFF_PORTAL_BASE_PATH:-}" ]]; then
  dotenv_apply_if_missing "$PROD_ENV" VITE_STAFF_PORTAL_BASE_PATH \
    "$VITE_STAFF_PORTAL_BASE_PATH" "$VITE_ENV_PREEXISTED"
fi

npm run build

echo "==> Shared file storage (CI3 + APM → host path outside git)"
chmod +x "$ROOT/scripts/migrate-shared-storage.sh" 2>/dev/null || true
"$ROOT/scripts/migrate-shared-storage.sh" || true

echo "==> Systemd (queue worker + scheduler on Linux)"
"$ROOT/scripts/install-systemd.sh" || true

# shellcheck source=/dev/null
source "$ROOT/scripts/lib/dotenv.sh"
dotenv_load_file "$SETUP_ENV"

echo ""
echo "Staff Portal setup complete."
echo "  SPA:  ${STAFF_PORTAL_SPA_URL:-http://localhost/staff/staff-portal/}"
echo "  API:  ${APP_URL:-http://localhost/staff/staff-portal/backend}/up"
echo "  Dev:  npm run dev:all   (API :8081 + Vite :5175)"
