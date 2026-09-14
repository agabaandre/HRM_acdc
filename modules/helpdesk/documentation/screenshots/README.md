# Screenshots for Helpdesk documentation

PNG files in this folder are generated automatically.

## Regenerate

From the `helpdesk/` directory:

```bash
npm install
npx playwright install chromium
npm run docs:screenshots
```

### Public pages only (no login)

Captures the TV lobby dashboard:

```bash
HELPDESK_DOC_BASE_URL=https://cbp.africacdc.org/staff/helpdesk npm run docs:screenshots
```

Without `HELPDESK_DOC_TOKEN`, all routes are still captured (login/SSO gate or empty shell). Set `HELPDESK_DOC_SKIP_AUTH_ONLY=1` to capture **only** public routes when no token is available.

### Full UI capture (authenticated)

1. Sign in to the Staff portal and open Helpdesk (or copy the JWT from the redirect URL `?token=…`).
2. Run:

```bash
export HELPDESK_DOC_BASE_URL=https://cbp.africacdc.org/staff/helpdesk
export HELPDESK_DOC_TOKEN='paste-jwt-here'
npm run docs:screenshots
```

Output files are named `01-home-knowledge-base.png` through `15-tv-screen.png`. A `manifest.json` records capture time and any skipped routes.

Referenced from [USER_GUIDE.md](./USER_GUIDE.md) and [ADMIN_GUIDE.md](./ADMIN_GUIDE.md).
