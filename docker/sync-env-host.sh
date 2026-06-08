#!/usr/bin/env bash
# Sync module .env files from repo root .env when NOT using Docker (host Apache / Valet).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ ! -f "${ROOT}/.env" ]]; then
    echo "Missing ${ROOT}/.env — copy docker/compose.env.example to .env first."
    exit 1
fi

export STAFF_ROOT="${ROOT}"
"${ROOT}/docker/sync-module-env.sh"

echo "sync-env-host: module .env files updated (database: staff, DB_HOST from root .env)."
