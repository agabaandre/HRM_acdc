#!/usr/bin/env bash
# shellcheck shell=bash
# Rewrite absolute public path prefixes in .htaccess to /{web_root}/.
# Keeps filesystem paths (modules/…) untouched. Prefer a single current web-root
# in redirects — do not add extra RewriteCond/alias complexity here.

SETUP_HTACCESS_ALIAS_NAMES=(staff demo_staff cbp demo_cbp cbpdemo demo_cbpdemo)

setup_htaccess_alias_group() {
  local current="${1:?}" name
  local -a names=()
  local seen="|"
  for name in "$current" "${SETUP_HTACCESS_ALIAS_NAMES[@]}"; do
    [[ -n "$name" ]] || continue
    [[ "$seen" == *"|${name}|"* ]] && continue
    names+=("$name")
    seen="${seen}${name}|"
  done
  local IFS='|'
  printf '%s' "${names[*]}"
}

setup_update_htaccess_file() {
  local file="${1:?}" web_root="${2:?}"
  local aliases
  [[ -f "$file" ]] || return 0
  aliases="$(setup_htaccess_alias_group "$web_root")"

  if ! command -v perl >/dev/null 2>&1; then
    echo "warn: perl required to update $file (skipped)" >&2
    return 0
  fi

  # Only rewrite public URL prefixes and existing alias groups — no new rules.
  WEB_ROOT="$web_root" ALIASES="$aliases" perl -i -pe '
    my $w = $ENV{WEB_ROOT};
    my $a = $ENV{ALIASES};
    s#/(?:staff|demo_staff|cbp|demo_cbp|cbpdemo|demo_cbpdemo)/#/${w}/#g;
    s#\((?:\?:)?(?:staff|demo_staff|cbp|demo_cbp|cbpdemo|demo_cbpdemo)(?:\|(?:staff|demo_staff|cbp|demo_cbp|cbpdemo|demo_cbpdemo))*\)#($a)#g;
  ' "$file"
}

setup_update_htaccess_tree() {
  local root="${1:?}" web_root="${2:?}"
  local f
  for f in \
    "$root/.htaccess" \
    "$root/modules/staff-portal/.htaccess" \
    "$root/modules/apm/.htaccess"
  do
    if [[ -f "$f" ]]; then
      setup_update_htaccess_file "$f" "$web_root"
      echo "    updated ${f#"$root"/} → /${web_root}/"
    fi
  done
}
