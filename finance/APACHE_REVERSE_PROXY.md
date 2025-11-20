# Apache Reverse Proxy Configuration for Finance App

## Architecture

The Finance app consists of two servers:
- **Frontend (React)**: Running on `http://localhost:3002` - serves the React app
- **Backend (Node.js/Express)**: Running on `http://localhost:3003` - serves the API

## Complete Apache Configuration

Add this to your Apache virtual host configuration:

```apache
# Backend API Server (port 3003) - MUST come first
# This handles all API requests to /finance/api/*
<Location /finance/api>
    ProxyPass http://localhost:3003/api
    ProxyPassReverse http://localhost:3003/api

    # Cookie path rewriting for session cookies
    ProxyPassReverseCookiePath /finance/api /api
    ProxyPassReverseCookieDomain localhost cbp.africacdc.org

    # Headers for proper proxy handling
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-For %{REMOTE_ADDR}s
    RequestHeader set X-Forwarded-Host %{HTTP_HOST}s
    
    # Enable proxy
    ProxyPreserveHost On
</Location>

# Frontend React App (port 3002) - Handles all other /finance routes
# This serves the React app and static assets
<Location /finance>
    ProxyPass http://localhost:3002/finance
    ProxyPassReverse http://localhost:3002/finance

    # Cookie path rewriting for session cookies
    ProxyPassReverseCookiePath /finance /finance
    ProxyPassReverseCookieDomain localhost cbp.africacdc.org

    # Headers for proper proxy handling
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-For %{REMOTE_ADDR}s
    RequestHeader set X-Forwarded-Host %{HTTP_HOST}s
    
    # Enable proxy
    ProxyPreserveHost On
</Location>
```

## Important Notes

1. **Order Matters**: The `/finance/api` location **MUST** come before `/finance` because Apache processes locations in order. If `/finance` comes first, it will catch all requests including API calls.

2. **Path Preservation**: 
   - API requests: `/finance/api/session/transfer` → `http://localhost:3003/api/session/transfer`
   - Frontend requests: `/finance/` → `http://localhost:3002/finance/`
   - Static assets: `/finance/static/js/bundle.js` → `http://localhost:3002/finance/static/js/bundle.js`

3. **Session Cookies**: The `ProxyPassReverseCookiePath` ensures that session cookies set by the backend are properly rewritten to work with the `/finance` path.

4. **HTTPS**: The `X-Forwarded-Proto` header tells the Node.js servers that the original request was HTTPS, which is important for secure cookies.

## Testing

After updating the configuration:

1. **Reload Apache**:
   ```bash
   sudo systemctl reload apache2
   # or
   sudo service apache2 reload
   ```

2. **Test API endpoint**:
   ```bash
   curl -k https://cbp.africacdc.org/finance/api/health
   ```
   Should return: `{"status":"ok","message":"Finance server is running",...}`

3. **Test frontend**:
   - Visit `https://cbp.africacdc.org/finance` in a browser
   - Check browser console for any errors
   - Verify API calls are working (check Network tab)

## Troubleshooting

### API calls return 404
- Check that `/finance/api` location comes before `/finance`
- Verify backend server is running on port 3003
- Check Apache error logs: `sudo tail -f /var/log/apache2/error.log`

### Session not working
- Verify cookies are being set (check browser DevTools → Application → Cookies)
- Check that `ProxyPassReverseCookiePath` is correctly configured
- Verify `X-Forwarded-Proto` header is set to "https"

### Blank page
- Check browser console for JavaScript errors
- Verify frontend server is running on port 3002
- Check that React app was built with `homepage: "/finance"` in package.json

