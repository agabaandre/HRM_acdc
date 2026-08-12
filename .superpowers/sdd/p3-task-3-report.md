# Plan 3 Task 3 Report

## Status

Completed.

## What changed

- Polished `staff-portal/docs/oauth-oidc-clients.md` with the real backend path prefix for this app: `/staff/staff-portal/backend`.
- Documented the concrete Passport URLs for authorize, token, token refresh, and the Passport user endpoint.
- Added a public-client PKCE example covering authorize parameters, token exchange, and bearer-token user lookup.
- Clarified coexistence between the new Passport flow and the legacy SSO JWT flow.
- Added a migration checklist for APM / Helpdesk covering client registration, client type choice, redirect URIs, staged rollout, and cutover.

## Verification

- Verified the documented route families exist in the backend route table via `php artisan route:list --path=oauth`.
- Verified the Passport user endpoint exists in the same route listing as `api/v1/oauth/user`.

## Notes / concerns

- The app exposes a Passport-protected user endpoint at `/api/v1/oauth/user`; it does not currently expose a separate OpenID Connect `userinfo` alias.
- This task was documentation-only; no manual live token exchange was performed in this pass.
