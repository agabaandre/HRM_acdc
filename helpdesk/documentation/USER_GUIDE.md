# Helpdesk — User Guide

Welcome to the **Africa CDC IT Service Desk**. This guide walks every audience through what they can do in the helpdesk SPA, including a step-by-step walkthrough of **creating a ticket**.

> The helpdesk is reached from the Staff portal home page (the **IT Service Desk** tile). You will be signed in automatically — there is no separate password.

## Table of contents

1. [Audiences](#audiences)
2. [Getting in](#getting-in)
3. [The home page](#the-home-page)
4. [Creating a ticket](#creating-a-ticket) ← step-by-step
5. [Tracking your tickets (requesters)](#tracking-your-tickets-requesters)
6. [Confirming a resolution](#confirming-a-resolution)
7. [Agent desk](#agent-desk-agents--supervisors)
8. [Reassigning a ticket](#reassigning-a-ticket-permission-required)
9. [Knowledge base](#knowledge-base)
10. [Reports](#reports)
11. [TV / lobby dashboard](#tv--lobby-dashboard)
12. [Admin settings](#admin-settings-administrators)
13. [Troubleshooting](#troubleshooting)

---

## Audiences

The helpdesk recognises five roles. Each user gets exactly one, set automatically the first time they sign in (admins can refine it later).

| Role | Who | What they can do |
|---|---|---|
| **Requester** (`user`) | Default for every staff member | File tickets, view their own tickets, comment, confirm resolutions, browse the knowledge base. |
| **Agent** | Members of designated agent divisions or staff explicitly marked by an admin | Everything a requester can, plus: see all tickets in their queue, change status/priority, add internal notes, attach files, submit resolutions. |
| **Supervisor** | Senior agents | Same actions as agents (used for reporting splits and category routing). |
| **Admin** | Helpdesk owners (mapped from Staff portal role groups) | Full access: settings, categories, SLA rules, agents, knowledge base, audit log. |
| **Auditor** | Compliance / read-only staff | Read access to tickets and reports without write permissions. |

Two granular flags live on top of the agent role and can be toggled by admins from **Settings → Agents**:

- **`can_manage_kb`** — lets a non-admin create / edit knowledge-base articles.
- **`can_reassign_tickets`** — lets an agent hand a ticket over to a different agent (with a reason).

---

## Getting in

1. Sign in to the **Staff portal** (`/staff/`) as usual.
2. On the home page, look for the **IT Service Desk** tile and click it.
3. You'll land on the helpdesk SPA at `/staff/helpdesk/` already signed in. Your name and role appear in the top bar.

If you ever see _"helpdesk_error=sso"_ on the portal home, your account does not currently have the helpdesk permission. Ask an administrator to grant Staff permission **85**, **92**, or **93** (the codes recognised by `HELPDESK_SSO_PERMISSION_CODES`).

---

## The home page

The helpdesk home page combines two things:

- **A searchable knowledge base** browser, grouped by category. Type a question — the matching FAQs filter live.
- **Quick links** — depending on your role, you'll see shortcuts to *Create ticket*, *My tickets*, *Agent desk*, *Knowledge base management*, *Reports*, and *Settings*.

You can return to the home page any time by clicking the **IT Service Desk** logo in the top-left.

---

## Creating a ticket

You can open this flow from:

- The **+ New ticket** button on the **My tickets** page (`/tickets`),
- The **Create ticket** card on the home page, or
- Directly at `/tickets/new`.

### Who you can file for

- **As a requester** — you can file a ticket for yourself, or, if you know the staff ID of a colleague, choose them as the requester (helpful when you log an issue someone reported to you in person).
- **As an agent / supervisor / admin** — you **must** select a requester from the Staff directory before saving. The picker searches `name`, `work email`, and `staff id` against the Staff portal directory in real time. The form sets a `agent_logged_for_requester` flag automatically so reports can tell agent-logged tickets apart from self-service.

### Step-by-step

1. **Open the form** at `/tickets/new`. You'll see four sections: *Requester*, *Issue details*, *Description*, and *Attachments*.

2. **Requester** *(agents only)*
   - Start typing a name or work email. The directory dropdown filters as you type (debounced).
   - Click the matching person. Their directorate, division, and duty station load read-only beneath the picker so you know who you're filing for.
   - Requesters skip this step — you are the requester by default. If you want to log on behalf of someone else, expand the **"Log this on behalf of someone else"** toggle and pick them.

3. **Category** *(required)*
   - Pick the category that best matches the issue (Email, Computer troubleshooting, Network, Access requests, Printing, etc.).
   - Categories are configured by admins under **Settings → Categories**; SLA targets are tied to category.

4. **Priority** *(agents only — requesters cannot set priority)*
   - `low` / `medium` / `high` / `critical`.
   - Requester-submitted tickets default to **medium**; an agent reviews and adjusts after triage.

5. **Description** *(strongly recommended)*
   - The description field is a **rich text editor** (Quill). You can paste screenshots from your clipboard, format bullet lists, add links, and use code blocks.
   - There is no separate "subject" field — the system writes the subject for you using your category + name + the first line of your description (so descriptions like _"Outlook won't open since this morning"_ become useful subjects automatically).
   - You can leave it empty in a pinch (e.g. when phoning in a ticket), but agents will likely contact you for more detail.

6. **Attachments** *(optional)*
   - Drag files into the upload zone or click to pick. One file at a time, up to **10 MB**.
   - Allowed types: **JPG, JPEG, PNG, GIF, WEBP, PDF, DOC, DOCX**.
   - Files are uploaded the moment you drop them; you'll see a list of attached items below the editor. You can remove a file before saving by clicking the trash icon on the row.

7. **Submit**
   - Click **"Create ticket"**. The page shows a confirmation toast and routes you to the new ticket's detail view.
   - The ticket gets a number like **`HD-2026-000123`** (`HD-{year}-{sequence}` — the counter resets every January 1).
   - The system picks an initial assignee automatically:
     - If you are an agent and filed the ticket, you're assigned by default.
     - Otherwise the auto-router picks an available agent from your duty station + the routing rules under **Settings → Agents** (and from the optional AI assistant when `ai_agent_assignment_enabled` is on).

### What happens next

- An entry is written to the ticket's **audit history** (always visible in the timeline).
- If the assignee has notifications enabled (mail / Teams), they're alerted immediately.
- If the AI scanner is enabled, the new ticket is queued for tagging/triage signals.
- You'll receive an **email** with the ticket number and a tracking link.

### Tips

- **Filing for a colleague?** Use the requester picker — don't impersonate (sign-in is single sign-on, so tickets are always traceable to you in the history log).
- **Multiple attachments?** Upload them one at a time; the form accepts as many as the ticket needs.
- **Need to add information later?** Open the ticket and use the **Comment** box — every reply (public or internal) is timestamped and attributed.

---

## Tracking your tickets (requesters)

Click **My tickets** in the top bar (or visit `/tickets`).

You'll see:

- Your full list, newest first, with status, priority, category, assignee, and created date.
- A search box that filters by ticket number, subject, and category.
- Status filters (`open`, `pending`, `in progress`, `awaiting confirm`, `resolved`, `closed`).

Open any ticket to see:

- The full description, attachments, comments, and resolution.
- A live **timeline** of every event (created, assigned, status change, comment, resolution submitted, resolution confirmed, reassignment).
- A **comment box** — your replies are sent to the assignee and the watchers.

You cannot change priority, category, status, or the assignee yourself — those belong to the agent.

---

## Confirming a resolution

If your administrator has enabled **resolution confirmation** (default), this is the flow:

1. The agent submits a resolution. Your ticket moves to **`awaiting_requester_confirmation`** and you receive an email with a one-time confirmation link.
2. Open the link (the page is at `/tickets/confirm-resolution?token=…` and is publicly reachable from outside the helpdesk).
3. Review the resolution summary.
4. Click **"Yes, this is resolved"** — the ticket moves to **`resolved`** and the agent is notified.
5. If the issue is **not** resolved, click **"Re-open"** and add a comment explaining why. The ticket goes back to `open`.

If the toggle is off, agents move tickets straight to `resolved` and you only see a notification.

---

## Agent desk (agents & supervisors)

The **Agent desk** at `/desk/agent` is the daily workspace for everyone with a staff role.

What you see:

- A greeting + the current time, with a pulse on whether you're "in service hours".
- **KPI tiles**: *Pending*, *Due today*, *Overdue*, *Awaiting confirmation*, *High-priority pending*, *New today*, *Resolved this week*, *Closed*.
- **Status bar chart** showing your queue by status.
- **Priority bar chart** showing your queue by urgency.
- A live **recent tickets** table (the 25 most recent assigned to you) with quick filters (status, priority, due-soon).
- **Action buttons** per ticket: *Open* (full detail view), and *Reassign* (only if you have the permission — see next section).

The data refreshes automatically when you switch tabs and on each visit; for a longer-running session click any KPI tile to re-pull.

---

## Reassigning a ticket (permission required)

Only agents with `can_reassign_tickets = true` (admins always qualify) see the **Reassign** button on a ticket.

### Where it appears

- Inline on each row of the **Agent desk** recent-tickets table (only on rows whose status is `open`, `pending`, or `in_progress`).
- On the ticket detail page header.

### Steps

1. Click **Reassign**.
2. A modal opens with the **list of eligible agents** for the ticket's category (loaded via `GET /tickets/{id}/eligible-agents`). Each row shows the agent's name and current open ticket count, so you can prefer a less-busy colleague.
3. Pick the new assignee.
4. Enter a **reason** (minimum 10 characters — required). The reason is logged so the audit trail is clear ("Out of office", "Specialist needed", etc.).
5. Click **Confirm reassign**.

What happens:

- The ticket's `assigned_user_id` is updated.
- A row is added to the ticket history (`reassigned` event) with the reason and the original assignee.
- An **internal comment** with the same reason is recorded so the new agent has context.
- The new assignee receives a notification.

Reassignment is **only** allowed on `open`, `pending`, or `in_progress` tickets — the API rejects attempts on `awaiting_requester_confirmation`, `resolved`, or `closed` tickets with a clear 422 error.

---

## Knowledge base

### Browsing (everyone)

The home page lists FAQs grouped by category with a global search box. Click any question to expand the full answer. Articles are reordered by the `sort_order` field and only **active** articles appear.

### Managing (admins + `can_manage_kb` agents)

If you have permission, **"Knowledge base"** appears in the top navigation. Open it at `/knowledge-base/manage` to:

- **Add** an article: pick category, type a question, write the answer (rich text), set sort order, mark active.
- **Edit** an article inline.
- **Reorder** within a category by adjusting sort order.
- **Deactivate** instead of deleting — deactivated articles disappear from the public list but stay in the database for audit.

Use the knowledge base to deflect repeat tickets. The home-page search runs over titles and answers, so phrase answers the way users would search ("how do I reset my password", not "AD password reset procedure").

---

## Reports

`/reports` shows two tabs:

- **My tickets** — your own activity:
  - As a *requester*: tickets you've filed, with totals (pending/resolved/total received).
  - As an *agent*: your queue and resolution history.
- **Admin summary** *(admins only)*: org-wide counts (total / open / awaiting confirm / resolved / closed) and the 30 most recent tickets.

The **Export** button on each tab streams an Excel workbook (`maatwebsite/excel`) with the matching scope.

---

## TV / lobby dashboard

A public, full-screen dashboard for office TVs lives at **`/staff/helpdesk/screen`** (or `http://localhost:5174/screen` in development). It does **not** require authentication and exposes only aggregate metrics — never ticket subjects, descriptions, or requester identities.

What it shows:

- **Live clock + status pip** ("Live" / "Reconnecting").
- **KPI tiles**: Active tickets, Unassigned, Awaiting confirm, SLA breached, New today, Resolved today.
- **SLA gauges**: % first-response within target, % resolution within target (rolling 7 days).
- **Traffic & wait times**: average first-response, longest open ticket (with number + priority, but no subject).
- **Priority matrix**: count by Urgent / High / Medium / Low.
- **Agent workload**: top 8 agents by current open load.
- **Open by category**: top 8 categories by open count.
- **30-day trend**: created vs resolved per day.

The page polls every 15 seconds and tolerates short network blips (it shows "Reconnecting" rather than blanking out).

Point a TV browser at the screen URL once and leave it — no sign-in required.

---

## Admin settings (administrators)

The **Settings** area (`/settings`) is gated to `role = admin`. It contains seven panels:

- **General** — branding colours, default agent divisions, division-staff picker for promoting agents, and the **"Require resolution confirmation"** toggle.
- **AI models & provider** — choose OpenAI / Gemini / a custom provider, the model name, endpoint, encrypted API key, and toggles for *AI active* and *AI helps pick the assignee*.
- **Agents & category routing** — the agent roster. For each agent, configure which categories they handle and toggle `can_manage_kb` / `can_reassign_tickets`. Embedded picker lets you add new agents from any division.
- **Issue categories** — CRUD on the categories shown to requesters. Categories used by existing tickets cannot be deleted (the API responds with 409).
- **Jobs (SLA rules + directory sync)** — define response and resolution targets in minutes per category, plus a **"Sync now"** card that warms the Staff Share API cache.
- **WhatsApp & Teams** — enable/disable each channel and store the credentials. The page shows the webhook base URL to paste into Meta / Azure.
- **Audit & ISO logging** — paginated read-only viewer over `helpdesk_audit_logs` and a status indicator for the `iso_json` channel (JSON Lines for ISO/IEC 27001 / 27014 evidence).

Every settings save writes an entry to the audit log with the actor, IP, user-agent, and a JSON diff.

---

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| `helpdesk_error=sso` on the Staff portal | Your account doesn't have permission 85, 92, or 93. Ask an admin. |
| "Could not load staff from the directory" when filing a ticket | The Staff Share API credentials are stale. An admin should open **Settings → Jobs** and click **Sync now** (`POST /admin/reference-sync`). |
| Attachment upload fails | Check the file is under 10 MB and is a supported MIME (`jpg/jpeg/png/gif/webp/pdf/doc/docx`). Network firewalls also block some MIMEs — try a PDF. |
| You can't see the **Reassign** button | Either the ticket isn't in `open` / `pending` / `in_progress`, or you don't have `can_reassign_tickets`. Admins set this on **Settings → Agents**. |
| Resolution confirmation email never arrives | Check **Settings → Logging** for outbound mail failures and confirm `MAIL_*` is configured. Confirmation tokens stay valid until the ticket is reopened. |
| The TV screen says "Reconnecting" indefinitely | The browser can't reach `/api/v1/public/screen`. Confirm Apache is up and `throttle:120,1` isn't being hit by another consumer. |

---

For technical deep-dives (schema, endpoints, extension points) see the [Helpdesk Developer Guide](./DEVELOPER_GUIDE.md).
