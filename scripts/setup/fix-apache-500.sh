#!/usr/bin/env bash
# One-shot fix for Apache HTTP 500 on /demo_staff/ (or any WEB_ROOT Alias).
# Run ON the production host inside the deploy checkout:
#   cd /var/www/html/demo_staff   # or /var/lib/ACDC_SYSTEMS/demo_staff
#   git pull
#   WEB_ROOT=demo_staff ./scripts/setup/fix-apache-500.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck source=update-htaccess.sh
source "$ROOT/scripts/setup/update-htaccess.sh"

WEB_ROOT="${WEB_ROOT:-$(basename "$ROOT")}"
WEB_ROOT="${WEB_ROOT#/}"
WEB_ROOT="${WEB_ROOT%/}"
[[ -n "$WEB_ROOT" ]] || WEB_ROOT=staff

echo "==> Checkout: $ROOT"
echo "==> WEB_ROOT: $WEB_ROOT"

if [[ -d "$ROOT/.git" ]]; then
  echo "==> git fetch + hard reset to origin/main (tracked files only)"
  git -C "$ROOT" fetch origin
  git -C "$ROOT" reset --hard origin/main
fi

echo "==> Strip Options / [END] / RedirectMatch from deploy .htaccess (common 500 causes)"
# Ensure we start from the repo template after reset, then rewrite prefixes.
setup_update_htaccess_tree "$ROOT" "$WEB_ROOT"

# Belt-and-suspenders: purge any leftover Options / END / RedirectMatch that
# update-htaccess does not touch.
for f in \
  "$ROOT/.htaccess" \
  "$ROOT/modules/staff-portal/.htaccess" \
  "$ROOT/modules/staff-portal/backend/.htaccess" \
  "$ROOT/modules/helpdesk/backend/.htaccess"
do
  [[ -f "$f" ]] || continue
  # Drop Options lines (AllowOverride without Options → 500)
  perl -i -ne 'print unless /^\s*Options\b/' "$f"
  # Replace [END] with [L] if any remain
  perl -i -pe 's/\[END([,\]])/[L$1/g; s/,END([,\]])/,L$1/g' "$f"
  # RedirectMatch can conflict with mod_rewrite on some hosts
  perl -i -ne 'print unless /^\s*RedirectMatch\b/' "$f"
  echo "    sanitized ${f#"$ROOT"/}"
done

echo "==> Verify SPA front controller"
SPA="$ROOT/modules/staff-portal/spa-static.php"
if [[ ! -f "$SPA" ]]; then
  echo "error: missing $SPA — layout may be pre-modules; check checkout" >&2
  exit 1
fi
chmod a+rX "$SPA" "$ROOT/modules/staff-portal" 2>/dev/null || true

echo "==> Quick syntax hints (look for Options / END still present)"
if rg -n '^\s*Options\b|\[END|RedirectMatch' \
  "$ROOT/.htaccess" \
  "$ROOT/modules/staff-portal/.htaccess" \
  "$ROOT/modules/staff-portal/backend/.htaccess" 2>/dev/null
then
  echo "warn: still found risky directives above" >&2
else
  echo "    clean"
fi

echo
echo "Done. Test: curl -sI https://cbp.africacdc.org/${WEB_ROOT}/ | head"
echo "If still 500, check Apache error log, e.g.:"
echo "  sudo tail -50 /var/log/apache2/error.log"
echo "  sudo tail -50 /var/log/httpd/error_log"
