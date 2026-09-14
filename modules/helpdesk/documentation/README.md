# Helpdesk documentation

The Africa CDC Service Desk (Laravel 11 JSON API + Vue 3.5 SPA) is part of the [Central Business Platform](../../README.md). This folder is the home of every helpdesk-specific doc — start with the guide that matches your role.

## Pick your starting point

| You are… | Read this |
|---|---|
| **A staff member** filing or tracking tickets | [USER_GUIDE.md](./USER_GUIDE.md) — ticket creation, comments, reopen, knowledge base |
| **An agent or supervisor** working the queue | [USER_GUIDE.md → Agent desk](./USER_GUIDE.md#agent-desk-agents--supervisors) |
| **An administrator** configuring AI, WhatsApp, mail, SLA | **[ADMIN_GUIDE.md](./ADMIN_GUIDE.md)** ← full configuration reference |
| **A developer** extending or debugging the module | [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) |
| **A system administrator** deploying the module | [SYSTEMD.md](./SYSTEMD.md) · [DEVELOPER_GUIDE.md → Operations](./DEVELOPER_GUIDE.md#operations--runbooks) · [INTEGRATION.md](./INTEGRATION.md) |

## Screenshots

UI screenshots for guides live in [`screenshots/`](./screenshots/). Regenerate automatically:

```bash
cd helpdesk
npm install
npx playwright install chromium
npm run docs:screenshots
# Optional — all authenticated pages:
HELPDESK_DOC_TOKEN='<staff-jwt>' HELPDESK_DOC_BASE_URL=https://cbp.africacdc.org/staff/helpdesk npm run docs:screenshots
```

## All documents in this folder

| Document | Audience | Purpose |
|----------|----------|---------|
| [USER_GUIDE.md](./USER_GUIDE.md) | Requesters, agents, admins | End-user walkthroughs with screenshots: SSO, tickets, **reopen via comment**, agent desk KPIs, support groups, KB, reports, TV screen |
| [ADMIN_GUIDE.md](./ADMIN_GUIDE.md) | Administrators | **AI**, **WhatsApp**, **Teams**, General/SLA/agents, env vars, mail branding, security checklist |
| [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) | Engineers, SRE | Stack, Apache routing, SSO, API reference, services, frontend architecture, runbooks |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Engineers, architects | API-first layout, schema groupings, integrations |
| [INTEGRATION.md](./INTEGRATION.md) | Engineers, integrators | Staff SSO, HMAC bridge, Staff Share API, webhooks, audit logging |
| [SYSTEMD.md](./SYSTEMD.md) | SRE / Linux admins | systemd units for queue worker and scheduler |
| [openapi.yaml](./openapi.yaml) | API consumers | OpenAPI 3 outline |
| [screenshots/](./screenshots/) | All | Auto-captured UI images referenced by guides |

## Feature index (recent)

| Feature | User doc | Admin doc |
|---------|----------|-----------|
| Business units + AI categorization on create | [USER_GUIDE → Creating a ticket](./USER_GUIDE.md#creating-a-ticket) | [ADMIN_GUIDE → Categories & BUs](./ADMIN_GUIDE.md#issue-categories--business-units) |
| Inbound Exchange mailbox intake | [USER_GUIDE → Email to the service desk](./USER_GUIDE.md#email-to-the-service-desk) | [ADMIN_GUIDE → Inbound email intake](./ADMIN_GUIDE.md#inbound-email-intake) |
| Link IT asset on resolve (serial search) | [USER_GUIDE → Resolving](./USER_GUIDE.md#resolving-a-ticket-agents) | [ADMIN_GUIDE → IT Assets](./ADMIN_GUIDE.md#it-assets-settings) |
| IT Assets / Licenses / Software requests tools | [USER_GUIDE → Tools](./USER_GUIDE.md#tools-it-assets--software) | [ADMIN_GUIDE → IT Assets](./ADMIN_GUIDE.md#it-assets-settings) |
| Requester reopen via comment + agent email | [USER_GUIDE](./USER_GUIDE.md#comments-reopen--agent-email) | [ADMIN_GUIDE → General](./ADMIN_GUIDE.md#general-settings) |
| Support groups & group routing | [USER_GUIDE](./USER_GUIDE.md#support-groups) | [ADMIN_GUIDE → Agents](./ADMIN_GUIDE.md#agents--support-groups) |
| Clickable agent desk KPI filters | [USER_GUIDE](./USER_GUIDE.md#agent-desk-agents--supervisors) | — |
| Branded transactional email (Africa CDC Service Desk) | [USER_GUIDE](./USER_GUIDE.md#comments-reopen--agent-email) | [ADMIN_GUIDE → Mail](./ADMIN_GUIDE.md#mail--branded-notifications) |
| Signed attachment URLs | — | [ADMIN_GUIDE → Security](./ADMIN_GUIDE.md#security-checklist) |
| AI provider & agent assignment | — | [ADMIN_GUIDE → AI](./ADMIN_GUIDE.md#ai-models--provider) |
| WhatsApp / Teams webhooks | — | [ADMIN_GUIDE → Integrations](./ADMIN_GUIDE.md#whatsapp--teams-integrations) |
| FAQ ingest from APM | — | [ADMIN_GUIDE → Jobs](./ADMIN_GUIDE.md#jobs-sla--directory-sync) |

## Related reading

- [Helpdesk top-level README](../README.md) — quick start, Apache routing, production deploy
- [Helpdesk backend README](../backend/README.md) — Laravel-specific notes
- [APM helpdesk integration notes](../../apm/documentation/HELPDESK_INTEGRATION.md)
- [CBP central documentation hub](../../documentation/README.md)

## Source of truth

- Requirements specification: `../helpdesk-module.text` (URS)
- Delivery checklist: `../cursor.txt`
