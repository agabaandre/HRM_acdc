# Business Unit mailbox intake (Exchange Graph)

**Date:** 2026-07-22  
**Status:** Approved in conversation; awaiting spec file review before implementation plan  
**Approach:** Scheduled Graph poll (every 1 minute)

## Problem

IT support mail arrives at dedicated mailboxes (starting with `helpdesk@africacdc.org` for IT & MIS). Helpdesk today only **sends** mail via Exchange Graph. There is no inbound poll, so email is not turned into tickets, categorized, or assigned.

## Goals

1. Each **Business Unit** may have a dedicated **support mailbox**.
2. Poll those mailboxes every **1 minute** using existing `EXCHANGE_*` app credentials.
3. On success: create a helpdesk ticket (`source=email`), AI-categorize within that BU, assign eligible agents.
4. If categorization fails or yields no category: assign a **helpdesk admin** using existing **least open-workload** logic (`TicketAssignmentService::assignAdminRoundRobin`).
5. After a ticket is created for a message: **mark as read** and **move to a “Processed” folder** (create folder if missing).
6. Settings UX: Business Units edited in a **modal** (table is crowded with Anonymous / Allow Asset / mailbox fields).

## Non-goals (this iteration)

- Graph change-notification webhooks / subscriptions.
- IMAP.
- Per-mailbox OAuth (delegated user tokens); app-only client credentials only.
- Replies that reopen existing tickets by thread (new message = new ticket unless later extended).
- Attachment download into ticket files (optional follow-up; body + subject first).
- Reading Sent Items or folders other than Inbox for intake.

## Configuration

### Business Unit fields

| Field | Type | Notes |
|-------|------|--------|
| `support_mailbox` | string nullable | UPN/email, e.g. `helpdesk@africacdc.org` |
| `email_intake_enabled` | boolean default false | Must be on + non-empty mailbox to poll |

**Seed:** IT & MIS → `support_mailbox = helpdesk@africacdc.org`, `email_intake_enabled = true` (or enabled after admin confirms Graph `Mail.Read` is granted — document in ADMIN guide; default enable true for IT & MIS if Graph already used for send).

### Env (unchanged + Graph permission)

Reuse:

- `EXCHANGE_TENANT_ID`, `EXCHANGE_CLIENT_ID`, `EXCHANGE_CLIENT_SECRET`
- `EXCHANGE_AUTH_METHOD=client_credentials`
- `EXCHANGE_SCOPE` should include application permissions needed for **send and read** (e.g. `https://graph.microsoft.com/.default` with Azure app roles `Mail.Send` + `Mail.Read` on the mailboxes, or `Mail.ReadWrite` if move requires it)

**Ops requirement:** Azure app registration must be granted **application** permission to read/move mail for each BU mailbox (application access policy / Exchange Online RBAC as per org practice). Document in `documentation/ADMIN_GUIDE.md` / `INTEGRATION.md`.

### Settings UI

**Business units tab** (`CategoriesManagementPanel`):

- Table columns (lean): Name, Categories (count/chips summary), Active, Actions (Edit / Delete).
- **Add** and **Edit** open a **modal** with: name, slug, description, sort order, active, allow anonymous, allow asset on resolve, support mailbox, email intake enabled.
- Create flow uses the same modal.

No new General settings keys required for mailbox address (lives on BU). Optional later: last poll summary on Jobs page — out of scope unless cheap to add as `HelpdeskSetting` JSON.

## Runtime flow

```
Every 1 minute (scheduler)
  → PollBusinessUnitMailboxesJob
      for each BU where email_intake_enabled && support_mailbox filled
        → Graph: list unread Inbox messages (page, cap per run e.g. 25)
        → for each message:
            if already in helpdesk_email_messages (graph_message_id) → skip
            resolve requester from From: via Staff directory email match
            create HelpdeskTicket:
              business_unit_id = BU
              category_id = null
              source = email
              subject from email subject (truncated)
              description from body (prefer text, else stripped HTML)
              requester_* from directory or From header
            insert helpdesk_email_messages row (idempotency + ticket_id)
            dispatch CategorizeTicketWithAi (existing)
            Graph: mark read + move to folder "Processed" (ensure folder exists)
```

### Categorize & assign (existing)

Reuse `CategorizeTicketWithAi` / `TicketAiCategorizationService` scoped to the ticket’s BU categories.

- Success → `TicketAssignmentService::assignAgent`
- Failure / no match → `TicketAssignmentService::assignAdminRoundRobin` (least open load among helpdesk admins)

### Idempotency

Table `helpdesk_email_messages`:

| Column | Purpose |
|--------|---------|
| `id` | PK |
| `business_unit_id` | FK |
| `graph_message_id` | unique |
| `internet_message_id` | nullable, indexed |
| `ticket_id` | nullable FK |
| `from_email`, `subject` | audit |
| `processed_at` | timestamp |
| `raw_meta` | JSON optional (folder, conversationId) |

Unique on `graph_message_id`. If ticket create fails after insert, leave row with null ticket for retry policy (or delete row on hard fail — prefer: insert only after successful ticket create, with a short-lived “processing” lock key in cache by graph id to avoid double create under concurrency).

**Concurrency:** use cache lock `email-intake:{graph_message_id}` during processing.

### Graph reader

New service (alongside existing send path), e.g. `ExchangeGraphMailReader` / extend OAuth client:

- `GET /users/{mailbox}/mailFolders/inbox/messages?$filter=isRead eq false&$top=25&$select=...`
- Ensure folder: list child folders under Inbox or root named `Processed`; create if missing
- `PATCH` isRead=true; `POST .../move` to Processed

Prefer **Mail.ReadWrite** application permission if move + mark read require it; document minimum permissions.

## Scheduler & workers

- Register in Laravel schedule: `PollBusinessUnitMailboxesJob` **everyMinute**, without overlapping (`withoutOverlapping`).
- Job may dispatch on default or `helpdesk` queue; ensure deploy workers listen to queues used by categorize (`helpdesk-ai`) as today.
- Document that `php artisan schedule:run` (or systemd timer) must run every minute.

## Failure handling

| Case | Behavior |
|------|----------|
| Graph auth / 403 | Log; skip BU; surface last error in logs |
| Single message parse error | Log; leave unread (do not move); continue others |
| Ticket create OK, move fails | Log warning; message may be re-read → idempotency skips duplicate ticket |
| AI categorize down | Existing admin fallback |

## Testing

- Feature/unit: reader mocked → ticket created with `source=email`, BU set, idempotent second poll.
- Categorize fail path → admin assignment (reuse patterns from `BusinessUnitTicketCreateTest`).
- UI: modal save persists mailbox + intake flag.

## Docs to update

- `documentation/ADMIN_GUIDE.md` — enable intake, Azure permissions, Processed folder
- `documentation/INTEGRATION.md` — Mail.Read / ReadWrite + mailbox mapping
- `documentation/SYSTEMD.md` — schedule every minute if not already

## Open points resolved in conversation

| Topic | Decision |
|-------|----------|
| Mailbox location | On Business Unit |
| IT & MIS mailbox | `helpdesk@africacdc.org` |
| Post-process | Mark read + move to Processed |
| Poll interval | Every 1 minute |
| BU settings UX | Modal (not crowded inline table) |
| Uncategorized | Admin least-workload assignment |
| Transport | Microsoft Graph with existing EXCHANGE_* env |
