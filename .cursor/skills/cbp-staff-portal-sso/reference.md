# CBP Staff Portal SSO — reference

## Portal signer (`modules/staff-portal`)

- Encoder: `App\Support\SsoJwt::encode`
- Secret: `env('JWT_SECRET', env('APP_KEY', ''))`
- Launch: `Modules\Core\Support\StaffSsoLaunch::prepareLaunch`
- API: `POST /api/v1/cbp-modules/launch` with `{ "module_key": "…" }`
- Frontend: `modules/staff-portal/frontend/src/lib/cbpLaunch.ts` → hidden form POST to `accept_url`

### Compact claims

From `StaffSsoLaunch::compactClaims`: `staff_id`, `auth_staff_id`, `user_id`, `permissions`, `base_url`, `work_email`, `email`, `name`, `fname`, `lname`, `role`, `role_id`, `directorate_id`, `division_id`, …

## Module decoder (required secret chain)

```php
public static function secret(): string
{
    foreach ([
        config('app.cbp_staff_jwt_secret'),
        env('CBP_STAFF_JWT_SECRET', ''),
        env('JWT_SECRET', ''),
        env('APP_KEY', ''),
    ] as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return '';
}
```

Config default:

```php
'cbp_staff_jwt_secret' => env('CBP_STAFF_JWT_SECRET', env('JWT_SECRET', '')),
```

## Accept controller sketch

```php
public function __invoke(Request $request): Response|RedirectResponse
{
    $jwt = trim((string) $request->input('staff_sso_jwt', ''));
    $payload = $jwt !== '' ? SsoJwt::decode($jwt) : null;
    $staffId = (int) ($payload['staff_id'] ?? 0);
    $email = strtolower(trim((string) ($payload['work_email'] ?? $payload['email'] ?? '')));

    if (! is_array($payload) || $staffId < 1 || $email === '') {
        return redirect('/login'); // app-relative only
    }

    // upsert local user / session from claims…
    $token = /* Sanctum plainTextToken or opaque API token */;

    $next = ltrim(trim((string) $request->input('next', '')), '/');
    if ($next !== '' && (str_contains($next, '..') || str_contains($next, '://'))) {
        $next = '';
    }

    $redirect = rtrim((string) config('app.url'), '/');
    $redirect .= $next !== '' ? '/'.$next : '/';

    return response()->view('sso-bridge', [
        'token' => $token,
        'redirect' => $redirect,
    ], 200)->withHeaders([
        'Content-Security-Policy' => "default-src 'none'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'",
        'Cache-Control' => 'no-store',
    ]);
}
```

Route (CSRF off):

```php
Route::post('/sso/accept', SsoAcceptController::class)->withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, // not PreventRequestForgery
]);
```

## Bridge view sketch

```blade
<script>
(function () {
    try {
        sessionStorage.setItem(@json($tokenKey), @json($token));
    } catch (e) {}
    window.location.replace(@json($redirect));
})();
</script>
```

`$tokenKey` must match the SPA (`email_server.token`, `helpdesk_api_token`, `risk_register_api_token`, …).

## `cbp_modules` CORE example

```php
[
    'module_key' => 'devtrack',
    'system_name' => 'Development Tracker',
    'base_url' => 'devtrack',
    'icon_class' => 'fa-code-branch',
    'permission_code' => '128', // must be a free permissions.id
    'uses_staff_portal_token' => 1,
    'is_production' => 1,
    'is_enabled' => 1,
    'target_resolver' => 'staff_app_token',
    'sort_order' => 55,
],
```

## Known failure → fix

| Symptom | Likely cause |
|---------|----------------|
| Module login page after tile click | Secret mismatch / empty `CBP_STAFF_JWT_SECRET` |
| Grey / “Not available yet” tile | Missing or `is_enabled=0` `cbp_modules` row |
| Empty page at `/staff/x/staff/x/login` | `redirect('/'+spaPath()+'/login')` under subpath mount |
| Bridge 200 but still logged out | Wrong `sessionStorage` key |
| Launch 422 SSO target not allowed | `STAFF_LEGACY_BASE_URL` host mismatch |
| Permission collision | Reused `permission_code` that already names another feature |

## First-party module map (this repo)

| module_key | base_url | Accept (prod shape) |
|------------|----------|---------------------|
| `approvals_management` | `apm` | `/staff/apm/sso/accept` |
| `finance_management` | `finance` | `/staff/finance/backend/sso/accept` |
| `helpdesk_itsm` | `helpdesk` | `/staff/helpdesk/backend/sso/accept` |
| `risk_register` | `risk-register` | `/staff/risk-register/backend/sso/accept` |
| `it` | `it` | `/staff/it/backend/sso/accept` |
| `registry` | `registry` | `/staff/registry/backend/sso/accept` |
| `devtrack` | `devtrack` | `/staff/devtrack/sso/accept` |
