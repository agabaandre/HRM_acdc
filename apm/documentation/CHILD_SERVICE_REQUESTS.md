# Supplementary service requests

This guide describes **supplementary service requests** in APM (UI label; stored as child SRs via `parent_service_request_id`): requests that cover the **remaining memo balance** after a parent service request has requested less than the full original memo budget.

## Purpose

When a parent service request’s **Total Requested Funds** is less than the **Original Memo Budget**, funds may still be needed for the same approved memo (DSA, imprest, tickets, etc.). A **child service request**:

- Uses the **same source memo** as the parent (`source_type`, `source_id`, activity, fund type, division).
- Is capped at the parent’s **remaining balance**: `original_total_budget − new_total_budget` on the parent at the time the child is created.
- Follows the **same approval workflow** as a normal service request.
- Is linked to the parent via `service_requests.parent_service_request_id`.

Only **one direct child** may exist per service request (root or nested). **Nested children** are allowed: a child SR may have its own child when it still has **remaining balance** (`original_total_budget − new_total_budget` on that SR).

## System setting

| Key | Group | Type | Default |
|-----|-------|------|---------|
| `allow_child_service_requests` | `service_requests` | boolean | `1` (on) |

**UI:** [App Settings → Service requests](https://cbp.africacdc.org/staff/apm/system-settings) (`/system-settings`).

When the setting is off, the **Create supplementary request** button is hidden and new supplementary SRs cannot be created.

**Seed / deploy:**

```bash
php artisan db:seed --class=SystemSettingsSeeder
```

## When the “Create supplementary request” button appears

On the parent service request **detail** page (`GET /service-requests/{id}`), the button is shown only when **all** of the following are true:

| Rule | Implementation |
|------|----------------|
| Feature enabled | `ServiceRequest::childRequestsEnabled()` → `allow_child_service_requests` |
| Parent still has unrequested balance | `remainingMemoBalanceForChild()` &gt; 0 on the SR you are creating from (root or child) |
| Parent has a linked source memo | `source_type` and `source_id` set |
| Remaining memo balance &gt; 0 | `remainingMemoBalanceForChild()` &gt; 0 (parent `original_total_budget` &gt; parent `new_total_budget`) |
| No child already exists | `childServiceRequests()->exists()` is false |
| User can act as creator | `is_with_creator_generic($serviceRequest)` (same as edit/submit on draft) |

**Create URL:** `GET /service-requests/create?parent_service_request_id={parentId}`

If the parent is not eligible, create redirects back with an error message.

## User workflow

1. Open the **parent** service request (e.g. `/service-requests/333`).
2. Confirm **Original Memo Budget** &gt; **Total Requested Funds** and that no child is listed.
3. Click **Create supplementary request**.
4. Complete the child form (pre-filled from the parent memo). Budget summary shows **Maximum allowable (remaining balance)** instead of full memo budget.
5. Ensure **Total Requested Funds** does not exceed that cap (client-side validation and server-side check on save/submit).
6. Save as draft or submit for approval as usual.

### Identification in the UI

- **Banner** (warning style): “Supplementary service request”, cap amount, link to **previous service request document number**.
- **Badge** on the detail header and subject area.
- **Parent** detail page: info alert when a child already exists, with link to the child’s document number.

### PDF / print

Child requests show a plain-text note **after the Subject** on the memorandum PDF (no border): supplementary funds, the **balance remaining on the previous service request**, and the parent’s **document number** (falls back to `request_number`, then `SR #id` only if both are missing). The subject line may still show a **CHILD SERVICE REQUEST** label.

### PDF attachments (print pack order)

At the end of the service request PDF (same approach as **Change Request Memo** / **Original Approval Memo** embeds), approved **previous service requests** are included **before** the Original Approval Memo:

1. Change Request memo (if this SR came from a CR)
2. **Previous Service Request** section(s) — one embed per approved parent-chain and/or same-memo SR (oldest first); label uses `document_number`, then `request_number`, then `SR #id` only if both are missing
3. **Original Approval Memo** (source memo)

Only records with `overall_status = approved` are embedded. Pending/draft parents are omitted from the attachment block.

## Budget rules

| Field (child) | Meaning |
|---------------|---------|
| `original_total_budget` | Set to parent’s **remaining balance** at child **creation** (fixed cap for that child) |
| `new_total_budget` | Sum of requested costs on the child; must be ≤ cap |

**Parent remaining balance** (at create time; same formula for root or nested parent):

```text
remaining = max(0, parent.original_total_budget − parent.new_total_budget)
```

On a **root** SR, `original_total_budget` is the full memo budget. On a **child** SR, `original_total_budget` is the cap allocated when that child was created; any further child uses what that SR did not request.

**Validation:**

- **Store:** rejects `new_total_budget` &gt; cap; copies parent source fields and sets `parent_service_request_id`.
- **Update:** child updates cannot exceed the child’s stored `original_total_budget` (the cap at creation).

The child does **not** change the parent’s totals; it only consumes the “shortfall” the parent did not request.

## Database

Migration: `2026_05_20_100000_add_parent_service_request_id_to_service_requests_table.php`

| Column | Type | Notes |
|--------|------|--------|
| `parent_service_request_id` | nullable `unsignedBigInteger` FK → `service_requests.id` | Indexed; `null` = parent or standalone SR |

```bash
php artisan migrate
```

## Code reference

| Area | Location |
|------|----------|
| Model helpers | `App\Models\ServiceRequest` — `parentServiceRequest()`, `childServiceRequests()`, `isChildRequest()`, `childRequestsEnabled()`, `remainingMemoBalanceForChild()`, `canCreateChildRequest()` |
| HTTP | `App\Http\Controllers\ServiceRequestController` — `create`, `store`, `update`, `show`, PDF build |
| Views | `resources/views/service-requests/show.blade.php`, `create.blade.php`, `partials/child-request-banner.blade.php`, `print.php` |
| Setting seed | `database/seeders/SystemSettingsSeeder.php` |
| Settings group label | `App\Http\Controllers\SystemSettingsController` — group `service_requests` |

## Relationship to other rules

- **One service request per memo (initial):** The usual rule still applies when creating the **first** SR from an approved document. The child is an exception: it is created from an **existing parent SR**, not directly from the memo button again.
- **Intramural:** Parent SRs are still tied to intramural memos; children inherit the parent’s source and fund type.
- **Change requests:** Child creation is separate from change-request–driven SR creation; do not mix `change_request_id` with `parent_service_request_id` on the same create flow.

## Troubleshooting

| Issue | Check |
|-------|--------|
| Button missing | Setting off, balance already fully requested, child already exists, or user is not the creator |
| Cannot open create link | Parent failed `canCreateChildRequest()` (redirect with flash error) |
| Save rejected over cap | Reduce line items; cap is shown in banner and budget summary |
| Setting not in UI | Run `SystemSettingsSeeder` or add key manually under group `service_requests`, type `boolean` |

## Related documentation

- [User Guide — Creating a Service Request](./USER_GUIDE.md#creating-a-service-request) (parent flow)
- [User Guide — Supplementary service requests](./USER_GUIDE.md#supplementary-service-requests)
- [Document numbering](./DOCUMENT_NUMBERING_SYSTEM.md) — child SR receives its own SR document number
- [Approvers Guide](./APPROVERS_GUIDE.md) — service request approval workflow
