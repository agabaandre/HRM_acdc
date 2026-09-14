# Session Expiry Management Setup

This document explains how to set up session expiry management in the Laravel APM application.

## Features

- **Session Monitoring**: Automatically monitors user session activity
- **Warning Dialog**: Shows a 5-minute warning before session expires
- **Keep Me Logged In**: Option to extend session without re-login
- **CI Integration**: Validates session with CodeIgniter application
- **Automatic Logout**: Logs out user when session expires

## Setup Instructions

### 1. Environment Configuration

Add the following to your `.env` file:

```env
# CI Application Configuration
CI_BASE_URL=http://localhost/staff
```

### 2. Session Configuration

Ensure your session configuration in `config/session.php` is properly set:

```php
'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours in minutes
```

### 3. Middleware Registration

The session expiry middleware is already registered in `bootstrap/app.php`:

```php
$middleware->web(append: [
    \App\Http\Middleware\CheckSessionExpiry::class,
]);
```

### 4. Staff Portal API Endpoints

The following endpoints in the CodeIgniter Staff application support session validation and refresh:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/share/validate_session` | GET | Validates Bearer SSO JWT (HS256) or legacy base64 token |
| `/share/refresh_token` | POST | Issues a fresh SSO JWT from an existing token |
| `/auth/refresh_sso_session` | GET | Issues a fresh SSO JWT from the active Staff portal session cookie (used by sibling modules) |

Shared JWT helpers: `application/helpers/sso_launch_helper.php` (`staff_sso_build_jwt`, `staff_sso_decode_jwt`, `staff_sso_parse_bearer_token`).

### 5. Laravel API Endpoints

The following endpoints are available in the Laravel APM app:

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/validate-session` | GET | Validates APM session against Staff Share API |
| `/api/extend-session` | POST | Extends APM session; refreshes SSO token when near expiry |
| `/api/session-status` | GET | Returns current session status for `session-monitor.js` |
| `/sso/refresh` | POST | Updates APM web session from a fresh Staff SSO JWT (called by `cbp-session-refresh.js`) |

## Cross-App SSO Session Refresh

While a user works in APM (or Helpdesk), the Staff portal session cookie on the **same site** keeps sibling modules in sync without re-launching from CBP Home.

```
Browser (APM / Helpdesk tab)
    │  every ~15 min (or ~20 min before JWT expiry)
    ▼
GET /staff/auth/refresh_sso_session   (Staff portal cookie)
    │  fresh HS256 JWT
    ▼
cbp:sso-refreshed event
    ├─► APM:  POST /staff/apm/sso/refresh { sso_token }
    └─► Helpdesk: POST /api/v1/auth/staff-sso { token } → new Sanctum token
```

**Client script:** `assets/js/cbp-session-refresh.js` (loaded from the APM layout when logged in; dynamically loaded by the Helpdesk SPA).

**Requirements:**

- All apps on the same host (e.g. `cbp.africacdc.org`) so `credentials: 'same-origin'` sends the Staff portal session cookie.
- `JWT_SECRET` in Staff root `.env` must match APM and Helpdesk.
- User must remain logged into the Staff portal (session not expired in CI).

APM stores `sso_jwt` and `sso_jwt_exp` in the Laravel session after SSO accept or refresh.

## How It Works

1. **Activity Tracking**: The system tracks user activity (mouse, keyboard, scroll, etc.)
2. **Session Monitoring**: Every 30 seconds, checks if session is about to expire
3. **Warning Display**: Shows a 5-minute countdown warning before expiry
4. **User Choice**: User can choose to extend session or log out
5. **Staff SSO refresh**: `cbp-session-refresh.js` polls Staff portal and posts fresh JWTs to `/sso/refresh`
6. **CI Validation**: Server-side checks use `/share/validate_session` with the stored SSO JWT
7. **Automatic Logout**: Logs out user if session expires or Staff validation fails

## Customization

### Warning Time
To change the warning time (default: 5 minutes), modify the `warningTime` property in `public/js/session-monitor.js`:

```javascript
this.warningTime = 5 * 60; // 5 minutes in seconds
```

### Check Interval
To change the check interval (default: 30 seconds), modify the `checkInterval` property:

```javascript
this.checkInterval = 30; // Check every 30 seconds
```

### Session Lifetime
To change the session lifetime, update the `SESSION_LIFETIME` environment variable:

```env
SESSION_LIFETIME=180 # 3 hours in minutes
```

## Troubleshooting

### Session Not Extending
- Check if CI app is accessible from Laravel app
- Verify CI_BASE_URL is correctly configured
- Check Laravel logs for API communication errors

### Warning Not Showing
- Ensure user is logged in (check meta tag `user-logged-in`)
- Verify JavaScript is loading without errors
- Check browser console for JavaScript errors

### CI Validation Failing
- Ensure Staff Share endpoints are reachable: `/share/validate_session`, `/share/refresh_token`
- Verify `JWT_SECRET` matches across Staff, APM, and Helpdesk `.env` files
- Check that `sso_jwt` is stored in the APM session (re-launch from CBP Home once if upgrading from an older session)
- Check Staff portal logs if `GET /auth/refresh_sso_session` returns 401 (Staff session expired)

## Security Notes

- Sessions are validated against the CI app for security
- Failed validations result in immediate logout
- Tokens are refreshed automatically when needed
- All API communications are logged for debugging

## Files Modified/Created

### Laravel App
- `app/Http/Middleware/CheckSessionExpiry.php` - Session expiry middleware
- `app/Http/Controllers/Api/SessionController.php` - Session management API
- `app/Http/Controllers/AuthController.php` - SSO accept + `ssoRefresh`
- `app/Support/StaffSsoSession.php` - Share API URL helpers
- `resources/views/components/session-expiry-modal.blade.php` - Warning modals
- `public/js/session-monitor.js` - Client-side session monitoring
- `resources/views/layouts/app.blade.php` - Session modals + `cbp-session-refresh.js`
- `routes/web.php` - SSO and API routes
- `bootstrap/app.php` - Registered middleware

### Staff Portal (CI)
- `application/modules/share/controllers/Share.php` - `validate_session`, `refresh_token` (JWT-aware)
- `application/modules/auth/controllers/Auth.php` - `refresh_sso_session`
- `application/helpers/sso_launch_helper.php` - JWT build/decode helpers
- `assets/js/cbp-session-refresh.js` - Cross-module client refresh (APM, Helpdesk, Finance)

## Testing

1. Log into the application
2. Wait for the warning dialog to appear (or manually trigger by modifying the warning time)
3. Test "Keep Me Logged In" functionality
4. Test "Log Out Now" functionality
5. Test automatic logout when session expires
