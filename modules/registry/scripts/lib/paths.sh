#!/usr/bin/env bash
# shellcheck shell=bash
# Resolve Staff portal and module directories at runtime (any deploy path).

staff_portal_root_from() {
    local dir="${1:?start directory required}"
    dir="$(cd "$dir" && pwd)"
    while [[ -n "$dir" && "$dir" != "/" ]]; do
        if [[ -d "$dir/modules/staff-portal" && -f "$dir/.htaccess" ]]; then
            printf '%s' "$dir"
            return 0
        fi
        if [[ -f "$dir/index.php" && -d "$dir/application" ]]; then
            printf '%s' "$dir"
            return 0
        fi
        dir="$(dirname "$dir")"
    done
    return 1
}

staff_module_parent_fallback() {
    local module_root="${1:?}"
    if [[ "$(basename "$(dirname "$module_root")")" == "modules" ]]; then
        cd "$module_root/../.." && pwd
    else
        cd "$module_root/.." && pwd
    fi
}

staff_paths_resolve_from_module() {
    local module_root="${1:?module directory required}"
    local staff_root
    module_root="$(cd "$module_root" && pwd)"
    staff_root="$(staff_portal_root_from "$module_root")" || staff_root="$(staff_module_parent_fallback "$module_root")"
    export STAFF_ROOT="$staff_root"
    export MODULE_ROOT="$module_root"
    export STAFF_ENV="$STAFF_ROOT/.env"
    export APM_ENV="$STAFF_ROOT/modules/apm/.env"
}

staff_paths_source_lib() {
    local from_script="${1:?BASH_SOURCE[0]}"
    local start staff_root lib_dir
    start="$(cd "$(dirname "$from_script")" && pwd)"
    staff_root="$(staff_portal_root_from "$start")" || staff_root="$(staff_module_parent_fallback "$start")"
    lib_dir="$staff_root/scripts/lib"
    if [[ ! -d "$lib_dir" ]]; then
        echo "error: missing $lib_dir (Staff scripts library)" >&2
        return 1
    fi
    printf '%s' "$lib_dir"
}
