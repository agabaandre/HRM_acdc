# Finance — Apache configuration

Finance is served like APM: **PHP via Laravel** at `/staff/finance/`, not a Node reverse proxy.

## Request flow

```
/staff/finance/*  →  finance/.htaccess  →  server.php  →  public/index.php
```

Static files under `public/` (including Vite `public/build/`) are served directly when they exist on disk.

## Local example

- `http://localhost/staff/finance/?token=…` — SSO entry
- `http://localhost/staff/finance/dashboard` — Inertia dashboard

## `APP_URL`

```env
APP_URL=http://localhost/staff/finance
```

No trailing slash.

## Production

1. Map `/staff/finance` to the `finance/` directory (same as `/staff/apm` → `apm/`).
2. Enable `mod_rewrite` and `AllowOverride All`.
3. Run `npm run build` in `finance/` so `public/build/` exists.
4. Set `APP_ENV=production`, `APP_DEBUG=false`.

## Retired setup

Remove any `ProxyPass` to Node ports **3002** / **3003**.
