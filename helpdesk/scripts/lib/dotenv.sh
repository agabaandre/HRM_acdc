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

# Load KEY=value from a file into the shell (no export). Skips comments and blanks.
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
