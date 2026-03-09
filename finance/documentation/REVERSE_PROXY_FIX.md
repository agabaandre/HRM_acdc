# Reverse Proxy Configuration Fix

## Issue
When accessing the finance app through the reverse proxy at `https://cbp.africacdc.org/finance`, there are two problems:
1. The React app is built with `homepage: "/finance"` so assets are referenced as `/finance/static/...`
2. API calls to `/finance/api/*` need to reach the backend server (port 3003), not the frontend (port 3002)
3. The reverse proxy only forwards to the frontend, so API calls fail

## Solution

### Complete Reverse Proxy Configuration

You need to configure the reverse proxy to handle both the frontend (React app) and backend (API server):

```apache
# Backend API Server (port 3003) - Handle API routes first
<Location /finance/api>
    ProxyPass http://localhost:3003/api
    ProxyPassReverse http://localhost:3003/api

    # For Node/Express apps
    ProxyPassReverseCookiePath /finance/api /api
    ProxyPassReverseCookieDomain localhost cbp.africacdc.org

    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-For %{REMOTE_ADDR}s
    RequestHeader set X-Forwarded-Host %{HTTP_HOST}s
</Location>

# Frontend React App (port 3002) - Handle all other /finance routes
<Location /finance>
    ProxyPass http://localhost:3002/finance
    ProxyPassReverse http://localhost:3002/finance

    # For Node/Express apps
    ProxyPassReverseCookiePath /finance /finance
    ProxyPassReverseCookieDomain localhost cbp.africacdc.org

    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-For %{REMOTE_ADDR}s
    RequestHeader set X-Forwarded-Host %{HTTP_HOST}s
</Location>
```

**Important Notes:**
1. The `/finance/api` location must come **before** the `/finance` location (Apache processes locations in order)
2. API calls to `/finance/api/*` will be forwarded to `http://localhost:3003/api/*`
3. All other `/finance/*` requests will be forwarded to `http://localhost:3002/finance/*`

### Option 2: Rebuild React App Without Homepage (Alternative)

If you can't change the reverse proxy config, you can rebuild the React app without the `homepage` setting:

1. Remove `"homepage": "/finance"` from `finance/frontend/package.json`
2. Rebuild: `cd finance/frontend && npm run build`
3. Restart the Node.js server

However, this means the app won't work correctly when accessed through the reverse proxy.

## Steps to Fix

1. **Update Apache reverse proxy config** (use Option 1 above)
2. **Rebuild the React app** with the homepage setting:
   ```bash
   cd finance/frontend
   npm run build
   ```
3. **Restart the Node.js server**:
   ```bash
   cd finance
   npm restart
   # or
   pm2 restart finance
   ```
4. **Test the application** at `https://cbp.africacdc.org/finance`

## Current Configuration

- `package.json` has `"homepage": "/finance"` - this is correct
- Server is configured to handle both `/` and `/finance` routes
- React Router uses dynamic basename detection

The main fix needed is updating the reverse proxy to preserve the path.

