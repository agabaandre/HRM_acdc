# Frontend Dependency Fix

## Issue
The frontend is failing with: `Cannot find module 'ajv/dist/compile/codegen'`

## Solution

The issue is caused by a version mismatch between `ajv` and `ajv-keywords`. The `ajv-keywords` package expects `ajv` version 8.x, but react-scripts may have installed an older version.

### Quick Fix

1. **Fix permissions** (if needed):
```bash
cd /opt/homebrew/var/www/staff/finance/frontend
sudo chown -R $(whoami) node_modules
```

2. **Install correct ajv version**:
```bash
cd /opt/homebrew/var/www/staff/finance/frontend
npm install ajv@^8.12.0 --legacy-peer-deps
```

3. **Or reinstall all dependencies**:
```bash
cd /opt/homebrew/var/www/staff/finance/frontend
sudo rm -rf node_modules package-lock.json
npm install --legacy-peer-deps
```

### Alternative: Use npm ci

If you have a package-lock.json:
```bash
cd /opt/homebrew/var/www/staff/finance/frontend
sudo rm -rf node_modules
npm ci --legacy-peer-deps
```

### Verify Fix

After installation, verify ajv is correct:
```bash
cd /opt/homebrew/var/www/staff/finance/frontend
test -f node_modules/ajv/dist/compile/codegen.js && echo "✓ Fixed" || echo "✗ Still broken"
```

### Port Configuration

The frontend is configured to run on port 3002:
- `frontend/.env` has `PORT=3002`
- `frontend/package.json` start script includes `PORT=3002`

If you still see port 3000 errors, make sure:
1. The `.env` file exists: `frontend/.env`
2. It contains: `PORT=3002`
3. Restart the dev server

