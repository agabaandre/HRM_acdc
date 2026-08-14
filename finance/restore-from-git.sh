#!/usr/bin/env bash
#
# Restore the Finance Laravel app from the staff git repository.
# Keeps local finance/.env (not in git). Use when setup reports missing composer.json.
#
#   cd /var/lib/ACDC_SYSTEMS/staff/finance
#   ./restore-from-git.sh
#
set -euo pipefail

FINANCE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAFF_ROOT="$(cd "$FINANCE_ROOT/.." && pwd)"

log() { printf '==> %s\n' "$*"; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

[[ -d "$STAFF_ROOT/.git" ]] || die "$STAFF_ROOT is not a git repository — clone the full staff repo first."

cd "$STAFF_ROOT"

BACKUP_DIR="$(mktemp -d)"
cleanup() { rm -rf "$BACKUP_DIR"; }
trap cleanup EXIT

for f in finance/.env finance/setup.env; do
    if [[ -f "$f" ]]; then
        cp -a "$f" "$BACKUP_DIR/$(basename "$f")"
        log "Backed up $f"
    fi
done

if [[ -d finance/storage ]]; then
    cp -a finance/storage "$BACKUP_DIR/storage" 2>/dev/null || true
fi

log "Fetching latest from git (origin)"
git fetch origin 2>/dev/null || true

BRANCH="$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo main)"
log "Restoring finance/ from git ($BRANCH)"
if ! git checkout HEAD -- finance/ 2>/dev/null; then
    git pull origin "$BRANCH" -- finance/ || git pull -- finance/ || die "git could not restore finance/ — check network and branch"
    git checkout HEAD -- finance/ 2>/dev/null || true
fi

if [[ ! -f finance/composer.json ]]; then
    die "finance/composer.json still missing after git checkout.

Push finance from your dev machine, then on the server:
  git fetch origin && git checkout origin/$BRANCH -- finance/
  test -f finance/composer.json"
fi

if [[ -f "$BACKUP_DIR/.env" ]]; then
    cp -a "$BACKUP_DIR/.env" finance/.env
    log "Restored finance/.env"
fi
if [[ -f "$BACKUP_DIR/setup.env" ]]; then
    cp -a "$BACKUP_DIR/setup.env" finance/setup.env
    log "Restored finance/setup.env"
fi

chmod +x finance/setup-production.sh finance/setup.sh finance/fix-storage-permissions.sh 2>/dev/null || true
chmod +x finance/restore-from-git.sh 2>/dev/null || true

log "Finance app restored ($(git ls-files finance/composer.json finance/artisan | wc -l | tr -d ' ') key paths OK)"
echo ""
echo "Next: cd finance && ./setup-production.sh"
