# Wave Final Fix Report

## Scope

Addressed the final whole-wave important findings on branch `feature/wave-staff-auth-performance`:

1. Wrapped `Modules\Staff\Services\StaffContractService::create()` in a database transaction so insert, status sync, renewal demotion, and related contract-state updates now succeed or fail together.
2. Added minimal OIDC discovery and JWKS endpoints for Passport-backed OAuth clients because the installed Passport 13 setup does not expose discovery metadata or JWKS endpoints out of the box in this app.

## Code changes

- `staff-portal/backend/Modules/Staff/app/Services/StaffContractService.php`
  - Wrapped contract creation in `DB::transaction(...)`.
- `staff-portal/backend/tests/Feature/StaffContractUniquenessTest.php`
  - Added a rollback regression test proving a failure after insert does not leave an extra current contract behind.
- `staff-portal/backend/Modules/Auth/app/Http/Controllers/OidcDiscoveryController.php`
  - Added discovery and JWKS JSON responses.
- `staff-portal/backend/Modules/Auth/routes/web.php`
  - Published `/.well-known/openid-configuration`, `/oauth/.well-known/openid-configuration`, and `/oauth/jwks`.
- `staff-portal/docs/oauth-oidc-clients.md`
  - Documented the actual discovery and JWKS URLs plus the minimal OIDC behavior.
- `staff-portal/backend/Modules/Auth/README-oauth.md`
  - Removed the outdated “No client management UI yet” note.
- `staff-portal/backend/tests/Feature/OidcDiscoveryTest.php`
  - Added route and JSON coverage for discovery and JWKS.
- `staff-portal/backend/tests/Feature/OAuthUserApiTest.php`
  - Added in-test Passport key configuration so the existing OAuth user endpoint test can run reliably in the test environment.

## Verification

### Routes verified

- `GET /.well-known/openid-configuration`
- `GET /oauth/.well-known/openid-configuration`
- `GET /oauth/jwks`

Verified with:

- `php artisan route:list --path=openid-configuration`
- `php artisan route:list --path=oauth/jwks`

### Tests

Passed:

- `php artisan test tests/Feature/StaffContractUniquenessTest.php tests/Feature/StaffContractApiTest.php tests/Feature/OidcDiscoveryTest.php tests/Feature/OAuthUserApiTest.php`

## Notes

- Discovery metadata intentionally points clients at Passport's existing authorize and token endpoints plus the existing `GET /api/v1/oauth/user` endpoint.
- The added discovery document is sufficient for endpoint discovery and JWKS publication, but it does not add a full Passport-issued OIDC ID token flow beyond what Passport already provides here.
