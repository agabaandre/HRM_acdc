#!/usr/bin/env bash
# Reset the production working tree to match GitHub (origin/main).
# Use when server files were edited locally or git pull left merge conflicts.
#
# Preserves: untracked files (e.g. .env). Does NOT run git clean.
#
# Usage (on production):
#   cd /var/lib/ACDC_SYSTEMS/staff
#   ./scripts/production-sync-from-git.sh
#
set -euo pipefail

STAFF_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$STAFF_ROOT"

BRANCH="${PRODUCTION_GIT_BRANCH:-main}"
REMOTE="${PRODUCTION_GIT_REMOTE:-origin}"

echo "==> Fetching ${REMOTE}/${BRANCH} in ${STAFF_ROOT}"
git fetch "$REMOTE" "$BRANCH"

echo "==> Hard reset tracked files to ${REMOTE}/${BRANCH} (server edits on tracked files will be discarded)"
git reset --hard "${REMOTE}/${BRANCH}"

echo "==> Checking for leftover merge conflict markers"
if rg -n '<<<<<<<|=======|>>>>>>>' apm staff-portal helpdesk assets 2>/dev/null \
  --glob '*.php' --glob '*.blade.php' --glob '*.css' --glob '*.js' --glob '*.vue'; then
  echo "error: conflict markers still present after reset — fix manually or re-run fetch." >&2
  exit 1
fi

echo "==> APM: refresh autoload + clear caches"
if [[ -f apm/artisan ]]; then
  (cd apm && composer dump-autoload -o 2>/dev/null || true)
  (cd apm && php artisan view:clear && php artisan cache:clear && php artisan config:clear)
fi

echo "==> Staff portal: clear caches"
if [[ -f staff-portal/artisan ]]; then
  (cd staff-portal && php artisan view:clear && php artisan cache:clear)
fi

echo "==> Done. Deployed commit: $(git rev-parse --short HEAD)"
echo "    Verify APM header:"
echo "    grep -n 'cbp_modules_header_dropdown\\|<<<<<<' apm/resources/views/layouts/partials/header.blade.php"
