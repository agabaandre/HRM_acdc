#!/usr/bin/env bash
# shellcheck shell=bash
# Discover CBP modules under modules/ so root ./setup.sh does not need a
# hard-coded list for every new app.

# Populated by setup_discover_cbp_modules (module directory basenames).
SETUP_DISCOVERED_MODULES=()

# Modules with bespoke env writers in setup.sh (still discovered for install/migrate).
SETUP_CUSTOM_ENV_MODULES=(
  staff-portal
  apm
  finance
  helpdesk
  risk-register
  it
  registry
)

setup_module_dir() {
  printf '%s' "$ROOT/modules/$1"
}

setup_module_json() {
  local dir
  dir="$(setup_module_dir "$1")"
  if [[ -f "$dir/cbp-module.json" ]]; then
    printf '%s' "$dir/cbp-module.json"
    return 0
  fi
  return 1
}

# Optional cbp-module.json: { "root_setup": false } excludes the module from root setup.
setup_module_root_setup_enabled() {
  local name="$1" json
  json="$(setup_module_json "$name" 2>/dev/null || true)"
  [[ -n "$json" ]] || return 0
  if grep -Eq '"root_setup"[[:space:]]*:[[:space:]]*false' "$json" 2>/dev/null; then
    return 1
  fi
  return 0
}

setup_module_looks_like_cbp() {
  local name="$1" dir
  dir="$(setup_module_dir "$name")"
  [[ -d "$dir" ]] || return 1
  setup_module_root_setup_enabled "$name" || return 1

  if [[ -f "$dir/cbp-module.json" ]]; then
    return 0
  fi
  if [[ -f "$dir/setup-production.sh" ]]; then
    return 0
  fi
  if [[ -f "$dir/scripts/configure-env.sh" ]]; then
    return 0
  fi
  # Standalone Laravel app (e.g. APM) with env template.
  if [[ -f "$dir/artisan" && -f "$dir/.env.example" ]]; then
    return 0
  fi
  if [[ -f "$dir/backend/artisan" && ( -f "$dir/backend/.env.example" || -f "$dir/setup.env.example" ) ]]; then
    return 0
  fi
  return 1
}

setup_discover_cbp_modules() {
  SETUP_DISCOVERED_MODULES=()
  local d name ordered=() rest=()
  shopt -s nullglob
  for d in "$ROOT/modules"/*/; do
    name="$(basename "$d")"
    [[ "$name" == .* ]] && continue
    if setup_module_looks_like_cbp "$name"; then
      if [[ "$name" == "staff-portal" ]]; then
        ordered+=("$name")
      else
        rest+=("$name")
      fi
    fi
  done
  shopt -u nullglob
  # Stable order: staff-portal first, then alphabetical.
  if [[ ${#rest[@]} -gt 0 ]]; then
    # shellcheck disable=SC2207
    rest=($(printf '%s\n' "${rest[@]}" | LC_ALL=C sort))
  fi
  SETUP_DISCOVERED_MODULES=("${ordered[@]}" "${rest[@]}")
}

setup_module_is_custom_env() {
  local name="$1" m
  for m in "${SETUP_CUSTOM_ENV_MODULES[@]}"; do
    [[ "$m" == "$name" ]] && return 0
  done
  return 1
}

setup_module_default_database() {
  local name="$1" json val
  json="$(setup_module_json "$name" 2>/dev/null || true)"
  if [[ -n "$json" ]]; then
    val="$(sed -n 's/.*"db_database"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$json" | head -1)"
    if [[ -n "$val" ]]; then
      printf '%s' "$val"
      return 0
    fi
  fi
  # staff-portal → staff, risk-register → risk_register
  case "$name" in
    staff-portal) printf 'staff' ;;
    helpdesk) printf 'helpdesk' ;;
    finance) printf 'finance' ;;
    risk-register) printf 'risk_register' ;;
    apm) printf 'apm_local' ;;
    *) printf '%s' "${name//-/_}" ;;
  esac
}

setup_module_has_production_installer() {
  [[ -f "$(setup_module_dir "$1")/setup-production.sh" ]]
}

setup_module_has_dev_installer() {
  [[ -f "$(setup_module_dir "$1")/setup.sh" ]]
}

# Print zero or more absolute .env paths for production APP_DEBUG forcing.
setup_module_env_files() {
  local name="$1" dir
  dir="$(setup_module_dir "$name")"
  local f
  for f in \
    "$dir/setup.env" \
    "$dir/.env" \
    "$dir/backend/.env" \
    "$dir/docker/.env"
  do
    [[ -f "$f" ]] && printf '%s\n' "$f"
  done
}

# Print artisan app roots (directory containing artisan).
setup_module_artisan_dirs() {
  local name="$1" dir
  dir="$(setup_module_dir "$name")"
  if [[ -f "$dir/backend/artisan" ]]; then
    printf '%s\n' "$dir/backend"
  fi
  if [[ -f "$dir/artisan" ]]; then
    printf '%s\n' "$dir"
  fi
}

setup_module_production_flag_name() {
  local name="$1"
  # risk-register → RISK_REGISTER_PRODUCTION_SETUP
  local upper
  upper="$(printf '%s' "$name" | tr '[:lower:]-' '[:upper:]_')"
  printf '%s_PRODUCTION_SETUP' "$upper"
}

setup_export_discovered_production_flags() {
  local name flag
  for name in "${SETUP_DISCOVERED_MODULES[@]}"; do
    flag="$(setup_module_production_flag_name "$name")"
    # Dynamic export (bash 3.2 / macOS compatible).
    eval "export ${flag}=1"
  done
}

# Resolve DB_DATABASE for installers (wizard vars for known modules, else .env).
setup_module_db_name() {
  local name="$1" dir val=""
  dir="$(setup_module_dir "$name")"
  case "$name" in
    staff-portal) printf '%s' "${SP_DB:-staff}"; return 0 ;;
    finance) printf '%s' "${FN_DB:-finance}"; return 0 ;;
    helpdesk) printf '%s' "${HD_DB:-helpdesk}"; return 0 ;;
    risk-register) printf '%s' "${RR_DB:-risk_register}"; return 0 ;;
    it) printf '%s' "${IT_DB:-it}"; return 0 ;;
    registry) printf '%s' "${RG_DB:-registry}"; return 0 ;;
    apm) printf '%s' "${APM_DB_DATABASE:-apm_local}"; return 0 ;;
  esac
  if [[ -f "$dir/setup.env" ]]; then
    val="$(env_get "$dir/setup.env" DB_DATABASE 2>/dev/null || true)"
  fi
  if [[ -z "$val" && -f "$dir/backend/.env" ]]; then
    val="$(env_get "$dir/backend/.env" DB_DATABASE 2>/dev/null || true)"
  fi
  if [[ -z "$val" && -f "$dir/.env" ]]; then
    val="$(env_get "$dir/.env" DB_DATABASE 2>/dev/null || true)"
  fi
  printf '%s' "${val:-$(setup_module_default_database "$name")}"
}

# Collect every discovered module .env path (newline-separated).
setup_all_discovered_env_files() {
  local name
  printf '%s\n' "$ROOT/.env"
  for name in "${SETUP_DISCOVERED_MODULES[@]}"; do
    setup_module_env_files "$name"
  done
}

# Collect every artisan app dir under discovered modules.
setup_all_discovered_artisan_dirs() {
  local name
  for name in "${SETUP_DISCOVERED_MODULES[@]}"; do
    setup_module_artisan_dirs "$name"
  done
}

setup_print_discovered_modules() {
  local name
  echo "    Discovered modules (${#SETUP_DISCOVERED_MODULES[@]}): ${SETUP_DISCOVERED_MODULES[*]:-none}"
  for name in "${SETUP_DISCOVERED_MODULES[@]}"; do
    local markers=()
    setup_module_has_production_installer "$name" && markers+=("setup-production")
    setup_module_has_dev_installer "$name" && markers+=("setup")
    [[ -f "$(setup_module_dir "$name")/scripts/configure-env.sh" ]] && markers+=("configure-env")
    setup_module_is_custom_env "$name" && markers+=("custom-env")
    echo "      - $name  (${markers[*]:-migrate-only})"
  done
}
