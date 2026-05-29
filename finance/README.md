# Africa CDC Finance Management

Laravel 12 + **Inertia.js (React)** for the CBP Finance module. Navigation and styling follow **APM** (`/staff/apm/`): top bar, primary menu, and CBP Modules dropdown.

| Area | Path |
|------|------|
| Application | [`app/`](./app/), [`routes/`](./routes/), [`config/`](./config/) |
| Inertia UI | [`resources/js/`](./resources/js/) |
| Apache entry | [`public/`](./public/), [`.htaccess`](./.htaccess), [`server.php`](./server.php) |
| Documentation | [`documentation/`](./documentation/) |

**Staff portal:** `http://localhost/staff/` · **Permission:** `92` (Finance)

---

## Quick start

```bash
cd finance
./setup.sh
```

Or manually:

```bash
cd finance
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install --legacy-peer-deps --cache ./.npm-cache
npm run build
```

### Environment (`.env`)

Copy from Staff root / APM:

| Variable | Purpose |
|----------|---------|
| `APP_URL` | e.g. `http://localhost/staff/finance` |
| `JWT_SECRET` | Must match Staff root `.env` |
| `BASE_URL` | e.g. `http://localhost/staff/` |
| `FINANCE_ASSETS_BASE_URL` | e.g. `http://localhost/staff/apm` (theme CSS) |
| `STAFF_API_*` | CBP Modules Share API |

Optional theme symlink:

```bash
ln -sf ../../apm/public/assets public/assets
```

### URLs (local)

| What | URL |
|------|-----|
| SSO entry (Staff home tile) | `http://localhost/staff/finance/?token=…` |
| Dashboard | `http://localhost/staff/finance/dashboard` |

Apache rewrites `/staff/finance/*` through [`server.php`](./server.php) → [`public/index.php`](./public/index.php) (same pattern as APM).

### Development

There is **no separate React app on port 3002** (that was the old Node/CRA stack). Finance is a single Laravel + Inertia app under `/staff/finance/`.

**Daily dev (Apache + built assets)** — recommended when testing Staff SSO:

```bash
cd finance
npm run build    # after JS/CSS changes
```

Then open `http://localhost/staff/finance/dashboard` (hard-refresh if the page was blank after an old build).

**Hot reload (optional)** — Vite on port **5173**, not 3002:

```bash
cd finance
npm run dev      # writes public/hot; @vite loads from :5173
```

Keep using Apache at `APP_URL` for SSO; only use `composer run dev` if you run Laravel’s built-in server instead of Apache.

If `npm run build` fails with `EACCES` on `public/build/`, fix ownership: `sudo chown -R $(whoami):staff public/build`.

---

## Authentication

1. User signs in on Staff (`/staff/auth`).
2. Home opens Finance with `?token=` (JWT or legacy base64, same as APM).
3. `GET /` stores session and redirects to `/dashboard`.
4. Permission **92** required (`FINANCE_SSO_PERMISSION_ID`).

---

## Project layout

```
finance/
├── app/                  # HTTP, services (CBP nav, SSO)
├── resources/js/         # Inertia + React (APM-style layout)
├── routes/web.php
├── public/               # Web root + Vite build
├── .htaccess             # Rewrites to public/ and server.php
├── server.php
├── documentation/
└── setup.sh
```

---

## Pages

| Route | Status |
|-------|--------|
| `/dashboard` | Starter UI |
| `/my-advances`, `/my-missions`, `/budgets` | Placeholders |

Add screens: route in `routes/web.php` + `resources/js/Pages/*.jsx` using `AppLayout`.

---

## Documentation

- [Quick start](./documentation/QUICKSTART.md)
- [Apache](./documentation/APACHE.md)
- [Authentication](./documentation/AUTHENTICATION.md)
- [Laravel + Inertia](./documentation/LARAVEL_INERTIA.md)

Related: [APM documentation](../apm/documentation/)

---

## Production

1. `APP_URL=https://<host>/staff/finance`
2. `composer install --no-dev`, `npm run build`, `php artisan migrate --force`
3. Align `cbp_modules` production URL for `finance_management`
4. Match `JWT_SECRET` and `STAFF_API_*` with Staff/APM
