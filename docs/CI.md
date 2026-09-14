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
- Org/repo may need package write enabled for `GITHUB_TOKEN`.

## Azure DevOps

Pipeline variables:

| Name | Secret? | Example |
|------|---------|---------|
| `GHCR_USERNAME` | no | `your-bot` |
| `GHCR_TOKEN` | **yes** | PAT with `write:packages` |
| `GHCR_IMAGE` | no | `ghcr.io/your-org/staff/cbp` |

Point the pipeline at repo-root `azure-pipelines.yml`.
