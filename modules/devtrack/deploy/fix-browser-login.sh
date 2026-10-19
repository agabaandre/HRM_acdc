#!/usr/bin/env bash
# Fix browser "Server Error" on login when curl to :8089 works.
# Cause: statefulApi() + SANCTUM_STATEFUL_DOMAINS=public-domain → CSRF on browser Origin.
#
#   cd /var/lib/IS/tools/AfricaCDCDevTrack && ./deploy/fix-browser-login.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BOOTSTRAP="$ROOT/backend/bootstrap/app.php"

if [[ ! -f "$BOOTSTRAP" ]]; then
  echo "ERROR: missing $BOOTSTRAP" >&2
  exit 1
fi

echo "==> Patching bootstrap/app.php (disable statefulApi / CSRF on api/*)"
cp "$BOOTSTRAP" "${BOOTSTRAP}.bak.$(date +%s)"

cat > "$BOOTSTRAP" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Bearer-token admin UI — never enable statefulApi() on the public domain.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        $middleware->throttleApi('api');
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
PHP

if [[ -f "$ROOT/backend/.env" ]]; then
  echo "==> Removing public domain from SANCTUM_STATEFUL_DOMAINS"
  if grep -q '^SANCTUM_STATEFUL_DOMAINS=' "$ROOT/backend/.env"; then
    sed -i 's/^SANCTUM_STATEFUL_DOMAINS=.*/SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3006,127.0.0.1/' "$ROOT/backend/.env"
  else
    echo 'SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3006,127.0.0.1' >> "$ROOT/backend/.env"
  fi
fi

rm -f "$ROOT/backend/bootstrap/cache/config.php" \
  "$ROOT/backend/bootstrap/cache/routes-v7.php" \
  "$ROOT/backend/bootstrap/cache/routes.php" 2>/dev/null || true

cd "$ROOT/docker"
echo "==> Recreating app + frontend nginx"
docker compose up -d --force-recreate --no-deps app frontend
sleep 4

DOMAIN="$(grep -E '^APP_URL=' "$ROOT/backend/.env" 2>/dev/null | cut -d= -f2- | tr -d '"' | sed 's|https\?://||')"
DOMAIN="${DOMAIN:-notifications.africacdc.org}"

echo "==> Probe HTTPS login with browser Origin (expect HTTP 422)"
code="$(curl -sS -o /tmp/login_probe.json -w '%{http_code}' \
  -X POST "https://${DOMAIN}/api/v1/admin/auth/login" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H "Origin: https://${DOMAIN}" \
  -H "Referer: https://${DOMAIN}/login" \
  -d '{"email":"probe@example.com","password":"invalid"}' || true)"
echo "HTTP $code"
head -c 300 /tmp/login_probe.json 2>/dev/null || true
echo

if [[ "$code" == "422" ]]; then
  echo "OK — browser login path is fixed. Hard-refresh the UI and sign in."
else
  echo "Still not 422. Check: docker compose logs app --tail 80" >&2
  exit 1
fi
