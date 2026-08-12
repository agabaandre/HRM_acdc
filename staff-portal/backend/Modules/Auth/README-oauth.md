# Auth Module OAuth Notes

This module now supports two API token modes on the same `PortalUser` model:

- Sanctum remains the token system for the first-party Staff Portal SPA.
- Passport is enabled for OAuth2 clients and protects `/api/v1/oauth/user` via the `api` guard.

## Why the model keeps Sanctum's token trait

Existing controllers in this module issue and revoke Sanctum personal access tokens through `createToken()` and `tokens()`. Replacing those methods with Passport equivalents would break the current SPA login/bootstrap flow.

Because of that, `PortalUser` keeps Sanctum's token trait as the primary implementation and exposes only the Passport-compatible methods needed for OAuth routing and bearer-token authorization:

- `clients()`
- `oauthApps()`
- `getProviderName()`
- `currentAccessToken()`
- `tokenCan()`
- `tokenCant()`
- `withAccessToken()`

## Guard split

- `auth:sanctum`: existing `/api/v1/*` SPA endpoints
- `auth:api`: Passport bearer token endpoints for OAuth consumers

## Non-goals in this task

- No change to Microsoft SSO login
- No change to legacy JWT issuance / validation
- OAuth client management remains available through the authenticated admin surface
