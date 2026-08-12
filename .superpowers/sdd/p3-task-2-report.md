# Plan 3 Task 2 Report

## Status

Completed and ready for admin review.

## What changed

- Added Passport-backed admin endpoints for OAuth clients in `staff-portal/backend/Modules/Auth/app/Http/Controllers/Api/V1/OAuthClientApiController.php`.
- Registered `GET`, `POST`, and `DELETE` routes under `/api/v1/auth/oauth-clients` in `staff-portal/backend/Modules/Auth/routes/api.php`.
- Added focused backend coverage in `staff-portal/backend/tests/Feature/OAuthClientsApiTest.php` for listing, confidential/public creation, permission gating, revoke behavior, and route registration.
- Extended `staff-portal/frontend/src/lib/authAdminApi.ts` with OAuth client list/create/revoke helpers and shared types.
- Added the new admin screen at `staff-portal/frontend/src/pages/auth/OAuthClientsPage.vue`.
- Added router and More-menu navigation entries in `staff-portal/frontend/src/router/index.ts` and `staff-portal/frontend/src/lib/portalNav.ts`.

## Verification

- `php artisan test tests/Feature/OAuthClientsApiTest.php`
- `npm run build`

## Notes / concerns

- Confidential client secrets are only returned on the create response and are intentionally not recoverable later.
- The frontend accepts newline-separated redirect URIs and normalizes them before posting.
- There is no dedicated frontend unit-test script in `staff-portal/frontend/package.json`, so frontend verification for this task was limited to the production build.
