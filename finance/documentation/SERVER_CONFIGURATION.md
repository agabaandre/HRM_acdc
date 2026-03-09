# Finance Server Configuration for Reverse Proxy

## Server Configuration Summary

The server is now configured to work with the Apache reverse proxy. Here's what's been set up:

### 1. Trust Proxy Setting ✅
- Added `app.set('trust proxy', 1)` in production
- This tells Express to trust the `X-Forwarded-*` headers from Apache
- Critical for proper HTTPS detection and IP address handling

### 2. API Routes ✅
- `/api/*` - Direct API access (development)
- `/finance/api/*` - API access through reverse proxy (production)
- Both routes are mounted and working

### 3. Session Configuration ✅
- Cookie path: `/` (works with reverse proxy)
- Cookie domain: Not set (browser handles it automatically)
- Secure cookies: Enabled in production (HTTPS only)
- SameSite: `none` in production (allows cross-site cookies)
- Session name: `finance.sid` (to avoid conflicts)

### 4. Static File Serving ✅
- Root path: `/` (for direct access)
- Reverse proxy path: `/finance` (for production)
- Both serve the React app build files

### 5. Route Handlers ✅
- Root route (`/`): Handles token processing and serves React app
- Finance route (`/finance`): Same as root, for reverse proxy
- Catch-all routes: Serve React app for SPA routing

## Testing Checklist

After restarting the server, test the following:

### 1. Health Check
```bash
curl https://cbp.africacdc.org/finance/api/health
```
Expected: `{"status":"ok","message":"Finance server is running",...}`

### 2. Session Transfer
1. Click Finance link in production
2. Check browser console for:
   - Token decoded successfully
   - Session transfer response
   - No CORS errors
3. Check Network tab:
   - `POST /finance/api/session/transfer` should return 200
   - Response should have `success: true`

### 3. Session Check
```bash
curl -v -c cookies.txt https://cbp.africacdc.org/finance/api/session
```
Expected: Session information or 401 if not authenticated

### 4. Cookies
- Open browser DevTools → Application → Cookies
- Should see `finance.sid` cookie
- Cookie should have:
  - Domain: `cbp.africacdc.org`
  - Path: `/`
  - Secure: ✓ (HTTPS only)
  - SameSite: None

## Server Logs to Monitor

When testing, watch for these log messages:

1. **Session Transfer**:
   ```
   Session transfer - Received session data: { hasUser: true, ... }
   Session saved successfully - Session ID: ...
   ```

2. **API Requests**:
   ```
   [API] POST /api/session/transfer - IP: ... - X-Forwarded-For: ...
   ```

3. **Errors**:
   - Token processing errors
   - Session save errors
   - Permission errors

## Common Issues and Solutions

### Issue: Session not persisting
**Solution**: Check that:
- `trust proxy` is set
- Cookie `secure` is `true` in production
- Cookie `sameSite` is `none` in production
- Apache is setting `X-Forwarded-Proto: https`

### Issue: API calls return 404
**Solution**: Verify:
- `/finance/api` location comes before `/finance` in Apache config
- Server has both `/api` and `/finance/api` routes mounted
- Backend server is running on port 3003

### Issue: CORS errors
**Solution**: Check:
- CORS middleware allows `https://cbp.africacdc.org`
- `credentials: true` is set in CORS config
- Frontend uses `withCredentials: true` in axios

## Restart Instructions

After making configuration changes:

1. **Restart Backend Server** (port 3003):
   ```bash
   cd /opt/homebrew/var/www/staff/finance
   pm2 restart finance-backend
   # or
   npm restart
   ```

2. **Restart Frontend Server** (port 3002) if running separately:
   ```bash
   pm2 restart finance-frontend
   ```

3. **Reload Apache**:
   ```bash
   sudo systemctl reload apache2
   ```

4. **Check Server Status**:
   ```bash
   pm2 status
   # or
   curl http://localhost:3003/api/health
   curl http://localhost:3002
   ```

## Current Configuration Files

- `server/index.js` - Main server file with route handlers
- `server/middleware/index.js` - Middleware configuration (CORS, sessions, etc.)
- `server/routes/index.js` - API route mounting
- `server/routes/session.js` - Session transfer endpoint
- `frontend/src/App.js` - React app with API URL configuration

