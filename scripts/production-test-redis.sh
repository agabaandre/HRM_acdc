#!/usr/bin/env bash
# Test Redis connectivity and exercise Helpdesk cache keys on production.
#
# Checks:
#   - redis-cli PING (when redis-cli is available)
#   - Laravel Redis connections (default + cache)
#   - Cache read/write on configured CACHE_STORE (and explicit redis store)
#   - helpdesk_reference_staff_v1_* staff directory cache (duty station data)
#
# Usage (on production):
#   cd /var/lib/ACDC_SYSTEMS/staff
#   ./scripts/production-test-redis.sh
#   ./scripts/production-test-redis.sh --verbose
#   ./scripts/production-test-redis.sh --warm-staff-cache   # repopulate staff cache from Share API
#
# Options:
#   --warm-staff-cache   Call ReferenceDataSyncService (needs Staff API credentials in .env)
#   --verbose, -v        Print sample duty stations from cached staff rows
#   --help, -h           Show this help
#
set -euo pipefail

STAFF_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HELPDESK_BACKEND="${HELPDESK_BACKEND:-$STAFF_ROOT/helpdesk/backend}"
PROBE_PHP="$STAFF_ROOT/scripts/lib/helpdesk-redis-probe.php"

WARM=0
VERBOSE=0

usage() {
  sed -n '2,20p' "$0" | sed 's/^# \?//'
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --warm-staff-cache) WARM=1; shift ;;
    --verbose|-v) VERBOSE=1; shift ;;
    --help|-h) usage; exit 0 ;;
    *)
      echo "error: unknown option: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

if [[ ! -f "$HELPDESK_BACKEND/artisan" ]]; then
  echo "error: Helpdesk backend not found at $HELPDESK_BACKEND" >&2
  exit 1
fi

if [[ ! -f "$PROBE_PHP" ]]; then
  echo "error: missing probe script $PROBE_PHP" >&2
  exit 1
fi

mask_env() {
  sed -E 's/^(REDIS_PASSWORD|.*_PASSWORD|.*_SECRET|.*_TOKEN)=.*/\1=***masked***/'
}

read_env_var() {
  local file="$1" key="$2"
  if [[ ! -f "$file" ]]; then
    return 1
  fi
  local line
  line="$(grep -E "^${key}=" "$file" | tail -1 || true)"
  [[ -n "$line" ]] || return 1
  local val="${line#*=}"
  val="${val%\"}"
  val="${val#\"}"
  val="${val%\'}"
  val="${val#\'}"
  printf '%s' "$val"
}

echo "==> Production Redis / cache test"
echo "    staff root:     $STAFF_ROOT"
echo "    helpdesk backend: $HELPDESK_BACKEND"
echo

ENV_FILE="$HELPDESK_BACKEND/.env"
if [[ -f "$ENV_FILE" ]]; then
  echo "==> Helpdesk .env (secrets masked)"
  grep -E '^(CACHE_STORE|CACHE_PREFIX|QUEUE_CONNECTION|REDIS_|HELPDESK_TICKET_READ_CACHE|HELPDESK_REFERENCE_CACHE|HELPDESK_STAFF_API_)' "$ENV_FILE" \
    | mask_env \
    | sort || true
  echo
else
  echo "warn: $ENV_FILE not found — Laravel will use defaults / exported env only" >&2
  echo
fi

REDIS_HOST="$(read_env_var "$ENV_FILE" REDIS_HOST 2>/dev/null || echo '127.0.0.1')"
REDIS_PORT="$(read_env_var "$ENV_FILE" REDIS_PORT 2>/dev/null || echo '6379')"
REDIS_PASSWORD="$(read_env_var "$ENV_FILE" REDIS_PASSWORD 2>/dev/null || true)"
REDIS_CACHE_DB="$(read_env_var "$ENV_FILE" REDIS_CACHE_DB 2>/dev/null || echo '1')"
REDIS_DB="$(read_env_var "$ENV_FILE" REDIS_DB 2>/dev/null || echo '0')"

echo "==> redis-cli (host=$REDIS_HOST port=$REDIS_PORT)"
if command -v redis-cli >/dev/null 2>&1; then
  redis_cli_args=(-h "$REDIS_HOST" -p "$REDIS_PORT")
  if [[ -n "$REDIS_PASSWORD" && "$REDIS_PASSWORD" != "null" ]]; then
    redis_cli_args+=(-a "$REDIS_PASSWORD" --no-auth-warning)
  fi

  for db in "$REDIS_DB" "$REDIS_CACHE_DB"; do
    if out="$(redis-cli "${redis_cli_args[@]}" -n "$db" PING 2>&1)"; then
      echo "[OK] redis-cli PING db=$db → $out"
    else
      echo "[FAIL] redis-cli PING db=$db → $out" >&2
    fi
  done

  echo
  echo "==> redis-cli INFO (memory + keyspace, db $REDIS_CACHE_DB)"
  redis-cli "${redis_cli_args[@]}" -n "$REDIS_CACHE_DB" INFO memory 2>/dev/null | grep -E '^(used_memory_human|maxmemory_human):' || true
  redis-cli "${redis_cli_args[@]}" -n "$REDIS_CACHE_DB" INFO keyspace 2>/dev/null | grep -E '^db' || echo "    (no keyspace stats — db may be empty)"
  echo
else
  echo "    skip: redis-cli not installed (Laravel probe still runs below)"
  echo
fi

PROBE_ARGS=()
[[ "$WARM" -eq 1 ]] && PROBE_ARGS+=(--warm-staff-cache)
[[ "$VERBOSE" -eq 1 ]] && PROBE_ARGS+=(--verbose)

echo "==> Laravel probe"
export HELPDESK_BACKEND_ROOT="$HELPDESK_BACKEND"
if php "$PROBE_PHP" ${PROBE_ARGS[@]+"${PROBE_ARGS[@]}"}; then
  echo
  echo "==> Done. Redis and Helpdesk cache look healthy."
  exit 0
fi

echo
echo "==> Done with failures."
echo "    Tips:"
echo "    - Ensure REDIS_HOST / REDIS_PASSWORD in helpdesk/backend/.env match the running Redis instance."
echo "    - Set CACHE_STORE=redis in production if you expect Laravel cache in Redis (not database)."
echo "    - Run with --warm-staff-cache after Staff API credentials are configured, or use Helpdesk Settings → Directory sync."
exit 1
