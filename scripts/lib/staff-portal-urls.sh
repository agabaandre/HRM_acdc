#!/usr/bin/env bash
# shellcheck shell=bash
# Shared Staff portal URL + DB resolution for module setup scripts (Finance, Helpdesk, …).
# Requires: dotenv.sh loaded; STAFF_ROOT set.

url_is_localhost() {
    local value="${1:-}"
    [[ "$value" =~ localhost|127\.0\.0\.1 ]] && return 0
    return 1
}

url_trim() {
    local s="${1:-}"
    s="${s%"${s##*[![:space:]]}"}"
    s="${s#"${s%%[![:space:]]*}"}"
    printf '%s' "$s"
}

url_origin_from_base() {
    local base scheme host port
    base="$(url_trim "$1")"
    [[ -n "$base" ]] || return 1
    if [[ ! "$base" =~ ^https?:// ]]; then
        base="http://${base}"
    fi
    if [[ "$base" =~ ^(https?)://([^/:]+)(:([0-9]+))?(/.*)?$ ]]; then
        scheme="${BASH_REMATCH[1]}"
        host="${BASH_REMATCH[2]}"
        port="${BASH_REMATCH[4]:-}"
        if [[ -n "$port" && "$port" != "80" && "$port" != "443" ]]; then
            printf '%s://%s:%s' "$scheme" "$host" "$port"
        else
            printf '%s://%s' "$scheme" "$host"
        fi
        return 0
    fi
    return 1
}

url_staff_base_from_full() {
    local base origin
    base="$(url_trim "$1")"
    [[ -n "$base" ]] || return 1
    if [[ ! "$base" =~ ^https?:// ]]; then
        base="http://${base}"
    fi
    origin="$(url_origin_from_base "$base")" || return 1
    printf '%s/staff/' "$origin"
}

staff_env_get() {
    local key="$1" file="$STAFF_ROOT/.env" val=""
    [[ -f "$file" ]] || return 1
    val="$(dotenv_get "$file" "$key" 2>/dev/null || true)"
    if [[ -z "$val" ]]; then
        local line
        line="$(grep -E "^${key}[[:space:]]*=" "$file" 2>/dev/null | tail -n 1 || true)"
        [[ -n "$line" ]] || return 1
        val="${line#*=}"
        val="$(url_trim "$val")"
        val="${val%$'\r'}"
        if [[ "$val" =~ ^\".*\"$ ]]; then val="${val:1:${#val}-2}"; fi
        if [[ "$val" =~ ^\'.*\'$ ]]; then val="${val:1:${#val}-2}"; fi
    fi
    printf '%s' "$val"
}

# Pick the best Staff portal base URL (ends with /staff/).
resolve_staff_portal_base_url() {
    local candidate host scheme
    for candidate in \
        "$(staff_env_get PRODUCTION_URL)" \
        "$(staff_env_get CI_BASE_URL)" \
        "$(staff_env_get BASE_URL)" \
        "$(dotenv_get "$STAFF_ROOT/apm/.env" BASE_URL 2>/dev/null || true)"; do
        candidate="$(url_trim "$candidate")"
        [[ -n "$candidate" ]] || continue
        if ! url_is_localhost "$candidate"; then
            url_staff_base_from_full "$candidate"
            return 0
        fi
    done

    host="$(hostname -f 2>/dev/null || hostname 2>/dev/null || true)"
    host="$(url_trim "$host")"
    [[ -n "$host" ]] || return 1

    if url_is_localhost "$host"; then
        scheme="http"
    else
        scheme="https"
    fi
    printf '%s://%s/staff/' "$scheme" "$host"
}

url_needs_resolve() {
    local v="${1:-}"
    [[ -z "$v" ]] && return 0
    url_is_localhost "$v"
}

# Map Staff portal DB_* keys into Laravel DB_* when module values are missing.
inherit_database_from_staff() {
    local val
    [[ "${DB_CONNECTION:-mysql}" == "mysql" ]] || return 0

    if [[ -z "${DB_HOST:-}" ]]; then
        val="$(staff_env_get DB_HOST)"
        [[ -n "$val" ]] && DB_HOST="$val"
    fi
    if [[ -z "${DB_PORT:-}" ]]; then
        val="$(staff_env_get DB_PORT)"
        [[ -n "$val" ]] && DB_PORT="$val"
    fi
    if [[ -z "${DB_USERNAME:-}" ]]; then
        val="$(staff_env_get DB_USER)"
        [[ -n "$val" ]] && DB_USERNAME="$val"
    fi
    if [[ -z "${DB_PASSWORD:-}" ]]; then
        val="$(staff_env_get DB_PASS)"
        [[ -n "$val" ]] && DB_PASSWORD="$val"
    fi
}
