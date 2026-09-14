#!/usr/bin/env bash
# Install Composer deps for each CBP Laravel app (build gate).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

MODULES=(
  modules/staff-portal/backend
  modules/apm
  modules/finance
  modules/helpdesk/backend
)

for dir in "${MODULES[@]}"; do
  echo "==> composer install in ${dir}"
  if [[ ! -f "${dir}/composer.json" ]]; then
    echo "error: missing ${dir}/composer.json" >&2
    exit 1
  fi
  (
    cd "${dir}"
    composer install --no-interaction --prefer-dist --no-progress
  )
done

echo "==> composer-modules: OK"
