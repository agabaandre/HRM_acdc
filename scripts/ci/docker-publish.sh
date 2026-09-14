#!/usr/bin/env bash
# Build docker/Dockerfile target prod and push to GHCR.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

# Prefer explicit IMAGE_NAME / GHCR_IMAGE; else derive from GITHUB_REPOSITORY.
RAW_IMAGE="${IMAGE_NAME:-${GHCR_IMAGE:-}}"
if [[ -z "$RAW_IMAGE" ]]; then
  if [[ -n "${GITHUB_REPOSITORY:-}" ]]; then
    RAW_IMAGE="ghcr.io/${GITHUB_REPOSITORY}/cbp"
  else
    echo "error: set IMAGE_NAME or GHCR_IMAGE (e.g. ghcr.io/org/repo/cbp)" >&2
    exit 1
  fi
fi

# GHCR requires lowercase repository names.
IMAGE_NAME="$(echo "$RAW_IMAGE" | tr '[:upper:]' '[:lower:]')"

SHORT_SHA="${IMAGE_TAG_SHA:-}"
if [[ -z "$SHORT_SHA" ]]; then
  FULL_SHA="${GITHUB_SHA:-$(git rev-parse HEAD)}"
  SHORT_SHA="$(echo "$FULL_SHA" | cut -c1-7)"
fi
SHA_TAG="sha-${SHORT_SHA}"

EXTRA_TAG="${IMAGE_TAG_EXTRA:-}"

echo "==> docker build --target prod → ${IMAGE_NAME}:${SHA_TAG}"
docker build \
  --target prod \
  -f docker/Dockerfile \
  -t "${IMAGE_NAME}:${SHA_TAG}" \
  .

if [[ -n "$EXTRA_TAG" ]]; then
  echo "==> tag ${IMAGE_NAME}:${EXTRA_TAG}"
  docker tag "${IMAGE_NAME}:${SHA_TAG}" "${IMAGE_NAME}:${EXTRA_TAG}"
fi

if [[ "${SKIP_PUBLISH:-0}" == "1" ]]; then
  echo "==> SKIP_PUBLISH=1 — not pushing"
  exit 0
fi

echo "==> docker push ${IMAGE_NAME}:${SHA_TAG}"
docker push "${IMAGE_NAME}:${SHA_TAG}"

if [[ -n "$EXTRA_TAG" ]]; then
  echo "==> docker push ${IMAGE_NAME}:${EXTRA_TAG}"
  docker push "${IMAGE_NAME}:${EXTRA_TAG}"
fi

echo "==> docker-publish: OK (${IMAGE_NAME}:${SHA_TAG}${EXTRA_TAG:+ + :${EXTRA_TAG}})"
