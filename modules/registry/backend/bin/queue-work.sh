#!/usr/bin/env bash
# Local/dev finance queue worker (SAP→APM sync uses the `finance` queue).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
exec php artisan queue:work database \
  --queue=finance,default \
  --sleep=1 \
  --tries=3 \
  --timeout=300 \
  --max-time=0 \
  --no-interaction
