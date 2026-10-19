#!/usr/bin/env bash
# Diagnose empty providers list after a successful create.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DOMAIN="${DOMAIN:-notifications.africacdc.org}"
cd "$ROOT/docker"

echo "==> Count rows in DB"
docker compose exec -T app php artisan tinker --execute="echo App\Models\EmailProvider::query()->count().PHP_EOL;"

echo "==> API list status (need admin token for 200 — this is unauthenticated probe)"
curl -sI "https://${DOMAIN}/api/v1/admin/email-providers" | head -n 8 || true

echo "==> Try transforming each provider (decrypt)"
docker compose exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (App\Models\EmailProvider::query()->orderBy("id")->get() as $p) {
  try {
    $cfg = $p->config;
    echo "id={$p->id} name={$p->name} decrypt=ok keys=".count((array)$cfg).PHP_EOL;
  } catch (Throwable $e) {
    echo "id={$p->id} name={$p->name} decrypt=FAIL ".$e->getMessage().PHP_EOL;
  }
}
'

echo "==> APP_KEY lengths"
docker compose exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "config=".strlen((string)config("app.key"))." env=".strlen((string)env("APP_KEY")).PHP_EOL;
'
