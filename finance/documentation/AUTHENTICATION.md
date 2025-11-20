# Authentication Flow

The finance app does not have its own login page. All authentication is handled by the CodeIgniter (CI) application.

## Authentication Flow

1. **User Access Without Session:**
   - User tries to access the finance app directly (e.g., `http://localhost:3002`)
   - App checks for existing session
   - If no session found, user is automatically redirected to CI login: `{CI_BASE_URL}/auth`

2. **User Access With Token:**
   - User clicks "Finance Management" card on CI home page
   - CI app generates a token with session data
   - User is redirected to finance app with token: `http://localhost:3002?token=...`
   - Finance app decodes token and transfers session to Node.js server
   - User is authenticated and can access the dashboard

3. **Session Transfer:**
   - Token contains base64-encoded JSON with user session data
   - Finance app decodes token and sends to `/api/session/transfer`
   - Node.js server stores session in Express session
   - User is authenticated

4. **Logout:**
   - User clicks logout in finance app
   - Session is destroyed on Node.js server
   - User is redirected to CI login page

## Configuration

Set the CI base URL in your `.env` file:

```env
CI_BASE_URL=http://localhost/staff
```

Or in React app:

```env
REACT_APP_CI_BASE_URL=http://localhost/staff
```

## Redirect Behavior

The app will redirect to CI login in the following scenarios:

- No session found when accessing the app
- Session transfer fails
- Session check fails
- User explicitly logs out
- Session expires

## API Responses

All session-related API endpoints now include a `redirectUrl` field in error responses:

```json
{
  "authenticated": false,
  "message": "No active session",
  "redirectUrl": "http://localhost/staff/auth"
}
```

This allows the frontend to redirect users to the CI login page when authentication fails.

