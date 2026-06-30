#!/usr/bin/env bash
# Diagnose and fix "Cannot declare class App\Models\Activity, because the name is already in use"
#
# Common causes on production (Linux):
#   - Untracked duplicate: app/Models/activity.php AND app/Models/Activity.php (macOS deploys often miss this)
#   - Activity.php.backup / Activity copy.php under app/ with the same class inside
#   - Activity.php contains the class twice after a bad merge
#   - PHP-FPM OPcache still serving an old broken copy (CLI/tinker passes, web fails)
#
# Usage (on production):
#   cd /var/lib/ACDC_SYSTEMS/staff   # or /var/www/html/staff
#   ./scripts/production-fix-apm-activity-duplicate.sh
#
set -euo pipefail

STAFF_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APM_ROOT="${APM_ROOT:-$STAFF_ROOT/apm}"
CANONICAL="$APM_ROOT/app/Models/Activity.php"

echo "==> APM Activity duplicate class fix"
echo "    staff root: $STAFF_ROOT"
echo "    apm root:   $APM_ROOT"
echo

if [[ ! -f "$APM_ROOT/artisan" ]]; then
  echo "error: APM not found at $APM_ROOT" >&2
  exit 1
fi

cd "$STAFF_ROOT"

echo "==> Restore canonical Activity.php from git (fixes duplicated class in one file)"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1 && git cat-file -e HEAD:apm/app/Models/Activity.php 2>/dev/null; then
  git checkout HEAD -- apm/app/Models/Activity.php
  GIT_MD5="$(git show HEAD:apm/app/Models/Activity.php | md5sum | awk '{print $1}')"
  DISK_MD5="$(md5sum "$CANONICAL" | awk '{print $1}')"
  echo "    git md5:  $GIT_MD5"
  echo "    disk md5: $DISK_MD5"
else
  echo "    warn: not a git repo or Activity.php missing from HEAD — skipping git restore"
fi
echo

echo "==> All files under apm/app declaring 'class Activity' (must be exactly one)"
CANONICAL_REAL="$(php -r "echo realpath('$CANONICAL');")"
FOUND=0
REMOVED=0

while IFS= read -r f; do
  [[ -z "$f" ]] || [[ ! -f "$f" ]] && continue
  if command -v rg >/dev/null 2>&1; then
    has_class=$(rg -q '^class Activity\b' "$f" 2>/dev/null && echo yes || echo no)
  else
    has_class=$(grep -q '^class Activity[[:space:]]' "$f" 2>/dev/null && echo yes || echo no)
  fi
  if [[ "$has_class" != "yes" ]]; then
    continue
  fi
  REAL="$(php -r "echo realpath('$f');")"
  if [[ "$REAL" == "$CANONICAL_REAL" ]]; then
    echo "    KEEP  $f"
    FOUND=$((FOUND + 1))
  else
    echo "    REMOVE $f"
    rm -f "$f"
    REMOVED=$((REMOVED + 1))
  fi
done < <(find "$APM_ROOT/app" -type f -name '*.php' -print 2>/dev/null | sort)

echo "    remaining declarations: $FOUND (removed $REMOVED)"
echo

echo "==> Case-sensitive check in app/Models (Linux: activity.php vs Activity.php)"
LOWER="$APM_ROOT/app/Models/activity.php"
if [[ -f "$LOWER" && -f "$CANONICAL" ]]; then
  CANONICAL_INODE="$(stat -c %i "$CANONICAL" 2>/dev/null || stat -f %i "$CANONICAL")"
  LOWER_INODE="$(stat -c %i "$LOWER" 2>/dev/null || stat -f %i "$LOWER")"
  if [[ "$CANONICAL_INODE" != "$LOWER_INODE" ]]; then
    echo "    REMOVE lowercase duplicate: app/Models/activity.php (inode $LOWER_INODE)"
    rm -f "$LOWER"
  else
    echo "    (activity.php shares inode with Activity.php — same file on this filesystem)"
  fi
fi
ls -la "$APM_ROOT/app/Models/" 2>/dev/null | grep -i activity || true
echo

INNER_COUNT="$(grep -c '^class Activity\b' "$CANONICAL" || true)"
echo "==> class Activity count inside canonical Activity.php: $INNER_COUNT"
if [[ "$INNER_COUNT" != "1" ]]; then
  echo "error: Activity.php still has $INNER_COUNT declarations after git restore." >&2
  exit 1
fi
echo

echo "==> Composer autoload refresh"
(cd "$APM_ROOT" && composer dump-autoload -o 2>&1 | tail -5)
echo

echo "==> Laravel caches"
(cd "$APM_ROOT" && php artisan optimize:clear)
echo

echo "==> CLI load test"
(cd "$APM_ROOT" && php artisan tinker --execute="echo App\Models\Activity::class.PHP_EOL;")
echo

echo "==> OPcache (CLI)"
php -r '
if (function_exists("opcache_get_status")) {
    $s = opcache_get_status(false);
    echo "    opcache enabled (CLI): ".(!empty($s["opcache_enabled"]) ? "yes" : "no").PHP_EOL;
} else {
    echo "    opcache_get_status not available in CLI".PHP_EOL;
}
'
echo

echo "==> IMPORTANT: restart PHP-FPM (reload is often not enough for OPcache)"
echo "    Web requests use PHP-FPM; CLI passing does NOT mean the site is fixed."
echo
echo "    sudo systemctl restart php8.2-fpm"
echo "    # or: sudo systemctl restart php-fpm"
echo "    # cPanel: MultiPHP Manager → restart PHP-FPM for the domain"
echo
echo "==> If still failing after FPM restart, run diagnostics and paste output:"
if command -v rg >/dev/null 2>&1; then
  echo "    rg -n '^class Activity\\b' $APM_ROOT --glob '*.php'"
else
  echo "    grep -rn '^class Activity' $APM_ROOT/app --include='*.php'"
fi
echo "    ls -la $APM_ROOT/app/Models/ | grep -i activity"
echo "    md5sum $CANONICAL"
echo "    git show HEAD:apm/app/Models/Activity.php | md5sum"
