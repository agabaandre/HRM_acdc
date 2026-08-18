#!/usr/bin/env bash
# shellcheck shell=bash
# Shared Staff portal URL + DB resolution for module setup scripts (Finance, Helpdesk, …).
# Requires: dotenv.sh loaded; STAFF_ROOT set.

url_is_localhost() {
    local value="${1:-}"
    [[ "$value" =~ localhost|127\.0\.0\.1|::1 ]] && return 0
    return 1
}

# True for URLs that must not be shown as the production site origin.
url_is_local_dev_host() {
    local value="${1:-}"
    url_is_localhost "$value" && return 0
    [[ "$value" =~ \.local(/|$) ]] && return 0
    [[ "$value" =~ \.lan(/|$) ]] && return 0
    [[ "$value" =~ \.localhost(/|$) ]] && return 0
    return 1
}

url_trim() {
    local s="${1:-}"
    s="${s%"${s##*[![:space:]]}"}"
    s="${s#"${s%%[![:space:]]*}"}"
    printf '%s' "$s"
}

url_host_is_public_candidate() {
    local h="${1:-}"
    h="$(printf '%s' "$h" | tr '[:upper:]' '[:lower:]')"
    h="${h%.}"
    [[ -n "$h" ]] || return 1
    [[ "$h" == localhost || "$h" == 127.0.0.1 || "$h" == ::1 || "$h" == '_' ]] && return 1
    [[ "$h" == *.local || "$h" == *.lan || "$h" == *.internal || "$h" == *.localhost ]] && return 1
    [[ "$h" =~ ^[0-9.]+$ ]] && return 1
    [[ "$h" == *.* ]] || return 1
    return 0
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

url_path_from_url() {
    local url
    url="$(url_trim "$1")"
    if [[ "$url" =~ ^https?://[^/]+(/.*)$ ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
    else
        printf '/'
    fi
}

# Rewrite a public URL to a loopback probe (same path) for on-server curl.
url_loopback_probe() {
    printf 'http://127.0.0.1%s' "$(url_path_from_url "$1")"
}

url_host_header() {
    local origin
    origin="$(url_origin_from_base "$1")" || return 1
    printf '%s' "${origin#*://}"
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

url_first_public_server_name_in_file() {
    local file="$1" line name
    [[ -r "$file" ]] || return 1
    while IFS= read -r line; do
        name="${line#*ServerName}"
        if [[ "$name" == "$line" ]]; then
            name="${line#*server_name}"
        fi
        name="$(url_trim "$name")"
        name="${name%%;*}"
        name="${name%% *}"
        name="${name%.}"
        if url_host_is_public_candidate "$name"; then
            printf '%s' "$name"
            return 0
        fi
    done < <(grep -E '^[[:space:]]*(ServerName|server_name)[[:space:]]+' "$file" 2>/dev/null || true)
    return 1
}

url_detect_web_server_name() {
    local f name h old_nullglob
    local -a files=()
    old_nullglob="$(shopt -p nullglob)"
    shopt -s nullglob
    files=(
        /etc/apache2/sites-enabled/*.conf
        /etc/httpd/conf.d/*.conf
        /etc/nginx/sites-enabled/*
        /etc/nginx/conf.d/*.conf
    )
    eval "$old_nullglob"
    for f in "${files[@]}"; do
        case "$(basename "$f")" in
            *ssl*|*443*|*le-ssl*) ;;
            *) continue ;;
        esac
        name="$(url_first_public_server_name_in_file "$f")" && { printf '%s' "$name"; return 0; }
    done
    for f in "${files[@]}"; do
        name="$(url_first_public_server_name_in_file "$f")" && { printf '%s' "$name"; return 0; }
    done
    if command -v apache2ctl >/dev/null 2>&1; then
        while IFS= read -r h; do
            h="$(url_trim "$h")"
            if url_host_is_public_candidate "$h"; then
                printf '%s' "$h"
                return 0
            fi
        done < <(apache2ctl -S 2>/dev/null | grep -Eo 'namevhost[[:space:]]+[^[:space:]]+' | awk '{print $2}' || true)
    fi
    if command -v httpd >/dev/null 2>&1; then
        while IFS= read -r h; do
            h="$(url_trim "$h")"
            if url_host_is_public_candidate "$h"; then
                printf '%s' "$h"
                return 0
            fi
        done < <(httpd -S 2>/dev/null | grep -Eo 'namevhost[[:space:]]+[^[:space:]]+' | awk '{print $2}' || true)
    fi
    return 1
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
    local candidate host
    for candidate in \
        "${STAFF_PORTAL_PUBLIC_URL:-}" \
        "$(staff_env_get PRODUCTION_URL)" \
        "$(staff_env_get CI_BASE_URL)" \
        "$(staff_env_get BASE_URL)" \
        "$(dotenv_get "$STAFF_ROOT/apm/.env" BASE_URL 2>/dev/null || true)"; do
        candidate="$(url_trim "$candidate")"
        [[ -n "$candidate" ]] || continue
        if ! url_is_local_dev_host "$candidate"; then
            url_staff_base_from_full "$candidate"
            return 0
        fi
    done

    host="$(url_detect_web_server_name || true)"
    host="$(url_trim "$host")"
    if url_host_is_public_candidate "$host"; then
        printf 'https://%s/staff/' "$host"
        return 0
    fi

    host="$(hostname -f 2>/dev/null || hostname 2>/dev/null || true)"
    host="$(url_trim "$host")"
    if url_host_is_public_candidate "$host"; then
        printf 'https://%s/staff/' "$host"
        return 0
    fi

    return 1
}

url_needs_resolve() {
    local v="${1:-}"
    [[ -z "$v" ]] && return 0
    url_is_local_dev_host "$v"
}

# Map Staff portal DB_* keys into Laravel DB_* when module values are missing.
inherit_database_from_staff() {
    local val
    [[ "${DB_CONNECTION:-mysql}" == "mysql" ]] || return 0

    if [[ -z "${DB_HOST:-}" ]]; then
        val="$(staff_env_get DB_HOST)"
        if [[ -n "$val" ]]; then DB_HOST="$val"; fi
    fi
    if [[ -z "${DB_PORT:-}" ]]; then
        val="$(staff_env_get DB_PORT)"
        if [[ -n "$val" ]]; then DB_PORT="$val"; fi
    fi
    if [[ -z "${DB_USERNAME:-}" ]]; then
        val="$(staff_env_get DB_USER)"
        if [[ -n "$val" ]]; then DB_USERNAME="$val"; fi
    fi
    if [[ -z "${DB_PASSWORD:-}" ]]; then
        val="$(staff_env_get DB_PASS)"
        if [[ -n "$val" ]]; then DB_PASSWORD="$val"; fi
    fi
}
