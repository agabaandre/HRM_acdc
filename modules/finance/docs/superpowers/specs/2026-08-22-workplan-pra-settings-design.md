# Workplan / PRA API settings

Admins configure the Africa CDC PRA public workplan API in **Settings → Workplan / PRA** (`/settings/workplan`) instead of only `.env`.

## Behaviour

- Permission **15** (same as other settings cards).
- Fields: API URL, API key, tiers, fiscal year, divisions, division aliases, timeout.
- Stored in `workplan_pra_settings` (key/value). API key is encrypted at rest.
- GET never returns the raw API key; it returns `api_key_set`.
- Blank API key on save keeps the existing key (or env fallback if none saved).
- `.env` `PRA_WORKPLAN_*` remains the fallback until Settings values are saved.
- `PraWorkplanClient` and `PraWorkplanSyncService` read the merged settings.
- Workplan **Sync from PRA** stays on `/workplan`. If not configured, the page links to this settings screen.

## Out of scope

- Changing Staff jobs schedule UI (still runs `workplan:sync-pra`).
- Writing back into `.env`.
