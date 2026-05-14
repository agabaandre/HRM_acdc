#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT/backend"
if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate --no-interaction
fi
composer install --no-interaction
php artisan migrate --no-interaction --force
php artisan db:seed --no-interaction --force
echo "Backend ready. Run: composer run dev (from backend/)"
cd "$ROOT/frontend"
npm install --cache ./.npm-cache --legacy-peer-deps
echo "Frontend deps installed. Run: npm run dev"
