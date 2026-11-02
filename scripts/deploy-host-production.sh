#!/usr/bin/env bash
# Host-Apache production deploy for the whole CBP staff tree.
#
# - Removes leftover CBP staff Docker Compose containers (does NOT touch
#   email-server / superset / unrelated stacks)
# - Syncs tracked files to origin/main (preserves .env)
# - Runs root ./setup.sh --defaults so EVERY discovered module gets a full
#   production installer (composer, migrate, SPA builds) against host MySQL
#
# Usage (on the server):
#   cd /var/lib/ACDC_SYSTEMS/staff
#   ./scripts/deploy-host-production.sh
#
# Options:
#   --skip-git          Do not fetch/reset (use current tree)
#   --skip-docker-clean Skip removing old cbp-staff containers
#   --migrate-only      Do not force full installers (change-based install)
#   -h, --help
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

SKIP_GIT=0
SKIP_DOCKER_CLEAN=0
FORCE_ALL=1

usage() {
  sed -n '2,20p' "$0" | sed 's/^# \?//'
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-git) SKIP_GIT=1; shift ;;
    --skip-docker-clean) SKIP_DOCKER_CLEAN=1; shift ;;
    --migrate-only) FORCE_ALL=0; shift ;;
    -h|--help) usage; exit 0 ;;
    *)
      echo "error: unknown option: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
done

echo "==> CBP host production deploy"
echo "    root: $ROOT"

# ---------------------------------------------------------------------------
# 1) Retire leftover staff Docker containers (host Apache + host MySQL)
# ---------------------------------------------------------------------------
if [[ "$SKIP_DOCKER_CLEAN" != "1" ]]; then
  echo "==> Removing leftover CBP staff Docker containers (if any)"
  if command -v docker >/dev/null 2>&1; then
    # Prefer compose project teardown when compose files exist.
    if [[ -f "$ROOT/docker-compose.yml" ]]; then
      (cd "$ROOT" && docker compose --profile workers down --remove-orphans 2>/dev/null) || true
      (cd "$ROOT" && docker compose down --remove-orphans 2>/dev/null) || true
    fi
    # Explicit names from prior Compose runs.
    for name in cbp-staff-web-1 cbp-staff-redis-1 cbp-staff-mysql-1 cbp-staff-workers-1; do
      if docker ps -a --format '{{.Names}}' 2>/dev/null | grep -qx "$name"; then
        echo "    docker rm -f $name"
        docker rm -f "$name" 2>/dev/null || true
      fi
    done
    # Any remaining containers labelled for this compose project.
    ids="$(docker ps -aq --filter "label=com.docker.compose.project=staff" 2>/dev/null || true)"
    if [[ -n "${ids:-}" ]]; then
      echo "    docker rm -f (compose project=staff)"
      # shellcheck disable=SC2086
      docker rm -f $ids 2>/dev/null || true
    fi
  else
    echo "    docker not installed — skip"
  fi
else
  echo "==> Skipping Docker cleanup (--skip-docker-clean)"
fi

# ---------------------------------------------------------------------------
# 2) Ensure host deploy mode in root .env (do not wipe other secrets)
# ---------------------------------------------------------------------------
if [[ -f "$ROOT/.env" ]]; then
  if grep -q '^DEPLOY_MODE=' "$ROOT/.env"; then
    sed -i.bak 's/^DEPLOY_MODE=.*/DEPLOY_MODE=host/' "$ROOT/.env" && rm -f "$ROOT/.env.bak"
  else
    printf '\nDEPLOY_MODE=host\n' >>"$ROOT/.env"
  fi
  if grep -q '^SITE_KIND=' "$ROOT/.env"; then
    sed -i.bak 's/^SITE_KIND=.*/SITE_KIND=production/' "$ROOT/.env" && rm -f "$ROOT/.env.bak"
  else
    printf '\nSITE_KIND=production\n' >>"$ROOT/.env"
  fi
  # Host MySQL — never leave Compose DNS names on disk for Apache/php-fpm.
  if grep -qE '^DB_HOST=(mysql|host\.docker\.internal)\s*$' "$ROOT/.env"; then
    sed -i.bak 's/^DB_HOST=.*/DB_HOST=127.0.0.1/' "$ROOT/.env" && rm -f "$ROOT/.env.bak"
    echo "    remapped DB_HOST → 127.0.0.1"
  fi
  echo "    DEPLOY_MODE=host SITE_KIND=production"
else
  echo "warn: $ROOT/.env missing — setup.sh --defaults will require existing secrets" >&2
fi

# ---------------------------------------------------------------------------
# 3) Sync from git
# ---------------------------------------------------------------------------
if [[ "$SKIP_GIT" != "1" ]]; then
  if [[ -x "$ROOT/scripts/production-sync-from-git.sh" ]]; then
    "$ROOT/scripts/production-sync-from-git.sh"
  else
    echo "==> git fetch + hard reset to origin/main"
    git fetch origin main
    git reset --hard origin/main
  fi
else
  echo "==> Skipping git sync (--skip-git)"
fi

# ---------------------------------------------------------------------------
# 4) Full module install via root setup (all discovered modules)
# ---------------------------------------------------------------------------
export SETUP_ASSUME_DEFAULTS=1
export SETUP_SKIP_GIT=1
if [[ "$FORCE_ALL" == "1" ]]; then
  export SETUP_FORCE_ALL_MODULES=1
  echo "==> SETUP_FORCE_ALL_MODULES=1 — full setup-production for every discovered module"
else
  export SETUP_FORCE_ALL_MODULES=0
  echo "==> migrate-only mode — full install only for changed modules"
fi

# Host Apache + external MySQL (defaults mode still asks via prompt defaults;
# production SITE_KIND forces Host Apache as choice 1).
cd "$ROOT"
./setup.sh --defaults --skip-git

echo "==> Host production deploy finished"
echo "    commit: $(git rev-parse --short HEAD 2>/dev/null || echo unknown)"
echo "    modules under modules/ with setup-production / artisan were installed"
echo "    Verify: https://cbp.africacdc.org/staff/"
