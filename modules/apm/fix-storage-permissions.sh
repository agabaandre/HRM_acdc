#!/usr/bin/env bash
# Fix APM Laravel storage only (do not re-run the full multi-app fixer).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
exec "$ROOT/scripts/fix-laravel-storage-permissions.sh" "modules/apm"
