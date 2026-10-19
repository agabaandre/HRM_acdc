#!/usr/bin/env bash
# Fix MissingAppKeyException / health app_key degraded.
# Handles duplicate APP_KEY= lines (empty first line wins in Dotenv).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> APP_KEY lines in backend/.env (count matters — duplicates break Laravel)"
grep -nE '^APP_KEY=' backend/.env 2>/dev/null | sed 's/=.*/=[redacted]/' || echo "(none)"

# Prefer an existing base64 key; otherwise generate
_key="$(grep -E '^APP_KEY=base64:.+' backend/.env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '\r' || true)"
if [[ -z "$_key" ]]; then
  _key="base64:$(openssl rand -base64 32 | tr -d '\n')"
  echo "==> Generated new APP_KEY"
else
  echo "==> Reusing existing base64 APP_KEY"
fi

# Collapse to a single APP_KEY line (critical)
grep -vE '^APP_KEY=' backend/.env > backend/.env.appkey.tmp 2>/dev/null || cp backend/.env backend/.env.appkey.tmp
printf 'APP_KEY=%s\n' "$_key" >> backend/.env.appkey.tmp
mv backend/.env.appkey.tmp backend/.env
chmod 600 backend/.env 2>/dev/null || sudo chmod 600 backend/.env || true

# Remove empty APP_KEY from docker/.env so compose never injects a blank override
if [[ -f docker/.env ]] && grep -qE '^APP_KEY=' docker/.env; then
  echo "==> Removing APP_KEY from docker/.env (must live only in backend/.env)"
  grep -vE '^APP_KEY=' docker/.env > docker/.env.tmp && mv docker/.env.tmp docker/.env
  chmod 600 docker/.env 2>/dev/null || true
fi

echo "==> Clear bootstrap config cache"
rm -f backend/bootstrap/cache/config.php \
  backend/bootstrap/cache/services.php \
  backend/bootstrap/cache/routes.php \
  backend/bootstrap/cache/routes-v7.php 2>/dev/null || true

cd docker
echo "==> Recreate app + queue (entrypoint exports APP_KEY into php-fpm)"
docker compose up -d --force-recreate --no-deps app
docker compose up -d --force-recreate --no-deps --scale "queue=${QUEUE_SCALE:-1}" queue 2>/dev/null \
  || docker compose up -d --force-recreate --no-deps queue || true
sleep 5

docker compose exec -T app php artisan config:clear || true
docker compose exec -T app php artisan route:clear || true

echo "==> Verify .env (exactly one APP_KEY line)"
docker compose exec -T app sh -c 'grep -cE "^APP_KEY=" .env; grep -E "^APP_KEY=base64:" .env | sed "s/=.*/=[ok]/"'

echo "==> Verify Laravel config + encrypt"
docker compose exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$key = (string) config("app.key");
$env = (string) env("APP_KEY");
echo "config_app_key_len=".strlen($key).PHP_EOL;
echo "env_app_key_len=".strlen($env).PHP_EOL;
echo "config_starts_base64=".(str_starts_with($key, "base64:") ? "yes" : "no").PHP_EOL;
try {
  $c = encrypt("probe");
  echo decrypt($c) === "probe" ? "encrypt_ok\n" : "encrypt_mismatch\n";
} catch (Throwable $e) {
  echo "encrypt_fail: ".$e->getMessage().PHP_EOL;
  exit(1);
}
'

echo "==> Public health"
curl -s "https://notifications.africacdc.org/api/v1/health" | head -c 500 || true
echo
echo "Done. Retry Add provider + /api/documentation."
