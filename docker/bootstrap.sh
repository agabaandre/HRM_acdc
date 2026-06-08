#!/usr/bin/env bash
# First-time Docker setup: env file, module .env sync, composer, keys, migrations.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

if [[ ! -f .env ]]; then
    cp docker/compose.env.example .env
    echo "Created .env from docker/compose.env.example — edit DB_* and JWT_SECRET before continuing."
fi

# All modules share one schema; keep CI3 APM connection aligned.
if ! grep -q '^APM_DB_NAME=' .env 2>/dev/null; then
    echo 'APM_DB_NAME=staff' >> .env
elif grep -q '^APM_DB_NAME=bms_new' .env 2>/dev/null || grep -q '^APM_DB_NAME=apm_local' .env 2>/dev/null; then
    sed -i.bak 's/^APM_DB_NAME=.*/APM_DB_NAME=staff/' .env && rm -f .env.bak
    echo "Set APM_DB_NAME=staff (shared schema with CodeIgniter)."
fi

ensure_module_env() {
    local example="$1"
    local target="$2"
    if [[ ! -f "${target}" && -f "${example}" ]]; then
        cp "${example}" "${target}"
        echo "Created ${target} from example."
    fi
}

ensure_module_env apm/.env.example apm/.env
ensure_module_env finance/.env.example finance/.env
ensure_module_env helpdesk/backend/.env.example helpdesk/backend/.env
ensure_module_env staff-portal/.env.example staff-portal/.env

COMPOSE=(docker compose)
BUNDLED=0
if [[ "${1:-}" == "--bundled-mysql" ]]; then
    BUNDLED=1
    shift
    if ! grep -q '^DB_HOST=mysql' .env 2>/dev/null; then
        if grep -q '^DB_HOST=' .env; then
            sed -i.bak 's/^DB_HOST=.*/DB_HOST=mysql/' .env && rm -f .env.bak
        else
            echo 'DB_HOST=mysql' >> .env
        fi
        echo "Set DB_HOST=mysql for bundled MySQL."
    fi
    COMPOSE+=(--profile bundled-mysql)
fi

"${COMPOSE[@]}" up -d --build

if [[ "${BUNDLED}" -eq 1 ]]; then
    echo "Waiting for bundled MySQL to become healthy..."
    for _ in $(seq 1 60); do
        if "${COMPOSE[@]}" ps mysql 2>/dev/null | grep -q '(healthy)'; then
            break
        fi
        sleep 2
    done
fi

echo "Waiting for web container..."
sleep 2

"${COMPOSE[@]}" exec -T web bash -lc '/usr/local/bin/sync-module-env.sh'

for dir in apm finance helpdesk/backend staff-portal; do
    if [[ -f "${dir}/composer.json" ]]; then
        echo "composer install: ${dir}"
        "${COMPOSE[@]}" exec -T web bash -lc "cd ${dir} && composer install --no-interaction --prefer-dist"
    fi
done

"${COMPOSE[@]}" exec -T web bash -lc '
set -e
cd /var/www/html/apm && (grep -q "^APP_KEY=base64:" .env 2>/dev/null || php artisan key:generate --force)
cd /var/www/html/apm && (grep -q "^JWT_SECRET=.\+" .env 2>/dev/null || php artisan jwt:secret --force)
cd /var/www/html/finance && (grep -q "^APP_KEY=base64:" .env 2>/dev/null || php artisan key:generate --force)
cd /var/www/html/helpdesk/backend && (grep -q "^APP_KEY=base64:" .env 2>/dev/null || php artisan key:generate --force)
cd /var/www/html/staff-portal && (grep -q "^APP_KEY=base64:" .env 2>/dev/null || php artisan key:generate --force)
'

if [[ "${SKIP_MIGRATE:-}" != "1" ]]; then
    "${COMPOSE[@]}" exec -T web bash -lc '/usr/local/bin/migrate-all.sh'
fi

# shellcheck disable=SC1090
source <(grep -E '^(APP_PORT|DOCKER_BASE_URL)=' .env 2>/dev/null | sed 's/\r$//') || true
BASE="${DOCKER_BASE_URL:-http://localhost:${APP_PORT:-8080}}"

cat <<EOF

CBP Docker is up.

  Staff (CI):      ${BASE}/
  APM:             ${BASE}/apm/
  Finance:         ${BASE}/finance/
  Helpdesk:        ${BASE}/helpdesk/
  Staff portal:    ${BASE}/staff-portal/

Re-run migrations:  docker compose exec web migrate-all.sh
Sync module .env:   docker compose exec web sync-module-env.sh

EOF
