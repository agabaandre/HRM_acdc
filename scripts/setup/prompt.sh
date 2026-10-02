#!/usr/bin/env bash
# shellcheck shell=bash
# Prompt helpers. When SETUP_ASSUME_DEFAULTS=1, accept the provided default
# (or keep-existing value) without reading from the TTY.

setup_assume_defaults() {
  [[ "${SETUP_ASSUME_DEFAULTS:-0}" == "1" ]]
}

prompt_value() {
  local __var="$1" __label="$2" __default="${3:-}" __in
  if setup_assume_defaults; then
    printf -v "$__var" '%s' "$__default"
    echo "$__label → ${__default:-"(empty)"}"
    return 0
  fi
  if [[ -n "$__default" ]]; then
    read -r -p "$__label [$__default]: " __in || true
  else
    read -r -p "$__label: " __in || true
  fi
  if [[ -z "$__in" ]]; then
    printf -v "$__var" '%s' "$__default"
  else
    printf -v "$__var" '%s' "$__in"
  fi
}

prompt_secret() {
  local __var="$1" __label="$2" __default="${3:-}" __in
  if setup_assume_defaults; then
    printf -v "$__var" '%s' "$__default"
    if [[ -n "$__default" ]]; then
      echo "$__label → [kept]"
    else
      echo "$__label → (empty)"
    fi
    return 0
  fi
  if [[ -n "$__default" ]]; then
    read -r -s -p "$__label [***** keep]: " __in || true
  else
    read -r -s -p "$__label: " __in || true
  fi
  echo
  if [[ -z "$__in" ]]; then
    printf -v "$__var" '%s' "$__default"
  else
    printf -v "$__var" '%s' "$__in"
  fi
}

prompt_choice() {
  local __var="$1" __label="$2" __options="$3" __default="$4" __in
  if setup_assume_defaults; then
    printf -v "$__var" '%s' "$__default"
    echo "$__label → $__default  ($__options)"
    return 0
  fi
  echo "$__label"
  echo "  $__options"
  read -r -p "Choice [$__default]: " __in || true
  [[ -z "$__in" ]] && __in="$__default"
  printf -v "$__var" '%s' "$__in"
}

# Required value — no silent default. Optional $3 is a keep-existing hint only
# (Enter keeps it); empty existing forces input until non-empty.
# Note: do not pass a target name that matches locals below (__in).
prompt_required() {
  local __var="$1" __label="$2" __existing="${3:-}" __in
  if setup_assume_defaults; then
    __in="${__existing#"${__existing%%[![:space:]]*}"}"
    __in="${__in%"${__in##*[![:space:]]}"}"
    if [[ -n "$__in" ]]; then
      printf -v "$__var" '%s' "$__in"
      echo "$__label → $__in"
      return 0
    fi
    echo "error: $__label is required but empty (defaults mode). Set it in .env or run interactively." >&2
    exit 1
  fi
  while true; do
    if [[ -n "$__existing" ]]; then
      read -r -p "$__label [$__existing]: " __in || true
      if [[ -z "$__in" ]]; then
        __in="$__existing"
      fi
    else
      read -r -p "$__label: " __in || true
    fi
    # Trim surrounding whitespace
    __in="${__in#"${__in%%[![:space:]]*}"}"
    __in="${__in%"${__in##*[![:space:]]}"}"
    if [[ -n "$__in" ]]; then
      printf -v "$__var" '%s' "$__in"
      return 0
    fi
    echo "  (required — please enter a value)" >&2
  done
}

# Required email with a light format check.
prompt_required_email() {
  local __var="$1" __label="$2" __existing="${3:-}" __val
  if setup_assume_defaults; then
    __val="${__existing#"${__existing%%[![:space:]]*}"}"
    __val="${__val%"${__val##*[![:space:]]}"}"
    if [[ -z "$__val" ]]; then
      __val="${SETUP_DEFAULT_MAIL_FROM:-}"
    fi
    if [[ "$__val" =~ ^[[:alnum:]._%+-]+@[[:alnum:].-]+\.[[:alpha:]]{2,}$ ]]; then
      printf -v "$__var" '%s' "$__val"
      echo "$__label → $__val"
      return 0
    fi
    echo "error: $__label needs a valid email in defaults mode (set MAIL_FROM_ADDRESS in .env)." >&2
    exit 1
  fi
  while true; do
    # Use __val (not __in) so prompt_required's local __in cannot shadow the result.
    prompt_required __val "$__label" "$__existing"
    if [[ "$__val" =~ ^[[:alnum:]._%+-]+@[[:alnum:].-]+\.[[:alpha:]]{2,}$ ]]; then
      printf -v "$__var" '%s' "$__val"
      return 0
    fi
    echo "  (invalid email — use name@domain.tld)" >&2
    __existing="$__val"
  done
}
