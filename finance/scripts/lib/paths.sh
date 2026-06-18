#!/usr/bin/env bash
# shellcheck shell=bash
# Resolve Staff portal and module directories at runtime (any deploy path: /var/www, /var/lib/…, etc.).

staff_portal_root_from() {
    local dir="${1:?start directory required}"
    dir="$(cd "$dir" && pwd)"
    while [[ -n "$dir" && "$dir" != "/" ]]; do
        if [[ -f "$dir/index.php" && -d "$dir/application" ]]; then
            printf '%s' "$dir"
            return 0
        fi
        dir="$(dirname "$dir")"
    done
    return 1
}

staff_paths_resolve_from_module() {
    local module_root="${1:?module directory required}"
    local staff_root
    module_root="$(cd "$module_root" && pwd)"
    staff_root="$(staff_portal_root_from "$module_root")" || staff_root="$(cd "$module_root/.." && pwd)"
    export STAFF_ROOT="$staff_root"
    export MODULE_ROOT="$module_root"
    export STAFF_ENV="$STAFF_ROOT/.env"
    export APM_ENV="$STAFF_ROOT/apm/.env"
}
