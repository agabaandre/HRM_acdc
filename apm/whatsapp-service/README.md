# APM WhatsApp Service

Native WhatsApp worker for the APM staff platform. Uses [Baileys](https://github.com/WhiskeySockets/Baileys) for the WhatsApp protocol and **MySQL** (same database as Laravel) for groups and members.

Laravel remains the admin API and UI; this process maintains the live WhatsApp session and syncs groups into `whatsapp_groups` / `whatsapp_group_members`.

## Setup

1. Copy env and align with APM `.env` database settings:

```bash
cd apm/whatsapp-service
cp .env.example .env
```

2. Set `WORKER_TOKEN` to the same value as **System configs → WhatsApp → Worker token** (`whatsapp_worker_token` in `system_settings`). A token is auto-generated when you run the platform migrations.

3. Install and start:

```bash
npm install
npm start
```

Default port: **8765** (`whatsapp_worker_url` in APM settings).

## Link WhatsApp

1. In **System configs → WhatsApp**, enter the bot number and click **Get pairing code**.
2. On your phone: WhatsApp → Settings → Linked devices → Link with phone number.
3. Enter the code shown in APM.
4. Click **Sync groups** once connected.

## Internal API

Protected by `X-Worker-Token` header (called by Laravel only):

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/health` | Process health |
| GET | `/internal/status` | Connection state |
| POST | `/internal/pair` | Request pairing code `{ phoneNumber }` |
| POST | `/internal/sync` | Pull groups/members from WhatsApp → MySQL |

## External bot mode

Set `whatsapp_driver` to `external` in system settings to keep using [WhatsAppBotMultiDevice](https://github.com/jacktheboss220/WhatsAppBotMultiDevice) instead of this worker.

## Production

Run under systemd/supervisor, e.g.:

```
WorkingDirectory=/path/to/staff/apm/whatsapp-service
ExecStart=/usr/bin/node src/index.js
Restart=always
```

Ensure `storage/whatsapp-auth` is writable and backed up (session credentials).

## Security

- Worker binds to **127.0.0.1 only** (`BIND_HOST`) — not reachable from the public internet.
- All endpoints (including `/health`) require **`X-Worker-Token`** (min 32 chars).
- Token comparison uses **timing-safe** equality in Node.
- Laravel encrypts worker token and external admin password at rest (`WhatsAppSecretStore`).
- Native worker URL is restricted to `http://127.0.0.1:*` (SSRF protection).
- WhatsApp routes use configurable access: **module** (Staff → groups, default all staff) and **config** (System configs → WhatsApp, default role 10). CSRF on POST and rate limits apply.
- Pairing/QR/sync actions are audit-logged (`storage/logs`).
