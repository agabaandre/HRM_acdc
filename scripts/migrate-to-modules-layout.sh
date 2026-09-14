#!/usr/bin/env bash
# Migrate a legacy CBP checkout to the modules/ folder layout.
#
# Moves root apps into modules/{staff-portal,apm,finance,helpdesk}, removes the
# root `backend` symlink, and optionally deletes known root clutter.
#
# Public URLs stay the same (/staff/, /staff/backend, /staff/apm, …) once the
# repo also has the updated root .htaccess (git pull / deploy that commit).
#
# Usage (from staff repo root, or any cwd — script finds the root):
#   ./scripts/migrate-to-modules-layout.sh           # dry-run
#   ./scripts/migrate-to-modules-layout.sh --apply   # perform moves
#   ./scripts/migrate-to-modules-layout.sh --apply --clean
#
set -euo pipefail

APPLY=0
CLEAN=0

usage() {
  sed -n '2,16p' "$0" | sed 's/^# \{0,1\}//'
  exit "${1:-0}"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --apply) APPLY=1 ;;
    --clean) CLEAN=1 ;;
    -h|--help) usage 0 ;;
    *)
      echo "error: unknown argument: $1" >&2
      usage 1
      ;;
  esac
  shift
done

# Resolve staff repo root (directory that should contain .htaccess + apps).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

if [[ ! -f "$ROOT/.htaccess" && ! -d "$ROOT/modules" && ! -d "$ROOT/apm" && ! -d "$ROOT/staff-portal" ]]; then
  echo "error: cannot find staff repo root at $ROOT" >&2
  exit 1
fi

cd "$ROOT"
echo "==> Staff root: $ROOT"
if [[ "$APPLY" -eq 0 ]]; then
  echo "==> DRY-RUN (pass --apply to make changes)"
fi

run() {
  if [[ "$APPLY" -eq 1 ]]; then
    "$@"
  else
    printf '    would run:';
    printf '%q ' "$@"
    printf '\n'
  fi
}

APPS=(staff-portal apm finance helpdesk)
MOVED=0
SKIPPED=0

mkdir_modules() {
  if [[ ! -d modules ]]; then
    echo "==> mkdir modules"
    run mkdir -p modules
  fi
}

move_app() {
  local name="$1"
  local src="$ROOT/$name"
  local dest="$ROOT/modules/$name"

  if [[ -e "$dest" || -L "$dest" ]]; then
    echo "==> skip $name (already at modules/$name)"
    SKIPPED=$((SKIPPED + 1))
    return 0
  fi

  if [[ ! -e "$src" && ! -L "$src" ]]; then
    echo "==> skip $name (not at repo root)"
    SKIPPED=$((SKIPPED + 1))
    return 0
  fi

  mkdir_modules
  echo "==> move $name/ → modules/$name/"
  # Prefer git mv when tracked; fall back to mv for deploy trees without git.
  if [[ -d "$ROOT/.git" ]] && [[ -n "$(git -C "$ROOT" ls-files "$name" 2>/dev/null | head -1)" ]]; then
    run git -C "$ROOT" mv "$name" "modules/$name"
  else
    run mv "$src" "$dest"
  fi
  MOVED=$((MOVED + 1))
}

for app in "${APPS[@]}"; do
  move_app "$app"
done

# Root backend symlink → modules/staff-portal/backend (no longer used).
if [[ -L "$ROOT/backend" || -e "$ROOT/backend" ]]; then
  if [[ -L "$ROOT/backend" ]]; then
    echo "==> remove backend symlink"
    if [[ -d "$ROOT/.git" ]] && git -C "$ROOT" ls-files --error-unmatch backend >/dev/null 2>&1; then
      run git -C "$ROOT" rm backend
    else
      run rm -f "$ROOT/backend"
    fi
  else
    echo "==> warn: root backend exists and is not a symlink — leave it; remove manually if safe"
  fi
else
  echo "==> skip backend (no root symlink)"
fi

if [[ "$CLEAN" -eq 1 ]]; then
  echo "==> clean optional root clutter"
  for path in resources utils "Africa CDC Funding Portfolio.xlsx" 000-default.conf nginx-http.conf; do
    if [[ -e "$ROOT/$path" || -L "$ROOT/$path" ]]; then
      echo "    remove $path"
      run rm -rf "$ROOT/$path"
    fi
  done
else
  echo "==> skip clutter cleanup (pass --clean with --apply to remove resources/, utils/, …)"
fi

echo
echo "==> Summary: moved=$MOVED skipped=$SKIPPED apply=$APPLY clean=$CLEAN"
echo
echo "Next steps:"
echo "  1. Ensure root .htaccess maps /staff/{backend,apm,finance,helpdesk} → modules/ (git pull if needed)."
echo "  2. Confirm URLs: /staff/  /staff/backend/up  /staff/apm/  /staff/finance/  /staff/helpdesk/"
echo "  3. Update any deploy scripts that still cd into root apm/ or staff-portal/."
echo "  4. See docs/superpowers/specs/2026-09-14-modules-root-cleanup-design.md"

if [[ "$APPLY" -eq 0 ]]; then
  echo
  echo "Re-run with: $0 --apply [--clean]"
fi
