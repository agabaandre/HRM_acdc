# CBP CI/CD — GitHub Actions + Azure Pipelines (GHCR)

**Date:** 2026-09-15  
**Status:** Approved for planning  
**Scope:** Build-gate CI for all CBP modules + Docker image publish to GHCR from both GitHub Actions and Azure Pipelines

## Goals

1. Replace the stale root `azure-pipelines.yml` (PHP 8.1 / root `composer install`) with a modules-aware pipeline.
2. Add GitHub Actions with the **same CI behavior** as Azure.
3. On `main` and version tags, build the CBP image (`docker/Dockerfile` target `prod`) and push to **GHCR**.
4. Keep CI logic in **shared shell scripts** so both platforms stay in sync and steps can be run locally.

## Non-goals

- Deploy to any environment (SSH, App Service, AKS, compose on a VM).
- Azure Container Registry (ACR) or dual-registry publish.
- PHPUnit / Pest / Pint / PHPStan / ESLint as required gates.
- Merging module `vendor/` trees or upgrading Helpdesk Laravel 11 → 12.
- Changing application runtime behavior outside CI/image packaging.

## Decisions

| Topic | Choice |
|-------|--------|
| Scope | CI (build gates) + Docker image → GHCR |
| Registry | GHCR only (`ghcr.io/<owner>/<repo>/cbp`) |
| Azure image publish | Yes — Azure also builds and pushes to GHCR |
| CI depth | Build gates only: `composer install` per app + staff SPA production build |
| Structure | Shared scripts under `scripts/ci/`; thin GHA + Azure wrappers |
| PHP version | 8.2 (parity with Docker image) |
| Node version | 20 LTS |
| Image Dockerfile | `docker/Dockerfile` target `prod` |
| Triggers | PRs + pushes to `main`; image publish on `main` + tags `v*` |

## Architecture

```text
PR / push
    │
    ├─► scripts/ci/composer-modules.sh   (4 Laravel apps)
    ├─► scripts/ci/build-spa.sh          (staff-portal frontend + publish-spa)
    │
    └─► (main / v* only)
            scripts/ci/docker-publish.sh → ghcr.io/.../cbp:<tags>
```

GitHub Actions and Azure Pipelines both:

1. Checkout  
2. Setup PHP 8.2 + Composer  
3. Run `composer-modules.sh`  
4. Setup Node 20 → run `build-spa.sh`  
5. If publish gate: login to GHCR → `docker-publish.sh`

## Shared scripts

| Script | Responsibility |
|--------|----------------|
| `scripts/ci/composer-modules.sh` | `composer install --no-interaction --prefer-dist` in: `modules/staff-portal/backend`, `modules/apm`, `modules/finance`, `modules/helpdesk/backend` |
| `scripts/ci/build-spa.sh` | `npm ci` (or `npm install` if no lockfile) + `npm run build` in `modules/staff-portal/frontend`; then `modules/staff-portal/scripts/publish-spa.sh` so `public-spa/` matches what the Docker bake expects |
| `scripts/ci/docker-publish.sh` | `docker build --target prod -f docker/Dockerfile -t … .` then `docker push` for sha + branch/tag aliases |

Scripts must be executable, `set -euo pipefail`, and accept env overrides:

- `PHP_VERSION` (informational)  
- `IMAGE_NAME` (default derived from `GITHUB_REPOSITORY` or `GHCR_IMAGE`)  
- `IMAGE_TAG_SHA`, `IMAGE_TAG_EXTRA` (e.g. `main`, `v1.2.3`)  
- `SKIP_PUBLISH=1` for local dry-run build-only if needed  

## GitHub Actions

**File:** `.github/workflows/ci.yml` (single workflow preferred)

- `permissions: contents: read`, `packages: write`  
- Jobs: `ci` (always) → `publish` (needs `ci`, only `main` / `refs/tags/v*`)  
- GHCR login: `docker/login-action` with `GITHUB_TOKEN`  
- Image refs: `ghcr.io/${{ github.repository }}/cbp` (lowercase)

## Azure Pipelines

**File:** `azure-pipelines.yml` (replace entirely)

- Trigger: `main`; PRs to `main`  
- Pool: `ubuntu-latest`  
- Stages/jobs mirror GHA: CI always; publish when `Build.SourceBranch` is `refs/heads/main` or `refs/tags/v*`  
- GHCR auth via pipeline secrets (document in README / pipeline comments):
  - `GHCR_USERNAME` — GitHub user or org bot  
  - `GHCR_TOKEN` — PAT with `write:packages` (and `read:packages` as needed)  
- Image name variable: `GHCR_IMAGE` = `ghcr.io/<owner>/<repo>/cbp` (same as GHA; set as pipeline variable)

## Image tagging

| Event | Tags pushed |
|-------|-------------|
| Push to `main` | `:sha-<short>`, `:main` |
| Tag `v1.2.3` | `:sha-<short>`, `:v1.2.3` |
| PR | No publish |

## Secrets / setup (operators)

**GitHub:** none beyond default `GITHUB_TOKEN` (enable GHCR package permissions for the workflow / org).

**Azure DevOps:**

1. Create GitHub PAT with `write:packages`.  
2. Add secret variables `GHCR_USERNAME`, `GHCR_TOKEN`.  
3. Set `GHCR_IMAGE` to `ghcr.io/<owner>/<repo>/cbp` (lowercase).  
4. Ensure the pipeline YAML path points at repo-root `azure-pipelines.yml`.

Package visibility (public vs internal) is an org choice; document default as private/internal.

## Files to add / change

| Path | Action |
|------|--------|
| `scripts/ci/composer-modules.sh` | Create |
| `scripts/ci/build-spa.sh` | Create |
| `scripts/ci/docker-publish.sh` | Create |
| `.github/workflows/ci.yml` | Create |
| `azure-pipelines.yml` | Replace |
| `docker/README.md` or `docs/CI.md` | Short operator notes (triggers, secrets, image name) |
| Root `README.md` | Link to CI docs / update Docker CI mention if present |

## Success criteria

1. PR run on GitHub: composer installs for all four apps + SPA build succeed.  
2. PR run on Azure: same.  
3. Push to `main` (or dry-run on a branch with publish forced in a test): image appears on GHCR with `:main` and `:sha-*`.  
4. Tag `v*` push: `:v*` tag on GHCR.  
5. Stale PHP 8.1 / root composer steps are gone from Azure YAML.  
6. Running `./scripts/ci/composer-modules.sh` and `./scripts/ci/build-spa.sh` locally mirrors CI.

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Docker `prod` bake needs SPA in tree | `build-spa.sh` runs `publish-spa.sh` before image build on publish jobs |
| Azure GHCR auth fails | Document PAT scopes; fail publish job clearly; CI still green |
| Large image / slow LibreOffice layer | Accept for v1; cache BuildKit where supported; document long first build |
| Case-sensitive GHCR paths | Force lowercase image name in scripts |
| Composer needs env/extensions in CI | Use `shivammathur/setup-php` (GHA) / apt PHP 8.2 (Azure) with common extensions (`mbstring`, `xml`, `curl`, `zip`, `mysql`) |

## Out of scope follow-ups

- Deploy job consuming `ghcr.io/.../cbp:main`  
- ACR mirror  
- Test/lint gates  
- Path-filtered jobs per module (optimize later if CI time hurts)
