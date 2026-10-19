# Migration backup & restore

**Date:** 2026-09-19  
**Status:** Implemented (2026-09-19); per-download encryption envelope added same day  
**Surface:** Admin panel (`/backup`) — admins only

## Problem

When moving Email Server Admin to a new host, operators need a reliable way to carry over:

1. User accounts (including partner registrations)
2. Integration clients (credentials that connecting systems already use)
3. Email provider connection details (SMTP / Exchange / etc.)
4. User ↔ client ownership links
5. Branding (colors, names, logos)

A raw MySQL dump is a poor fit: provider `config` is encrypted with `APP_KEY`, so a different key on the new server breaks decryption. Client secrets are stored as hashes (plaintext cannot be recovered), but the hash must travel so existing `client_id` + secret pairs keep working.

Downloaded packages must not leave credentials readable on disk. Each download is sealed with a unique system-generated key that is shown once and required on restore.

## Decisions (locked)

| Topic | Choice |
|---|---|
| Portability | **A** — portable package with **decrypted** provider secrets inside the sealed payload; re-encrypt on import with the destination `APP_KEY`. Client secrets remain **hashes** (existing credentials keep working; secrets cannot be re-displayed). |
| Package encryption | **Per-download AES-256-GCM** with a fresh 256-bit random key (base64url). Key is returned once with the export response and never stored server-side. Import requires the key. No password KDF — full-entropy key so offline brute-force is infeasible (~2²⁵⁶). |
| Restore UX | **1** — Admin UI download + upload (app applies upsert). No reliance on `mysql` CLI import for this flow. |
| Scope | **C** — users, clients, providers, user↔client links, branding (including logo/favicon bytes). |
| Excluded | Email logs, audit logs, blocked IPs/emails, password-reset tokens, Sanctum personal access tokens. |

## Approach

**Encrypted, versioned JSON migration package** (`schema_version` 2 envelope) + admin **Backup / Restore** page.

Do not use mysqldump for this feature. The application owns package encrypt/decrypt, `APP_KEY` re-encrypt of provider configs, and upsert rules.

## Package format

Filename: `email-server-migration-YYYYMMDD-HHMMSS.json`

Outer envelope (what is written to disk):

```json
{
  "meta": {
    "schema_version": 2,
    "encrypted": true,
    "cipher": "aes-256-gcm",
    "kdf": "none",
    "key_bytes": 32
  },
  "nonce": "<base64>",
  "tag": "<base64>",
  "ciphertext": "<base64>"
}
```

Inner plaintext (AES-256-GCM decrypted payload, still JSON):

```json
{
  "meta": {
    "schema_version": 1,
    "exported_at": "2026-09-19T18:00:00+00:00",
    "app_name": "Email Server",
    "source_app_url": "https://notifications.africacdc.org"
  },
  "email_providers": [ /* ... */ ],
  "users": [ /* ... */ ],
  "external_integrations": [ /* ... */ ],
  "user_client_links": [
    { "user_email": "partner@example.com", "client_slug": "my-app" }
  ],
  "branding": { /* ... */ }
}
```

### Match keys (natural IDs)

| Entity | Match key | Notes |
|---|---|---|
| Email provider | `slug` | Insert if missing; update if present |
| User | `email` | Insert if missing; update if present |
| Client | `slug` | Insert if missing; update if present |
| Links | user email + client slug | After users/clients imported, sync links for users present in the package |
| Branding | singleton row | Overwrite fields; restore binary assets when provided |

### Field rules

**Email providers**

- Export: decrypt `config` via `safeConfig()` / model accessors so the sealed payload contains usable credentials.
- Import: write through the model so `config` is re-encrypted with the destination `APP_KEY`.
- Preserve `driver`, from address/name, `is_default`, `is_active`, `priority`, `description`.
- If multiple providers claim `is_default`, the last one imported as default wins (clear others).

**Users**

- Export: include password hash, approval fields, phone, organisation, registration_source, admin/active flags, TOTP-related columns needed for login continuity (`two_factor_*`, `totp_required`, encrypted secret + recovery codes as exportable ciphertext or re-exportable values the model can restore).
- Import: **create** when email is absent; **update** when email exists (profile, approval, flags, password hash, 2FA fields from package).
- Do not delete users absent from the package.
- `created_by` / `approved_by`: resolve by email if the referenced user is in the package or already on the destination; otherwise null.

**External integrations (clients)**

- Export: `name`, `slug`, `api_key_hash`, `api_key_prefix`, `allowed_ips`, `settings`, `is_active`, `description`, provider reference by **provider slug** (not numeric id).
- Import: upsert by `slug`; update hash/prefix/settings/active/provider link so existing client credentials continue to authenticate.
- Do not regenerate secrets on import.

**User ↔ client links**

- Export as `{ user_email, client_slug }` pairs.
- Import: for each user appearing in the package links, `sync` their client set to the package set (clients resolved by slug). Users not mentioned in links are left unchanged.

**Branding**

- Export scalar fields (`primary_color`, `secondary_color`, `app_name`, logo size, etc.).
- Export `logo`, `logo_dark`, `favicon` as optional objects `{ "filename": "...", "mime": "...", "base64": "..." }` when files exist on disk.
- Import: overwrite branding row; write files into the normal branding storage paths used by `BrandingSetting`.

## API

Admin-only (`EnsureUserIsAdmin`):

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/v1/admin/migration/export` | JSON: `{ encryption_key, filename, package }` — key shown once, never stored |
| `POST` | `/api/v1/admin/migration/import` | Multipart: `file` + `encryption_key`; returns summary |

### Export response shape

```json
{
  "encryption_key": "<base64url 256-bit key>",
  "filename": "email-server-migration-YYYYMMDD-HHMMSS.json",
  "package": { "meta": {}, "nonce": "", "tag": "", "ciphertext": "" },
  "message": "Copy and store the encryption key now..."
}
```

### Import response shape

```json
{
  "message": "Migration imported.",
  "data": {
    "providers": { "created": 1, "updated": 2 },
    "users": { "created": 3, "updated": 1 },
    "clients": { "created": 0, "updated": 4 },
    "links_synced": 5,
    "branding_updated": true,
    "warnings": []
  }
}
```

## UI

- Route: `/backup` (name: `backup`), admin-only (same gate as other admin tools).
- Sidebar: **Backup / Restore** (e.g. `mdi-database-export`).
- Page actions:
  - **Download encrypted package** → browser saves envelope JSON; modal shows the one-time encryption key (copy required before dismiss)
  - File picker + encryption key field + **Restore** with confirm dialog
  - Result summary (created/updated counts + warnings)

## Safety & ops

- Confirm before import.
- Audit events: `migration_exported`, `migration_imported` (no secret values or encryption keys in audit payloads).
- Throttle import (e.g. 5/hour) and export (e.g. 20/hour).
- Max upload size aligned with nginx (`20M`); reject non-JSON / wrong outer `schema_version` / missing key.
- Run import inside a DB transaction; roll back on hard failures.
- Soft warnings (e.g. missing provider slug for a client) collected in `warnings` without aborting the whole import when the row can be skipped safely; abort + rollback on structural/validation errors.
- Wrong key and tampered ciphertext share one error message (no decrypt oracle).

## Security notes

- Credentials live only inside the AES-256-GCM ciphertext. The downloaded file alone is not usable.
- Key is 256 bits of CSPRNG entropy (`random_bytes`), not a user password — offline guessing is not practical.
- Key is never persisted on the server; losing it means the package cannot be restored.
- Only admins can export/import.
- Destination server may use a different `APP_KEY`; that is intentional for Approach A.

## Out of scope

- Scheduled/automatic backups
- Restoring email logs or audit history
- Re-issuing plaintext client secrets
- CLI `mysql` import path for this package
- Password-based package encryption (deliberately avoided for brute-force resistance)

## Success criteria

1. Admin can download an encrypted package from server A, copy the one-time key, and restore on server B with a different `APP_KEY` by supplying that key.
2. Provider SMTP/Exchange credentials work on B after import.
3. Existing client_id + client_secret pairs still obtain JWTs on B.
4. Users missing on B are created; existing users/clients matched by email/slug are updated.
5. Partner user ↔ client links and branding (including logos when present) are restored.
6. Feature tests cover encrypted export, missing/wrong key rejection, and import upsert behaviour.
