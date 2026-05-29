# Authentication

Finance has no standalone login page. Users authenticate on the **Staff** portal (CodeIgniter), then open Finance via SSO.

## Flow

1. User signs in at `{BASE_URL}/auth` (Staff).
2. Home dashboard shows Finance for users with permission **92**.
3. Staff builds a URL with `?token=` (JWT HS256 or legacy base64 JSON — same as APM).
4. Finance `GET /` decodes the token, stores `user` and `permissions` in the Laravel session, redirects to `/dashboard`.
5. `EnsureFinanceSession` middleware blocks routes without a valid session or permission 92.
6. Logout: `GET /logout` clears the session and redirects to Staff logout.

## Configuration (`.env` in `finance/`)

```env
JWT_SECRET=<exact copy from Staff root .env>
BASE_URL=http://localhost/staff/
FINANCE_SSO_PERMISSION_ID=92
```

## Token format

- **JWT:** three segments, signed with `JWT_SECRET`, optional `exp` claim.
- **Legacy:** base64-encoded JSON payload (backward compatible with older Staff hand-offs).

## Redirects

| Condition | Result |
|-----------|--------|
| No token, no session | Redirect to `{BASE_URL}/auth/login` |
| Invalid or expired token | Redirect to Staff login |
| Valid session, missing permission 92 | HTTP 403 |

## CBP Modules

After login, the top bar loads permitted modules from Staff Share API (`STAFF_API_USERNAME` / `PASSWORD`), with Finance marked active via `finance_management`.
