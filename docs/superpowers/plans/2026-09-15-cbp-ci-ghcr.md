# CBP CI/CD (GHA + Azure → GHCR) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add shared CI scripts plus GitHub Actions and Azure Pipelines that install Composer deps for all CBP Laravel apps, build/publish the staff SPA, and push the `prod` Docker image to GHCR on `main` / `v*` tags.

**Architecture:** Real work lives in `scripts/ci/*.sh`. `.github/workflows/ci.yml` and `azure-pipelines.yml` only set up PHP/Node/Docker auth and invoke those scripts.

**Tech Stack:** Bash, Composer 2, PHP 8.2, Node 20, Docker Buildx, GHCR, GitHub Actions, Azure Pipelines.

**Spec:** `docs/superpowers/specs/2026-09-15-cbp-ci-ghcr-design.md`

## Global Constraints

- PHP 8.2; Node 20 LTS.
- Registry: GHCR only — `ghcr.io/<owner>/<repo>/cbp` (lowercase).
- Both GitHub Actions and Azure publish images (Azure needs `GHCR_USERNAME` / `GHCR_TOKEN` / `GHCR_IMAGE`).
- Build gates only: composer install + SPA build; no PHPUnit/lint gates.
- Image: `docker/Dockerfile` target `prod`.
- Publish only on `main` and tags matching `v*`.
- Do not overwrite unrelated WIP (e.g. staff-portal UI card edits) in CI commits.

## File map

| File | Responsibility |
|------|----------------|
| `scripts/ci/composer-modules.sh` | Composer install for four apps |
| `scripts/ci/build-spa.sh` | npm build + `publish-spa.sh` |
| `scripts/ci/docker-publish.sh` | Build/push GHCR tags |
| `.github/workflows/ci.yml` | GHA wrapper |
| `azure-pipelines.yml` | Azure wrapper (replace stale template) |
| `docs/CI.md` | Operator secrets / triggers / image name |
| `README.md` | Link to `docs/CI.md` |

---

### Task 1: Shared CI scripts

**Files:**
- Create: `scripts/ci/composer-modules.sh`
- Create: `scripts/ci/build-spa.sh`
- Create: `scripts/ci/docker-publish.sh`

**Interfaces:**
- Consumes: repo root as cwd; optional env `GHCR_IMAGE`, `IMAGE_NAME`, `IMAGE_TAG_SHA`, `IMAGE_TAG_EXTRA`, `SKIP_PUBLISH`, `GITHUB_REPOSITORY`, `GITHUB_SHA`
- Produces: exit 0 on success; Docker tags pushed when not `SKIP_PUBLISH=1`

- [ ] **Step 1: Create `scripts/ci/composer-modules.sh`**

```bash
#!/usr/bin/env bash
# Install Composer deps for each CBP Laravel app (build gate).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

MODULES=(
  modules/staff-portal/backend
  modules/apm
  modules/finance
  modules/helpdesk/backend
)

for dir in "${MODULES[@]}"; do
  echo "==> composer install in ${dir}"
  if [[ ! -f "${dir}/composer.json" ]]; then
    echo "error: missing ${dir}/composer.json" >&2
    exit 1
  fi
  (
    cd "${dir}"
    composer install --no-interaction --prefer-dist --no-progress
  )
done

echo "==> composer-modules: OK"
```

- [ ] **Step 2: Create `scripts/ci/build-spa.sh`**

```bash
#!/usr/bin/env bash
# Build staff-portal Vue SPA and publish into public-spa/ for Docker bake / Apache.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
FRONTEND="${ROOT}/modules/staff-portal/frontend"
PUBLISH="${ROOT}/modules/staff-portal/scripts/publish-spa.sh"

cd "$FRONTEND"

if [[ -f package-lock.json ]]; then
  echo "==> npm ci (staff-portal frontend)"
  npm ci --legacy-peer-deps
else
  echo "==> npm install (no package-lock.json)"
  npm install --legacy-peer-deps
fi

echo "==> npm run build"
npm run build

if [[ ! -x "$PUBLISH" ]]; then
  chmod +x "$PUBLISH"
fi

echo "==> publish-spa.sh"
"$PUBLISH" "${FRONTEND}/dist-build"

if [[ ! -f "${ROOT}/modules/staff-portal/public-spa/index.html" ]]; then
  echo "error: public-spa/index.html missing after publish" >&2
  exit 1
fi

echo "==> build-spa: OK"
```

- [ ] **Step 3: Create `scripts/ci/docker-publish.sh`**

```bash
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
```

- [ ] **Step 4: Make scripts executable and smoke-check syntax**

```bash
chmod +x scripts/ci/*.sh
bash -n scripts/ci/composer-modules.sh
bash -n scripts/ci/build-spa.sh
bash -n scripts/ci/docker-publish.sh
```

Expected: no output, exit 0.

- [ ] **Step 5: Commit**

```bash
git add scripts/ci/composer-modules.sh scripts/ci/build-spa.sh scripts/ci/docker-publish.sh
git commit -m "$(cat <<'EOF'
Add shared CI scripts for composer, SPA, and GHCR publish.

EOF
)"
```

---

### Task 2: GitHub Actions workflow

**Files:**
- Create: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: scripts from Task 1; `GITHUB_TOKEN` for GHCR
- Produces: `ci` job always; `publish` job on `main` / `v*`

- [ ] **Step 1: Create `.github/workflows/ci.yml`**

```yaml
name: CI

on:
  push:
    branches: [main]
    tags: ["v*"]
  pull_request:
    branches: [main]

permissions:
  contents: read
  packages: write

concurrency:
  group: ci-${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

jobs:
  ci:
    name: Build gates
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.2"
          extensions: mbstring, xml, curl, zip, mysql, redis, gd, intl, bcmath
          coverage: none
          tools: composer:v2

      - name: Composer install (modules)
        run: ./scripts/ci/composer-modules.sh

      - name: Setup Node 20
        uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: npm
          cache-dependency-path: modules/staff-portal/frontend/package-lock.json

      - name: Build staff SPA
        run: ./scripts/ci/build-spa.sh

  publish:
    name: Publish GHCR image
    needs: ci
    if: github.event_name == 'push' && (github.ref == 'refs/heads/main' || startsWith(github.ref, 'refs/tags/v'))
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup Node 20
        uses: actions/setup-node@v4
        with:
          node-version: "20"
          cache: npm
          cache-dependency-path: modules/staff-portal/frontend/package-lock.json

      - name: Build staff SPA (for Docker bake)
        run: ./scripts/ci/build-spa.sh

      - name: Set image tags
        id: meta
        run: |
          echo "image=$(echo "ghcr.io/${GITHUB_REPOSITORY}/cbp" | tr '[:upper:]' '[:lower:]')" >> "$GITHUB_OUTPUT"
          echo "sha=$(echo "${GITHUB_SHA}" | cut -c1-7)" >> "$GITHUB_OUTPUT"
          if [[ "${GITHUB_REF}" == refs/tags/* ]]; then
            echo "extra=${GITHUB_REF_NAME}" >> "$GITHUB_OUTPUT"
          else
            echo "extra=main" >> "$GITHUB_OUTPUT"
          fi

      - name: Login to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push
        env:
          IMAGE_NAME: ${{ steps.meta.outputs.image }}
          IMAGE_TAG_SHA: ${{ steps.meta.outputs.sha }}
          IMAGE_TAG_EXTRA: ${{ steps.meta.outputs.extra }}
        run: ./scripts/ci/docker-publish.sh
```

- [ ] **Step 2: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "$(cat <<'EOF'
Add GitHub Actions CI and GHCR publish workflow.

EOF
)"
```

---

### Task 3: Azure Pipelines

**Files:**
- Replace: `azure-pipelines.yml`

**Interfaces:**
- Consumes: same scripts; pipeline vars `GHCR_USERNAME`, `GHCR_TOKEN` (secret), `GHCR_IMAGE`
- Produces: CI always; publish on `main` / `v*` when secrets present

- [ ] **Step 1: Replace `azure-pipelines.yml` with:**

```yaml
# CBP CI + GHCR publish (parity with .github/workflows/ci.yml).
#
# Required for publish job (Azure DevOps → Pipelines → Variables):
#   GHCR_USERNAME  — GitHub username or org bot
#   GHCR_TOKEN     — GitHub PAT with write:packages (secret)
#   GHCR_IMAGE     — ghcr.io/<owner>/<repo>/cbp  (lowercase)
#
# See docs/CI.md

trigger:
  branches:
    include:
      - main
  tags:
    include:
      - v*

pr:
  branches:
    include:
      - main

pool:
  vmImage: ubuntu-latest

variables:
  phpVersion: "8.2"
  nodeVersion: "20.x"
  # Override in the Azure pipeline UI if the default is wrong:
  # GHCR_IMAGE: ghcr.io/your-org/your-repo/cbp

stages:
  - stage: CI
    displayName: Build gates
    jobs:
      - job: build
        displayName: Composer + SPA
        steps:
          - checkout: self

          - script: |
              set -euo pipefail
              sudo apt-get update
              sudo apt-get install -y software-properties-common
              sudo add-apt-repository -y ppa:ondrej/php
              sudo apt-get update
              sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
                php$(phpVersion) php$(phpVersion)-cli php$(phpVersion)-mbstring \
                php$(phpVersion)-xml php$(phpVersion)-curl php$(phpVersion)-zip \
                php$(phpVersion)-mysql php$(phpVersion)-gd php$(phpVersion)-intl \
                php$(phpVersion)-bcmath php$(phpVersion)-redis
              sudo update-alternatives --set php /usr/bin/php$(phpVersion)
              php -v
              curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
              composer --version
            displayName: Setup PHP $(phpVersion) + Composer

          - script: ./scripts/ci/composer-modules.sh
            displayName: Composer install (modules)

          - task: NodeTool@0
            inputs:
              versionSpec: $(nodeVersion)
            displayName: Setup Node $(nodeVersion)

          - script: ./scripts/ci/build-spa.sh
            displayName: Build staff SPA

  - stage: Publish
    displayName: Publish GHCR image
    dependsOn: CI
    condition: and(succeeded(), or(eq(variables['Build.SourceBranch'], 'refs/heads/main'), startsWith(variables['Build.SourceBranch'], 'refs/tags/v')))
    jobs:
      - job: docker_publish
        displayName: Docker build and push
        steps:
          - checkout: self

          - task: NodeTool@0
            inputs:
              versionSpec: $(nodeVersion)
            displayName: Setup Node $(nodeVersion)

          - script: ./scripts/ci/build-spa.sh
            displayName: Build staff SPA (for Docker bake)

          - script: |
              set -euo pipefail
              if [[ -z "${GHCR_USERNAME:-}" || -z "${GHCR_TOKEN:-}" ]]; then
                echo "##vso[task.logissue type=error]Set secret variables GHCR_USERNAME and GHCR_TOKEN for GHCR publish"
                exit 1
              fi
              if [[ -z "${GHCR_IMAGE:-}" ]]; then
                echo "##vso[task.logissue type=error]Set GHCR_IMAGE (e.g. ghcr.io/org/repo/cbp)"
                exit 1
              fi
              echo "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USERNAME" --password-stdin
            displayName: Login to GHCR
            env:
              GHCR_USERNAME: $(GHCR_USERNAME)
              GHCR_TOKEN: $(GHCR_TOKEN)
              GHCR_IMAGE: $(GHCR_IMAGE)

          - script: |
              set -euo pipefail
              SHORT_SHA="$(echo "$(Build.SourceVersion)" | cut -c1-7)"
              if [[ "$(Build.SourceBranch)" == refs/tags/* ]]; then
                EXTRA="$(Build.SourceBranchName)"
              else
                EXTRA=main
              fi
              export IMAGE_NAME="$(echo "${GHCR_IMAGE}" | tr '[:upper:]' '[:lower:]')"
              export IMAGE_TAG_SHA="${SHORT_SHA}"
              export IMAGE_TAG_EXTRA="${EXTRA}"
              ./scripts/ci/docker-publish.sh
            displayName: Build and push image
            env:
              GHCR_IMAGE: $(GHCR_IMAGE)
```

**Note:** Azure macro syntax `$(Build.SourceVersion)` inside a `script:` block is expanded by Azure before bash runs. Keep those `$(...)` expressions exactly as written (Azure variables), not bash.

- [ ] **Step 2: Commit**

```bash
git add azure-pipelines.yml
git commit -m "$(cat <<'EOF'
Replace Azure Pipelines with modules CI and GHCR publish.

EOF
)"
```

---

### Task 4: Operator docs

**Files:**
- Create: `docs/CI.md`
- Modify: `README.md` (add CI link near Docker / docs table)

**Interfaces:**
- Consumes: secret names and image naming from Tasks 2–3
- Produces: operators can configure Azure + verify GHCR

- [ ] **Step 1: Create `docs/CI.md`**

````markdown
# CBP continuous integration

GitHub Actions (`.github/workflows/ci.yml`) and Azure Pipelines (`azure-pipelines.yml`) share scripts under `scripts/ci/`.

## What runs

| Step | When |
|------|------|
| `composer install` in staff-portal, APM, finance, helpdesk backends | Every PR / push |
| Staff SPA `npm run build` + `publish-spa.sh` | Every PR / push |
| Docker `prod` image → GHCR | Push to `main` or tag `v*` only |

Image name: `ghcr.io/<owner>/<repo>/cbp` (lowercase).

Tags: `sha-<7chars>`, plus `main` or `vX.Y.Z`.

## Local

```bash
./scripts/ci/composer-modules.sh
./scripts/ci/build-spa.sh
SKIP_PUBLISH=1 GHCR_IMAGE=ghcr.io/example/staff/cbp ./scripts/ci/docker-publish.sh
```

## GitHub

- Workflow permissions need `packages: write` (set in the workflow file).
- Org/repo may need “Allow GitHub Actions to create and approve pull requests” / package write enabled for `GITHUB_TOKEN`.

## Azure DevOps

Pipeline variables:

| Name | Secret? | Example |
|------|---------|---------|
| `GHCR_USERNAME` | no | `your-bot` |
| `GHCR_TOKEN` | **yes** | PAT with `write:packages` |
| `GHCR_IMAGE` | no | `ghcr.io/your-org/staff/cbp` |

Point the pipeline at repo-root `azure-pipelines.yml`.
````

- [ ] **Step 2: Add a README link**

In `README.md` Documentation table (or Docker details), add a row/link:

`[**🔁 CI (GitHub + Azure)**](./docs/CI.md)` — Build gates and GHCR image publish

- [ ] **Step 3: Commit**

```bash
git add docs/CI.md README.md
git commit -m "$(cat <<'EOF'
Document CBP CI triggers, secrets, and GHCR image tags.

EOF
)"
```

---

### Task 5: Local verification

**Files:** none (runtime)

- [ ] **Step 1: Syntax / help**

```bash
bash -n scripts/ci/*.sh
head -5 .github/workflows/ci.yml azure-pipelines.yml docs/CI.md
```

- [ ] **Step 2: Optional local composer + SPA** (skip if machine lacks PHP/Node; note in PR)

```bash
./scripts/ci/composer-modules.sh
./scripts/ci/build-spa.sh
```

Expected: both exit 0; `modules/staff-portal/public-spa/index.html` exists.

- [ ] **Step 3: Optional Docker dry-run** (requires Docker)

```bash
SKIP_PUBLISH=1 GHCR_IMAGE=ghcr.io/local/staff/cbp ./scripts/ci/docker-publish.sh
```

Expected: image builds; no push.

- [ ] **Step 4: Confirm stale Azure PHP 8.1 template is gone**

```bash
rg -n "phpVersion: 8\.1|composer install --no-interaction --prefer-dist$" azure-pipelines.yml || true
rg -n "composer-modules|GHCR|scripts/ci" azure-pipelines.yml .github/workflows/ci.yml
```

Expected: no PHP 8.1; scripts referenced.

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| Shared `scripts/ci/*` | Task 1 |
| GHA workflow | Task 2 |
| Azure replace + GHCR secrets | Task 3 |
| Docs + README link | Task 4 |
| Success criteria / local mirror | Task 5 |
| No deploy / ACR / tests | Out of scope |

## Plan complete
