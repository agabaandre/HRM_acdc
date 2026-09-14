#!/usr/bin/env bash
# Delegate to the repo-wide Laravel storage permission fixer.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec "$ROOT/scripts/fix-laravel-storage-permissions.sh"
