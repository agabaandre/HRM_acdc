#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate --no-interaction
fi

composer install --no-interaction
php artisan migrate --no-interaction --force
chmod +x fix-storage-permissions.sh
./fix-storage-permissions.sh || echo "Warning: run ./fix-storage-permissions.sh with sudo if Apache cannot write sessions/logs."
npm install --legacy-peer-deps --cache ./.npm-cache
npm run build

echo "Finance (Laravel + Inertia) ready."
echo "Open: http://localhost/staff/finance/?token=… (from Staff home)"
