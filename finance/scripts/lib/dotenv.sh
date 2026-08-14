#!/usr/bin/env bash
# shellcheck shell=bash
# Helpers to read/write Laravel .env files.

dotenv_get() {
    local file="$1" key="$2"
    [[ -f "$file" ]] || return 1
    local line
    line="$(grep -E "^${key}=" "$file" 2>/dev/null | tail -n 1 || true)"
    [[ -n "$line" ]] || return 1
    local val="${line#*=}"
    val="${val%$'\r'}"
    if [[ "$val" =~ ^\".*\"$ ]]; then
        val="${val:1:${#val}-2}"
    elif [[ "$val" =~ ^\'.*\'$ ]]; then
        val="${val:1:${#val}-2}"
    fi
    printf '%s' "$val"
}

dotenv_set() {
    local file="$1" key="$2" value="$3"
    touch "$file"
    local tmp="${file}.tmp.$$"
    grep -v -E "^${key}=" "$file" >"$tmp" 2>/dev/null || : >"$tmp"
    if [[ "$value" =~ [[:space:]#\$] ]]; then
        printf '%s="%s"\n' "$key" "${value//\"/\\\"}" >>"$tmp"
    else
        printf '%s=%s\n' "$key" "$value" >>"$tmp"
    fi
    mv "$tmp" "$file"
}

dotenv_value_present() {
    local val="${1:-}"
    [[ -n "$val" ]] || return 1
    [[ "$val" == change-me* ]] && return 1
    return 0
}

dotenv_apply_if_missing() {
    local file="$1" key="$2" value="$3" preexisted="${4:-0}"
    if ! dotenv_value_present "$value"; then
        return 0
    fi
    if [[ "$preexisted" == "1" ]]; then
        local existing
        existing="$(dotenv_get "$file" "$key" 2>/dev/null || true)"
        if dotenv_value_present "$existing"; then
            return 0
        fi
    fi
    dotenv_set "$file" "$key" "$value"
}

dotenv_load_file() {
    local file="$1"
    [[ -f "$file" ]] || return 0
    local line key val
    while IFS= read -r line || [[ -n "$line" ]]; do
        line="${line%%#*}"
        line="${line%"${line##*[![:space:]]}"}"
        line="${line#"${line%%[![:space:]]*}"}"
        [[ -n "$line" ]] || continue
        [[ "$line" == *=* ]] || continue
        key="${line%%=*}"
        val="${line#*=}"
        key="${key%"${key##*[![:space:]]}"}"
        key="${key#"${key%%[![:space:]]*}"}"
        val="${val#"${val%%[![:space:]]*}"}"
        val="${val%"${val##*[![:space:]]}"}"
        if [[ "$val" =~ ^\".*\"$ ]]; then val="${val:1:${#val}-2}"; fi
        if [[ "$val" =~ ^\'.*\'$ ]]; then val="${val:1:${#val}-2}"; fi
        printf -v "$key" '%s' "$val"
    done <"$file"
}
