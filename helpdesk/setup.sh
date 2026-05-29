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

export HELPDESK_SETUP_ENV="$SETUP_ENV"
chmod +x "$ROOT/scripts/configure-env.sh" "$ROOT/scripts/install-systemd.sh" 2>/dev/null || true
chmod +x "$ROOT/deploy/systemd/install.sh" "$ROOT/deploy/bin/"*.sh 2>/dev/null || true

echo "==> Configuring backend .env from setup.env"
"$ROOT/scripts/configure-env.sh"

echo "==> Backend (Composer, migrations, seed)"
cd "$ROOT/backend"
composer install --no-interaction
php artisan migrate --no-interaction --force
php artisan storage:link --no-interaction 2>/dev/null || true
php artisan db:seed --no-interaction --force

echo "==> Frontend (npm install + production build)"
cd "$ROOT/frontend"
npm install --cache ./.npm-cache --legacy-peer-deps

# Apply Vite production API base from setup.env when set.
# shellcheck source=/dev/null
source "$ROOT/scripts/lib/dotenv.sh"
dotenv_load_file "$SETUP_ENV"
if [[ -n "${VITE_HELPDESK_API_BASE_URL:-}" ]]; then
  PROD_ENV="$ROOT/frontend/.env.production.local"
  dotenv_set "$PROD_ENV" VITE_HELPDESK_API_BASE_URL "$VITE_HELPDESK_API_BASE_URL"
fi

npm run build

echo "==> Systemd (queue worker + scheduler on Linux)"
"$ROOT/scripts/install-systemd.sh" || true

# shellcheck source=/dev/null
source "$ROOT/scripts/lib/dotenv.sh"
dotenv_load_file "$SETUP_ENV"

echo ""
echo "Helpdesk setup complete."
echo "  SPA:  ${HELPDESK_FRONTEND_URL:-http://localhost/staff/helpdesk/}"
echo "  API:  ${APP_URL:-http://localhost/staff/helpdesk/backend}/api/v1/health"
echo "  Open from Staff home with ?token=… (permission 92 or 93)"
