#!/usr/bin/env bash
# shellcheck shell=bash
# Resolve Staff portal and module directories at runtime (macOS Homebrew, Linux /var/www, etc.).

# Staff portal root: CodeIgniter tree with index.php + application/.
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

# Given a module directory (helpdesk/, finance/, apm/), set STAFF_ROOT and related paths.
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

# Load staff/scripts/lib/*.sh from a file inside helpdesk/ or finance/.
staff_paths_source_lib() {
    local from_script="${1:?BASH_SOURCE[0]}"
    local module_root staff_root lib_dir
    module_root="$(cd "$(dirname "$from_script")/.." && pwd)"
    staff_root="$(staff_portal_root_from "$module_root")" || staff_root="$(cd "$module_root/.." && pwd)"
    lib_dir="$staff_root/scripts/lib"
    if [[ ! -d "$lib_dir" ]]; then
        echo "error: missing $lib_dir (Staff scripts library)" >&2
        return 1
    fi
    printf '%s' "$lib_dir"
}
