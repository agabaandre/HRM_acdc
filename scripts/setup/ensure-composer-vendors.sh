#!/usr/bin/env bash
# Ensure gitignored vendor/ trees exist for every CBP Laravel app Supervisors/queues use.
# "Failed opening required …/vendor/autoload.php" means Composer was never run here —
# not a file-permissions problem.
#
# Usage (on production / local):
#   cd /path/to/staff
#   ./scripts/setup/ensure-composer-vendors.sh
# Docker deploy (Composer inside Compose `web`):
#   DEPLOY_MODE=docker ./scripts/setup/ensure-composer-vendors.sh
#   # or: STAFF_COMPOSER_VIA_DOCKER=1 ./scripts/setup/ensure-composer-vendors.sh
set -euo pipefail

STAFF_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$STAFF_ROOT"
export STAFF_ROOT

# shellcheck source=docker-compose.sh
source "$STAFF_ROOT/scripts/setup/docker-compose.sh"

# Persist preference from root .env when not already set.
if [[ -z "${DEPLOY_MODE:-}" && -f "$STAFF_ROOT/.env" ]]; then
  _dm="$(grep -E '^DEPLOY_MODE=' "$STAFF_ROOT/.env" | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  [[ -n "$_dm" ]] && DEPLOY_MODE="$_dm"
  export DEPLOY_MODE
fi

if staff_use_docker_composer; then
  echo "Composer via Docker Compose (web service)"
  export STAFF_COMPOSER_VIA_DOCKER=1
  # Prefer wrapper on PATH so nested tools see the same behaviour.
  export PATH="$STAFF_ROOT/scripts/setup/bin:$PATH"
else
  if [[ "$(id -u)" -eq 0 ]]; then
    export COMPOSER_ALLOW_SUPERUSER=1
  fi
  if ! command -v composer >/dev/null 2>&1; then
    echo "error: composer not found on PATH (set DEPLOY_MODE=docker to use Compose)" >&2
    exit 1
  fi
fi

# Auto-discover Laravel apps under modules/ (composer.json + artisan).
APPS=()
shopt -s nullglob
for _mod in "$STAFF_ROOT"/modules/*/; do
  _name="$(basename "$_mod")"
  [[ "$_name" == .* ]] && continue
  if [[ -f "$_mod/cbp-module.json" ]] \
    && grep -Eq '"root_setup"[[:space:]]*:[[:space:]]*false' "$_mod/cbp-module.json" 2>/dev/null; then
    continue
  fi
  if [[ -f "$_mod/backend/composer.json" && -f "$_mod/backend/artisan" ]]; then
    APPS+=("modules/$_name/backend")
  fi
  if [[ -f "$_mod/composer.json" && -f "$_mod/artisan" ]]; then
    APPS+=("modules/$_name")
  fi
done
shopt -u nullglob
unset _mod _name
if [[ ${#APPS[@]} -eq 0 ]]; then
  echo "warn: no Laravel apps discovered under modules/" >&2
fi

# shellcheck source=ensure-writable-app-dirs.sh
source "$STAFF_ROOT/scripts/setup/ensure-writable-app-dirs.sh"

failed=0
for rel in "${APPS[@]}"; do
  dir="$STAFF_ROOT/$rel"
  [[ -f "$dir/composer.json" ]] || continue
  # Always ensure vendor is writable — partial/root-owned vendor breaks composer update.
  ensure_writable_app_dirs "$dir" || true
  if [[ -f "$dir/vendor/autoload.php" ]]; then
    echo "OK  $rel (vendor present)"
    continue
  fi
  echo "==> composer install — $rel"
  if (cd "$dir" && staff_composer install --no-dev --optimize-autoloader --no-interaction); then
    if [[ -f "$dir/vendor/autoload.php" ]]; then
      echo "OK  $rel"
    else
      echo "FAIL $rel (autoload.php still missing)" >&2
      failed=1
    fi
  else
    echo "    repairing ownership and retrying $rel"
    ensure_writable_app_dirs "$dir" --gui || true
    if (cd "$dir" && staff_composer install --no-dev --optimize-autoloader --no-interaction) \
      && [[ -f "$dir/vendor/autoload.php" ]]; then
      echo "OK  $rel (after ownership repair)"
    else
      echo "FAIL $rel (composer install exited non-zero)" >&2
      echo "     run: sudo chown -R \$(whoami):www-data $dir/vendor" >&2
      failed=1
    fi
  fi
done

if [[ "$failed" -ne 0 ]]; then
  echo "error: one or more vendor installs failed" >&2
  exit 1
fi

echo "Done."
if staff_use_docker_composer; then
  echo "  Restart Compose workers if needed:"
  echo "  docker compose --env-file docker/.env --profile workers up -d"
else
  echo "  Restart finance workers if they were FATAL:"
  echo "  sudo supervisorctl restart cbp-staff-finance-queue cbp-staff-finance-scheduler"
fi
