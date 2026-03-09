# Apache Reverse Proxy Configuration for Finance App

## Updated Apache Configuration

Update your Apache virtual host configuration with the following:

```apache
<Location /finance>
    # Proxy to Node.js server
    ProxyPass http://localhost:3002/
    ProxyPassReverse http://localhost:3002/
    
    # Preserve the original request path
    ProxyPreserveHost On
    
    # Set headers for proper forwarding
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-For %{REMOTE_ADDR}s
    RequestHeader set X-Forwarded-Host %{HTTP_HOST}s
    RequestHeader set X-Real-IP %{REMOTE_ADDR}s
    
    # For Node/Express apps - cookie path handling
    ProxyPassReverseCookiePath / /finance/
    ProxyPassReverseCookieDomain localhost cbp.africacdc.org
    
    # Allow WebSocket connections (if using dev server)
    RewriteEngine on
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/?(.*) "ws://localhost:3002/$1" [P,L]
</Location>
```

## Important Notes

1. **For Production**: Build the React app and serve it from the Node.js server:
   ```bash
   cd finance/frontend
   npm run build
   cd ..
   NODE_ENV=production npm start
   ```

2. **For Development**: The dev server is now configured to accept connections from any host (including through the proxy).

3. **Base Path**: The app should handle the `/finance` base path. Make sure your React Router is configured correctly if needed.

## Environment Variables

Make sure your production `.env` file has:
```env
NODE_ENV=production
PORT=3002
CLIENT_URL=https://cbp.africacdc.org/finance
CI_BASE_URL=https://cbp.africacdc.org/staff
```

## Fix for "Invalid Host header" Error

The "Invalid Host header" error has been fixed by:

1. **Updated `frontend/package.json` scripts** to include:
   - `DANGEROUSLY_DISABLE_HOST_CHECK=true` - Disables host header validation
   - `HOST=0.0.0.0` - Allows connections from any host

2. **Updated CORS configuration** in `server/middleware/index.js` to allow the production domain

3. **Apache proxy configuration** properly forwards headers

After making these changes, restart the React dev server:
```bash
cd finance/frontend
npm start
```

Or if running both server and client:
```bash
cd finance
npm run dev:all
```

