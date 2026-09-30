# Setup defaults + dashboard KPI polish

**Date:** 2026-09-26  
**Status:** Approved

## Goals

1. Wizard defaults: Docker Compose + External MySQL (host MySQL via `host.docker.internal` under Docker).
2. Keep shared `EXCHANGE_*` / `JWT_SECRET` in `/staff/.env` only (already done).
3. Do not refactor repetitive Docker Compose steps.
4. Polish Staff Portal `/dashboard` KPI cards with clearer icon tiles.
5. Verify CBP module SSO navigation; send Graph test email to `andrewa@africacdc.org`.

## Setup changes

- `DEPLOY_CHOICE` default → `2` (Docker Compose).
- `DB_DEFAULT` for new installs → `2` (External MySQL); existing installs stay `3` (Keep).
- Optionally reorder prompt labels so External is listed first for clarity; map choices consistently.
- Update `docs/SETUP.md` defaults table only.

## Dashboard

- Staff Portal `DashboardPage.vue` KPI row: icon badge + label + value, professional spacing/hover.
- Mirror same treatment in finance/risk-register clones if they share the markup.

## Out of scope

- Deduplicating Docker Compose service blocks.
- Home `/` module launcher redesign.
