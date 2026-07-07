#!/usr/bin/env bash
# Run all staff ecosystem upload migrations (copy only; keeps legacy until verified).
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
source "${DIR}/_common.sh"

for script in migrate-ci-uploads.sh migrate-apm-uploads.sh migrate-helpdesk-uploads.sh migrate-staff-portal-uploads.sh; do
  bash "${DIR}/${script}"
done

log "All modules migrated. Update .env files and run storage:link in Laravel apps."
