#!/usr/bin/env bash
# Run all Laravel migrations against shared staff DB on the host (no Docker).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}"

"${ROOT}/docker/sync-env-host.sh"

run() {
    local dir="$1"
    shift
    [[ -f "${dir}/artisan" ]] || return 0
    echo ""
    echo "========== ${dir} =========="
    (cd "${dir}" && php artisan "$@")
}

if [[ "${STAFF_LEGACY_SCHEMA_SKIP:-true}" != "true" ]] && [[ -f staff-portal/artisan ]]; then
    run staff-portal staff-portal:install-legacy-schema --force || true
fi

[[ -f staff-portal/artisan ]] && run staff-portal migrate --force
[[ -f staff-portal/artisan ]] && run staff-portal module:migrate --force 2>/dev/null || run staff-portal module:migrate
run apm migrate --force
run finance migrate --force
run helpdesk/backend migrate --force

echo ""
echo "migrate-host: done (database: staff)."
