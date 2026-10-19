#!/usr/bin/env bash
# Fix production login 500s caused by MySQL password drift / missing seed.
# Run on the server from the repo root:
#   ./deploy/fix-production-login.sh
#
# Optional:
#   ADMIN_PASSWORD='...' ./deploy/fix-production-login.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT/docker"

if [[ ! -f .env ]]; then
  echo "ERROR: docker/.env missing" >&2
  exit 1
fi

# shellcheck disable=SC1091
set -a
# Prefer source for special chars; strip export of comments
source .env
set +a

echo "==> Ensuring MYSQL_HOST_PORT is not conflicting with host :3306"
if ! grep -q '^MYSQL_HOST_PORT=' .env; then
  echo 'MYSQL_HOST_PORT=3309' >> .env
else
  sed -i 's/^MYSQL_HOST_PORT=.*/MYSQL_HOST_PORT=3309/' .env
fi

DATA_PATH="${EMAIL_SERVER_DATA_PATH:-/home/email_serverdata}"
DB_PASSWORD="${DB_PASSWORD:?DB_PASSWORD missing in docker/.env}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:?MYSQL_ROOT_PASSWORD missing in docker/.env}"
ADMIN_EMAIL="${ADMIN_EMAIL:-andrewa@africacdc.org}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"

echo "==> Recreating mysql/app/queue with current docker/.env"
docker compose up -d mysql
sleep 3

laravel_db_ok() {
  docker compose exec -T app php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    Illuminate\Support\Facades\DB::connection()->getPdo();
    Illuminate\Support\Facades\DB::select("select 1");
    echo "ok\n";
  ' 2>/dev/null
}

if ! laravel_db_ok; then
  echo "==> Laravel cannot reach MySQL — wiping ${DATA_PATH}/mysql and re-initializing"
  docker compose stop mysql || true
  docker compose rm -f mysql || true
  sudo rm -rf "${DATA_PATH}/mysql"
  sudo mkdir -p "${DATA_PATH}/mysql"
  sudo chown -R 999:999 "${DATA_PATH}/mysql" || true
  docker compose up -d --force-recreate mysql
  echo "==> Waiting for MySQL healthy..."
  for i in $(seq 1 40); do
    if docker compose ps mysql | grep -qi healthy; then
      break
    fi
    sleep 3
  done
  docker compose up -d --force-recreate app queue
  sleep 6
fi

if ! laravel_db_ok; then
  echo "ERROR: Laravel still cannot connect after MySQL wipe." >&2
  echo "Check DB_PASSWORD / MYSQL_ROOT_PASSWORD in docker/.env match each other." >&2
  docker compose logs mysql --tail 40 || true
  exit 1
fi

echo "==> Migrations"
docker compose exec -T app php artisan migrate --force

if [[ -z "$ADMIN_PASSWORD" ]]; then
  echo "WARNING: ADMIN_PASSWORD empty — set it in docker/.env or export ADMIN_PASSWORD=... then re-run seed"
else
  echo "==> Seeding / resetting admin ${ADMIN_EMAIL}"
  docker compose exec -T \
    -e ADMIN_EMAIL="$ADMIN_EMAIL" \
    -e ADMIN_PASSWORD="$ADMIN_PASSWORD" \
    -e ADMIN_RESET_PASSWORD=true \
    -e RUN_SEEDER=true \
    app php artisan db:seed --force
fi

echo "==> Health"
curl -fsS "http://127.0.0.1:${API_HOST_PORT:-8089}/api/v1/health" || true
echo
echo "Done. Try signing in again."
