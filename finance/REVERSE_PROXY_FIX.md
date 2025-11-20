# Reverse Proxy Configuration Fix

## Issue
When accessing the finance app through the reverse proxy at `https://cbp.africacdc.org/finance`, the page is blank because:
1. The React app is built with `homepage: "/finance"` so assets are referenced as `/finance/static/...`
2. The reverse proxy strips the `/finance` prefix when forwarding to Node.js
3. The browser requests assets at `/finance/static/...` but the server serves them at `/static/...`

## Solution

### Option 1: Update Reverse Proxy to Preserve Path (Recommended)

Update your Apache configuration to preserve the `/finance` path:

```apache
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

**Note:** Change `ProxyPass http://localhost:3002/` to `ProxyPass http://localhost:3002/finance` to preserve the path.

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

