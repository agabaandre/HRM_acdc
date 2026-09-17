#!/usr/bin/env bash
# shellcheck shell=bash

prompt_value() {
  local __var="$1" __label="$2" __default="${3:-}" __in
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
  echo "$__label"
  echo "  $__options"
  read -r -p "Choice [$__default]: " __in || true
  [[ -z "$__in" ]] && __in="$__default"
  printf -v "$__var" '%s' "$__in"
}

# Required value — no silent default. Optional $3 is a keep-existing hint only
# (Enter keeps it); empty existing forces input until non-empty.
prompt_required() {
  local __var="$1" __label="$2" __existing="${3:-}" __in
  while true; do
    if [[ -n "$__existing" ]]; then
      read -r -p "$__label [$__existing]: " __in || true
      if [[ -z "$__in" ]]; then
        __in="$__existing"
      fi
    else
      read -r -p "$__label: " __in || true
    fi
    if [[ -n "$__in" ]]; then
      printf -v "$__var" '%s' "$__in"
      return 0
    fi
    echo "  (required — please enter a value)" >&2
  done
}

# Required email with a light format check.
prompt_required_email() {
  local __var="$1" __label="$2" __existing="${3:-}" __in
  while true; do
    prompt_required __in "$__label" "$__existing"
    if [[ "$__in" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; then
      printf -v "$__var" '%s' "$__in"
      return 0
    fi
    echo "  (invalid email — use name@domain.tld)" >&2
    __existing=""
  done
}
