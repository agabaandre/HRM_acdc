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
