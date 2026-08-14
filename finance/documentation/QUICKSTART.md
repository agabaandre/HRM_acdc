# Finance — Quick start

## Prerequisites

- PHP 8.2+, Composer, Node 20+
- Apache with `mod_rewrite` (recommended)
- MySQL (optional; SQLite works for local session/cache tables)

## Install

From the module root:

```bash
cd finance
./setup.sh
```

## Configure `.env`

```env
APP_URL=http://localhost/staff/finance
BASE_URL=http://localhost/staff/
JWT_SECRET=<same as Staff root .env — required for ?token= SSO>
SESSION_PATH=/staff/finance
FINANCE_ASSETS_BASE_URL=http://localhost/staff/apm
STAFF_API_USERNAME=<Share API user email>
STAFF_API_PASSWORD=<password>
```

## Verify

1. Sign in to Staff: `http://localhost/staff/`
2. Open the Finance tile (permission 92).
3. You should land on `/dashboard` with the APM-style header and nav.

## Development with HMR

```bash
cd finance
composer run dev
```

## Troubleshooting

| Issue | Check |
|-------|--------|
| 404 on `/dashboard` | Apache `AllowOverride`, `finance/.htaccess` |
| Invalid token / login loop | `JWT_SECRET` matches Staff `.env`; open a **fresh** link from Staff home (tokens expire) |
| Apache “Forbidden” | Run `php artisan key:generate`; ensure `bootstrap/subdirectory.php` exists |
| `readonly database` (SQLite) | Run `./fix-storage-permissions.sh` (needs sudo on macOS for `database/database.sqlite`) |
| Wrong URL (`localhost/?token` not `/staff/finance/`) | Open Finance from Staff home tile; check `APP_URL=http://localhost/staff/finance` |
| Missing styles | `FINANCE_ASSETS_BASE_URL` or `ln -sf ../../apm/public/assets public/assets` |
| CBP Modules empty | `STAFF_API_USERNAME` / `PASSWORD` (same as APM) |
