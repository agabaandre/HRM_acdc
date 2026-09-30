#!/usr/bin/env bash
# Copy the repo into the Docker named volume cbp-staff-code (no host bind-mount).
# Required when Docker Desktop cannot share /opt/homebrew (Mounts denied).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VOL="${STAFF_DOCKER_VOLUME:-cbp-staff-code}"

export COPYFILE_DISABLE=1

echo "==> Ensuring volume ${VOL}"
docker volume create "$VOL" >/dev/null

echo "==> Syncing ${ROOT} → volume ${VOL}..."
docker rm -f cbp-sync-tmp >/dev/null 2>&1 || true
docker create --name cbp-sync-tmp -v "${VOL}:/data" alpine:3.20 >/dev/null
trap 'docker rm -f cbp-sync-tmp >/dev/null 2>&1 || true' EXIT

# Stream tar (no macOS xattrs) into the volume — avoids docker cp xattr failures.
tar -C "$ROOT" \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='modules/staff-portal/frontend/node_modules' \
  --exclude='modules/finance/frontend/node_modules' \
  --exclude='modules/risk-register/frontend/node_modules' \
  --exclude='modules/helpdesk/frontend/node_modules' \
  --exclude='modules/helpdesk/client/node_modules' \
  --exclude='modules/apm/node_modules' \
  --exclude='modules/apm/whatsapp-service/node_modules' \
  --exclude='modules/*/frontend/dist' \
  --exclude='modules/*/frontend/dist-build' \
  --no-xattrs \
  -cf - . \
  | docker cp - cbp-sync-tmp:/data/

docker run --rm -v "${VOL}:/data" alpine:3.20 \
  sh -c 'test -f /data/docker-compose.yml && test -d /data/modules/staff-portal/backend && echo OK_files_present'

echo "==> Sync complete. Start with:"
echo "    docker compose --env-file docker/.env -f docker-compose.yml -f docker-compose.volume.yml up -d --no-build"
