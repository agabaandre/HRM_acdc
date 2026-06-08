#!/usr/bin/env bash
# Run all CBP Laravel migrations against the shared staff database.
set -euo pipefail

ROOT="/var/www/html"
cd "${ROOT}"

if [[ -x /usr/local/bin/sync-module-env.sh ]]; then
    /usr/local/bin/sync-module-env.sh || true
fi

run_artisan() {
    local dir="$1"
    shift
    if [[ ! -f "${dir}/artisan" ]]; then
        echo "migrate-all: skip ${dir} (no artisan)"
        return 0
    fi
    echo ""
    echo "========== ${dir} =========="
    (cd "${dir}" && php artisan "$@")
}

# 1. Legacy CI3 DDL (fresh bundled DB only)
if [[ -f staff-portal/artisan ]]; then
    if [[ "${STAFF_LEGACY_SCHEMA_SKIP:-true}" != "true" ]]; then
        echo "migrate-all: installing legacy staff schema from staff-legacy-structure.sql"
        run_artisan staff-portal staff-portal:install-legacy-schema --force || true
    fi

    # 2. staff-portal core + module migrations
    run_artisan staff-portal migrate --force
    run_artisan staff-portal module:migrate --force 2>/dev/null || run_artisan staff-portal module:migrate
fi

# 3. APM (matrices, activities, memos, workflows, …)
run_artisan apm migrate --force

# 4. Finance
run_artisan finance migrate --force

# 5. Helpdesk (helpdesk_* tables)
run_artisan helpdesk/backend migrate --force

echo ""
echo "========== migration status =========="
for dir in staff-portal apm finance helpdesk/backend; do
    if [[ -f "${dir}/artisan" ]]; then
        echo "--- ${dir} ---"
        (cd "${dir}" && php artisan migrate:status --no-ansi 2>/dev/null | tail -n 5) || true
    fi
done

echo ""
echo "migrate-all: done (shared database: ${DB_NAME:-staff})."
