# Helpdesk licenses, shared date picker & settings layout

**Date:** 2026-07-22  
**Status:** Approved (conversation) — awaiting spec file review  
**Scope:** Approach 1 (incremental polish)

## Goals

1. Shared Vuetify-based date input (`UDateInput`) reused across tools and filters.
2. Shared staff directory picker for license responsible person.
3. Licenses: search, responsible person, expose per-license warning days.
4. License expiry email alerts to responsible person + all helpdesk admins.
5. General settings: enable alerts + configurable interval (1 / 3 / 7 days); natural-height settings cards.
6. Agents search: already present; keep/verify only.

## Non-goals

- Full Atomic Design folder restructure (`atoms/` / `molecules/` / …).
- Global “days before expiry” setting (per-license `warning_days_before` only).
- Non-email channels (WhatsApp/Teams).
- Changing agent routing behavior from prior work.

## Decisions (locked)

| Topic | Choice |
|--------|--------|
| Admin recipients | All helpdesk admins (`ROLE_ADMIN` / `grant_helpdesk_admin`) |
| Warning window | Per-license `warning_days_before` only |
| Settings | Enable/disable job + reminder interval (1 / 3 / 7 days) |
| Reminder cadence | Configurable interval while in warning window |
| UI reuse | `U*` form atoms matching `UColorInput` pattern |

## Design

### 1. Shared form atoms (`helpdesk/frontend/src/components/ui/`)

**`UDateInput.vue`**
- Wrap Vuetify labs `VDateInput` (register in `plugins/vuetify.ts` like `VColorInput`).
- `defineModel<string | null>` as ISO `YYYY-MM-DD`.
- Honor `UFormField` / `formContext` (label, required, errors).
- Register in `register.ts`.

**`UStaffDirectoryPicker.vue`**
- Debounced `GET /api/v1/reference-data/staff?q=`.
- Emits / models `staff_id` (+ optional display name/email for UI).
- Reuse later elsewhere; first consumer = licenses form.

**Replace call sites**
- `ItAssetsView.vue` — `purchase_date`
- `LicensesView.vue` — `purchase_date`
- `ReportsView.vue` — date from/to (×2)
- `LoggingAuditPanel.vue` — date from/to

Mirror under `helpdesk/client/` only if that tree is still actively built; production serves `frontend/dist-build`.

### 2. Agents

- Confirm client-side `agentSearch` / `filteredAgents` on Settings → Agents.
- No API change unless search is missing in deployed build (rebuild already done previously).

### 3. Licenses

**Schema**
- Add `responsible_staff_id` (nullable unsigned int, indexed).
- Add `expiry_alert_last_sent_at` (nullable timestamp) for interval throttling.
- Keep existing `warning_days_before` (default 30); expose in UI.

**API / model**
- Accept and return `responsible_staff_id`.
- Resolve display fields from Staff directory when listing/showing (name, email) without denormalizing permanently (or cache name/email on write if directory lookup is expensive — prefer live resolve like other tools).

**UI (`LicensesView.vue`)**
- Search box filtering name, vendor, license key, responsible person.
- Form: responsible person via `UStaffDirectoryPicker`; `warning_days_before` number field.
- Table column for responsible person + existing expiry badges.

### 4. Expiry alerts

**Settings keys** (after Monthly agent reports card)
- `license_expiry_alert_enabled` — `"1"` / `"0"`
- `license_expiry_alert_interval_days` — `1` | `3` | `7` (default `7`)

**Job** (daily, existing scheduler pattern)
- If disabled → no-op.
- For each active license with `expiry_date` and `days_until_expiry <= warning_days_before` and not past a sensible cutoff (include expired until marked inactive/renewed):
  - If `expiry_alert_last_sent_at` is null or older than `interval_days` → send mail.
  - Recipients: responsible person’s work email (if resolvable) + all helpdesk admin emails.
  - Update `expiry_alert_last_sent_at` after successful send.
- Clear or ignore last-sent when license renewed past warning window (optional: null out when `days_until > warning`).

**Mail**
- Follow existing Helpdesk mailable/branding patterns.
- Subject/body: license name, vendor, expiry date, days remaining, link to licenses tool if available.

### 5. General settings layout

```css
.settings-grid {
  align-items: start;
}
```

Add new settings card: **License expiry alerts** (enable + interval select), placed after Monthly agent reports.

## Testing

- Feature: settings save/load for new keys.
- Feature/unit: alert job sends when in window and due by interval; skips when disabled / too soon / no recipients.
- Feature: license CRUD with `responsible_staff_id` + `warning_days_before`.
- Manual: date picker on IT assets + licenses; search on licenses; settings cards unequal height.

## Out of scope follow-ups

- Extract more directory pickers to replace TicketCreate inlined search.
- Per-admin opt-out for license emails.
