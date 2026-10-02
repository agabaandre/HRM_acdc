#!/usr/bin/env bash
# shellcheck shell=bash
# Docker Compose helpers for CBP root setup.
# When DEPLOY_MODE=docker (or STAFF_COMPOSER_VIA_DOCKER=1), Composer and workers
# run through the Compose `web` / `workers` services instead of the host.

staff_docker_root() {
  if [[ -n "${STAFF_ROOT:-}" ]]; then
    printf '%s' "$STAFF_ROOT"
    return 0
  fi
  local here
  here="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
  printf '%s' "$here"
}

staff_docker_env_file() {
  printf '%s/docker/.env' "$(staff_docker_root)"
}

staff_use_docker_composer() {
  [[ "${STAFF_COMPOSER_VIA_DOCKER:-0}" == "1" ]] && return 0
  [[ "${DEPLOY_MODE:-}" == "docker" ]] && return 0
  return 1
}

staff_ensure_docker_env() {
  local root envf example
  root="$(staff_docker_root)"
  envf="$(staff_docker_env_file)"
  example="$root/docker/compose.env.example"
  if [[ ! -f "$envf" ]]; then
    if [[ -f "$example" ]]; then
      cp "$example" "$envf"
      echo "    created docker/.env from compose.env.example"
    else
      echo "error: missing $envf and $example" >&2
      return 1
    fi
  fi
  return 0
}

# Run: docker compose --env-file docker/.env "$@"
staff_docker_compose() {
  local root envf
  root="$(staff_docker_root)"
  envf="$(staff_docker_env_file)"
  staff_ensure_docker_env || return 1
  if ! command -v docker >/dev/null 2>&1; then
    echo "error: docker not found (required for Docker deploy)" >&2
    return 1
  fi
  (cd "$root" && docker compose --env-file "$envf" "$@")
}

# Map a host path under STAFF_ROOT to the container bind path (/var/www/staff/...).
staff_host_path_to_container() {
  local host_path="$1"
  local root container_root="/var/www/staff"
  root="$(cd "$(staff_docker_root)" && pwd)"

  if [[ -d "$host_path" ]]; then
    host_path="$(cd "$host_path" && pwd)"
  elif [[ -f "$host_path" ]]; then
    host_path="$(cd "$(dirname "$host_path")" && pwd)/$(basename "$host_path")"
  fi

  if [[ "$host_path" == "$root" ]]; then
    printf '%s' "$container_root"
    return 0
  fi
  if [[ "$host_path" == "$root"/* ]]; then
    printf '%s%s' "$container_root" "${host_path#"$root"}"
    return 0
  fi
  printf '%s' "$host_path"
}

# Composer via Compose `web` image (has composer:2). Honours host cwd + --working-dir.
staff_docker_composer() {
  local host_cwd container_cwd root
  host_cwd="$(pwd)"
  container_cwd="$(staff_host_path_to_container "$host_cwd")"
  root="$(cd "$(staff_docker_root)" && pwd)"

  local -a args=()
  local arg next_is_workdir=0
  for arg in "$@"; do
    if [[ "$next_is_workdir" -eq 1 ]]; then
      args+=("$(staff_host_path_to_container "$arg")")
      next_is_workdir=0
      continue
    fi
    case "$arg" in
      --working-dir=*)
        args+=("--working-dir=$(staff_host_path_to_container "${arg#--working-dir=}")")
        ;;
      -d|--working-dir)
        args+=("$arg")
        next_is_workdir=1
        ;;
      "$root"|"$root"/*)
        args+=("$(staff_host_path_to_container "$arg")")
        ;;
      *)
        args+=("$arg")
        ;;
    esac
  done

  echo "    composer (docker): -w $container_cwd composer ${args[*]}"
  staff_docker_compose run --rm --no-deps \
    -w "$container_cwd" \
    -e COMPOSER_ALLOW_SUPERUSER=1 \
    web \
    composer "${args[@]}"
}

# Host or Docker composer depending on deploy mode.
staff_composer() {
  if staff_use_docker_composer; then
    staff_docker_composer "$@"
  else
    if ! command -v composer >/dev/null 2>&1; then
      echo "error: composer not found on PATH" >&2
      return 1
    fi
    command composer "$@"
  fi
}

# Bring up Redis + web (build if needed).
staff_docker_up_web() {
  echo "==> Docker Compose: starting redis + web"
  staff_docker_compose up -d --build redis web
}

# Bring up workers profile (in-container Supervisor).
staff_docker_up_workers() {
  echo "==> Docker Compose: starting workers (Supervisor in container)"
  staff_docker_compose --profile workers up -d --build
}

staff_docker_workers_status() {
  staff_docker_compose --profile workers exec -T workers supervisorctl status 2>/dev/null \
    || staff_docker_compose --profile workers ps
}

# Stop + remove host Supervisor CBP programs so they do not fight Compose workers.
# Safe no-op when supervisorctl / conf dir are missing (macOS / fresh hosts).
staff_retire_host_supervisor() {
  local root web_root slug conf_dir
  root="$(staff_docker_root)"
  web_root="${WEB_ROOT:-$(basename "$root")}"
  slug="$(printf '%s' "$web_root" | tr -c 'A-Za-z0-9_-' '-' | sed 's/-\+/-/g; s/^-//; s/-$//')"
  [[ -n "$slug" ]] || slug="staff"
  conf_dir="${CBP_SUPERVISOR_CONF_DIR:-/etc/supervisor/conf.d}"

  echo "==> Retiring host Supervisor programs (Docker owns workers; slug=$slug)"

  _staff_svctl() {
    if command -v supervisorctl >/dev/null 2>&1; then
      if [[ "$(id -u)" -eq 0 ]]; then
        supervisorctl "$@"
      elif command -v sudo >/dev/null 2>&1; then
        sudo -n supervisorctl "$@" 2>/dev/null || sudo supervisorctl "$@"
      else
        supervisorctl "$@"
      fi
    else
      return 1
    fi
  }

  _staff_rm() {
    local f="$1"
    if [[ -w "$(dirname "$f")" ]] || [[ "$(id -u)" -eq 0 ]]; then
      rm -f "$f" 2>/dev/null || true
    elif command -v sudo >/dev/null 2>&1; then
      sudo -n rm -f "$f" 2>/dev/null || sudo rm -f "$f" 2>/dev/null || true
    fi
  }

  # Stop running host programs for this site (and any leftover cbp-* from older installs).
  if _staff_svctl status >/dev/null 2>&1; then
    local prog
    while IFS= read -r prog; do
      [[ -z "$prog" ]] && continue
      echo "    stop $prog"
      _staff_svctl stop "$prog" 2>/dev/null || true
    done < <(_staff_svctl status 2>/dev/null | awk '/^cbp-/ {print $1}' | cut -d: -f1 | sort -u)
  else
    echo "    (host supervisorctl not available — skipping stop)"
  fi

  # Remove confs so they cannot be restarted on host supervisord reboot.
  local f
  if [[ -d "$conf_dir" ]]; then
    for f in \
      "$conf_dir"/cbp-"${slug}"-*.conf \
      "$conf_dir"/cbp-staff-*.conf \
      "$conf_dir"/staff-apm-*.conf \
      "$conf_dir"/staff-portal-*.conf \
      "$conf_dir"/laravel-*-apm*.conf \
      "$conf_dir"/supervisor-laravel-*.conf
    do
      [[ -e "$f" ]] || continue
      echo "    remove $f"
      _staff_rm "$f"
    done
  else
    echo "    (no $conf_dir — nothing to remove)"
  fi

  if _staff_svctl status >/dev/null 2>&1; then
    _staff_svctl reread 2>/dev/null || true
    _staff_svctl update 2>/dev/null || true
  fi

  # Also retire CBP systemd queue units if present (older installs).
  if [[ "$(uname -s)" == "Linux" ]] && command -v systemctl >/dev/null 2>&1; then
    if [[ -f "$root/scripts/setup/systemd-cleanup.sh" ]]; then
      # shellcheck source=/dev/null
      source "$root/scripts/setup/systemd-cleanup.sh"
      local -a _retire=(
        staff-portal.target staff-portal-queue.service staff-portal-scheduler.service
        staff-portal-scheduler.timer staff-portal-health.service staff-portal-health.timer
        helpdesk.target helpdesk-queue.service helpdesk-scheduler.service
        helpdesk-scheduler.timer helpdesk-health.service helpdesk-health.timer
        laravel-queue-apm.service laravel-scheduler.service laravel-queue-worker.service
        laravel-queue-cleanup.service laravel12-queue-apm.service
      )
      local local_f
      for local_f in \
        /etc/systemd/system/staff-portal-*-"${web_root}".service \
        /etc/systemd/system/staff-portal-*-"${web_root}".timer \
        /etc/systemd/system/staff-portal-"${web_root}".target \
        /etc/systemd/system/helpdesk-*-"${web_root}".service \
        /etc/systemd/system/helpdesk-*-"${web_root}".timer \
        /etc/systemd/system/helpdesk-"${web_root}".target \
        /etc/systemd/system/laravel-*-"${web_root}".service
      do
        [[ -e "$local_f" ]] || continue
        _retire+=("$(basename "$local_f")")
      done
      echo "    retiring CBP systemd units (if any)"
      systemd_retire_units "${_retire[@]}" 2>/dev/null || true
    fi
  fi

  echo "    host Supervisor CBP workers retired (Compose workers are the source of truth)"
}
