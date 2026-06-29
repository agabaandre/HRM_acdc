#!/usr/bin/env bash
# Diagnose and fix "Cannot declare class App\Models\Activity, because the name is already in use"
#
# Common causes on production:
#   - Untracked duplicate under apm/app/Models/ (e.g. activity.php, Activity.php.backup)
#   - Activity.php contains the class twice after a bad merge
#   - Stale composer classmap or Laravel route/config cache
#   - PHP OPcache serving an old merged copy of Activity.php
#
# Usage (on production):
#   cd /var/lib/ACDC_SYSTEMS/staff   # or /var/www/html/staff
#   ./scripts/production-fix-apm-activity-duplicate.sh
#
set -euo pipefail

STAFF_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APM_ROOT="${APM_ROOT:-$STAFF_ROOT/apm}"
ACTIVITY_FILE="$APM_ROOT/app/Models/Activity.php"

echo "==> APM Activity duplicate class fix"
echo "    staff root: $STAFF_ROOT"
echo "    apm root:   $APM_ROOT"
echo

if [[ ! -f "$APM_ROOT/artisan" ]]; then
  echo "error: APM not found at $APM_ROOT" >&2
  exit 1
fi

if [[ ! -f "$ACTIVITY_FILE" ]]; then
  echo "error: missing $ACTIVITY_FILE" >&2
  exit 1
fi

echo "==> Activity-related files under apm/app/Models"
find "$APM_ROOT/app/Models" -maxdepth 1 \( -iname '*activity*' -o -iname 'activity.php' \) -print | sort || true
echo

echo "==> class Activity declarations in apm/app (should be exactly one)"
DECLARATIONS="$(rg -n '^class Activity\b' "$APM_ROOT/app" --glob '*.php' 2>/dev/null || true)"
echo "$DECLARATIONS"
DECL_COUNT="$(printf '%s\n' "$DECLARATIONS" | sed '/^$/d' | wc -l | tr -d ' ')"
echo "    count: $DECL_COUNT"
echo

if [[ "$DECL_COUNT" != "1" ]]; then
  echo "error: expected exactly 1 'class Activity' under apm/app — remove or rename extra files above." >&2
  echo "    Untracked duplicates are NOT removed by git reset --hard." >&2
  exit 1
fi

echo "==> class Activity count inside Activity.php (should be 1)"
INNER_COUNT="$(grep -c '^class Activity\b' "$ACTIVITY_FILE" || true)"
echo "    count: $INNER_COUNT"
if [[ "$INNER_COUNT" != "1" ]]; then
  echo "error: Activity.php contains $INNER_COUNT class declarations — restore from git:" >&2
  echo "    cd $STAFF_ROOT && git checkout HEAD -- apm/app/Models/Activity.php" >&2
  exit 1
fi

echo "==> Stray untracked model files to remove (if any)"
STRAY=0
while IFS= read -r f; do
  [[ -z "$f" ]] && continue
  case "$f" in
    "$ACTIVITY_FILE"|"$APM_ROOT/app/Models/ActivityApprovalTrail.php"|"$APM_ROOT/app/Models/ActivityBudget.php")
      continue
      ;;
  esac
  if rg -q '^class Activity\b' "$f" 2>/dev/null; then
    echo "    REMOVE: $f"
    rm -f "$f"
    STRAY=1
  fi
done < <(find "$APM_ROOT/app/Models" -maxdepth 1 -type f \( -iname '*activity*' \) 2>/dev/null)

if [[ "$STRAY" -eq 0 ]]; then
  echo "    (none)"
fi
echo

echo "==> Composer autoload refresh"
(cd "$APM_ROOT" && composer dump-autoload -o)
echo

echo "==> Laravel caches"
(cd "$APM_ROOT" && php artisan optimize:clear)
(cd "$APM_ROOT" && php artisan route:clear)
(cd "$APM_ROOT" && php artisan config:clear)
(cd "$APM_ROOT" && php artisan view:clear)
echo

echo "==> Verify Activity model loads"
(cd "$APM_ROOT" && php artisan tinker --execute="echo App\Models\Activity::class;")
echo

echo "==> Done. Reload the site (restart php-fpm if OPcache still serves an old file):"
echo "    sudo systemctl reload php8.2-fpm   # adjust service name if needed"
