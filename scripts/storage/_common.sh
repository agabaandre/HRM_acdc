#!/usr/bin/env bash
# Shared helpers for staff ecosystem storage migration scripts.
# shellcheck disable=SC2034
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[1]:-$0}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

staff_site_id() {
  local url="${BASE_URL:-${CI_BASE_URL:-http://localhost/staff}}"
  php -r '
    $u = trim(getenv("BASE_URL") ?: getenv("CI_BASE_URL") ?: "http://localhost/staff");
    $p = parse_url($u);
    $host = strtolower(preg_replace("/^www\./", "", $p["host"] ?? "localhost"));
    $slug = implode("-", array_filter(explode(".", $host)));
    $port = $p["port"] ?? null;
    if ($port && !in_array((int)$port, [80, 443], true)) {
        $slug .= "-".$port;
    }
    $path = trim($p["path"] ?? "", "/");
    if ($path !== "") {
        $pathSlug = trim(preg_replace("/[^a-z0-9]+/", "-", strtolower($path)), "-");
        if ($pathSlug !== "") {
            $slug .= "-".$pathSlug;
        }
    }
    echo preg_replace("/^-+|-+$/", "", $slug) ?: "default";
  ' 2>/dev/null || echo "localhost-staff"
}

STAFF_SITE_ID="${STAFF_SITE_ID:-$(staff_site_id)}"
STAFF_HOST_DATA_ROOT="${STAFF_HOST_DATA_ROOT:-/var/staffdata}"
STAFF_DATA_ROOT="${STAFF_DATA_ROOT:-${STAFF_HOST_DATA_ROOT}/${STAFF_SITE_ID}}"

OWNER="${SUDO_USER:-$USER}"
if [[ -z "$OWNER" || "$OWNER" == "root" ]]; then
  OWNER="$(id -un)"
fi
if [[ "$(uname -s)" == "Darwin" ]]; then
  GROUP="${STAFF_STORAGE_GROUP:-staff}"
else
  GROUP="${STAFF_STORAGE_GROUP:-www-data}"
fi

log() { echo "[staff-storage] $*"; }
die() { echo "[staff-storage] ERROR: $*" >&2; exit 1; }

# Cross-platform: macOS (Apple rsync 2.6.x) and Linux/Ubuntu (rsync 3.x).
resolve_rsync() {
  RSYNC_BIN=""
  if [[ "$(uname -s)" == "Darwin" ]]; then
    for candidate in /opt/homebrew/bin/rsync /usr/local/bin/rsync; do
      if [[ -x "$candidate" ]]; then
        RSYNC_BIN="$candidate"
        return 0
      fi
    done
  fi
  RSYNC_BIN="$(command -v rsync || true)"
  if [[ -z "$RSYNC_BIN" ]]; then
    if [[ "$(uname -s)" == "Darwin" ]]; then
      die "rsync not found. Install with: brew install rsync"
    fi
    die "rsync not found. Install with: sudo apt-get install -y rsync"
  fi
}

rsync_progress_flags() {
  # rsync 3.1+ (Homebrew/Linux); Apple rsync 2.6.x does not support --info=progress2
  if "$RSYNC_BIN" --info=progress2 --version &>/dev/null; then
    printf '%s\n' --info=progress2
  elif "$RSYNC_BIN" --help 2>&1 | grep -q -- '--progress'; then
    printf '%s\n' --progress
  fi
}

migrate_copy() {
  local module="$1" src="$2" dest="$3"
  if [[ ! -d "$src" ]]; then
    log "Skip ${module}: source missing (${src})"
    return 0
  fi
  resolve_rsync
  local -a progress_flags=()
  while IFS= read -r flag; do
    [[ -n "$flag" ]] && progress_flags+=("$flag")
  done < <(rsync_progress_flags)

  ensure_dest "$dest"
  log "Migrating ${module}: ${src} → ${dest} (rsync: ${RSYNC_BIN})"
  if [[ "${DRY_RUN:-false}" == "true" ]]; then
    if ((${#progress_flags[@]} > 0)); then
      "$RSYNC_BIN" -a --dry-run --ignore-existing "${progress_flags[@]}" "${src}/" "${dest}/"
    else
      "$RSYNC_BIN" -a --dry-run --ignore-existing "${src}/" "${dest}/"
    fi
  else
    if ((${#progress_flags[@]} > 0)); then
      "$RSYNC_BIN" -a --ignore-existing "${progress_flags[@]}" "${src}/" "${dest}/"
    else
      "$RSYNC_BIN" -a --ignore-existing "${src}/" "${dest}/"
    fi
  fi
  local src_n dest_n
  src_n="$(find "$src" -type f 2>/dev/null | wc -l | tr -d ' ')"
  dest_n="$(find "$dest" -type f 2>/dev/null | wc -l | tr -d ' ')"
  log "${module} done: source=${src_n} files, destination=${dest_n} files"
}

file_size() {
  local path="$1"
  if stat -f%z "$path" &>/dev/null; then
    stat -f%z "$path"
  else
    stat -c%s "$path"
  fi
}

verify_sizes() {
  local src="$1" dest="$2"
  [[ -d "$src" && -d "$dest" ]] || return 0
  while IFS= read -r -d '' f; do
    rel="${f#"${src}"/}"
    [[ -f "${dest}/${rel}" ]] || die "Missing on host: ${rel}"
    local src_size dest_size
    src_size="$(file_size "$f")"
    dest_size="$(file_size "${dest}/${rel}")"
    [[ "$src_size" == "$dest_size" ]] || die "Size mismatch: ${rel}"
  done < <(find "$src" -type f -print0)
  log "Verify OK: ${src} ↔ ${dest}"
}

ensure_dest() {
  local dest="$1"
  if [[ ! -d "$dest" ]]; then
    sudo mkdir -p "$dest"
  fi
  sudo chown -R "${OWNER}:${GROUP}" "$(dirname "$dest")" "$dest" 2>/dev/null || true
  chmod -R ug+rwX "$dest" 2>/dev/null || true
}
