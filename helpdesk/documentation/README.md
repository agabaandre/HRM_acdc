# Helpdesk documentation

The Africa CDC IT Service Desk (Laravel 11 JSON API + Vue 3.5 SPA) is part of the [Central Business Platform](../../README.md). This folder is the home of every helpdesk-specific doc — start with the guide that matches your role.

## Pick your starting point

| You are… | Read this |
|---|---|
| **A staff member** filing or tracking tickets | [USER_GUIDE.md](./USER_GUIDE.md) — every audience, with a step-by-step ticket-creation walkthrough. |
| **An agent or supervisor** working the queue | [USER_GUIDE.md → Agent desk](./USER_GUIDE.md#agent-desk-agents--supervisors) |
| **An administrator** configuring the service | [USER_GUIDE.md → Admin settings](./USER_GUIDE.md#admin-settings-administrators) |
| **A developer** extending or debugging the module | [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) |
| **A system administrator** deploying the module | [SYSTEMD.md](./SYSTEMD.md) · [DEVELOPER_GUIDE.md → Operations](./DEVELOPER_GUIDE.md#operations--runbooks) · [INTEGRATION.md](./INTEGRATION.md) |

## All documents in this folder

| Document | Audience | Purpose |
|----------|----------|---------|
| [USER_GUIDE.md](./USER_GUIDE.md) | Requesters, agents, admins | End-user walkthroughs: getting in, **ticket creation**, tracking, resolving, knowledge base, agent desk, reassignment, reports, TV dashboard, admin settings, troubleshooting. |
| [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) | Engineers, SRE | Stack & layout, local dev, Apache routing, SSO, authorization, schema reference, REST API reference, ticket lifecycle internals, services & jobs, frontend architecture, public TV dashboard, audit logging, extension points, runbooks. |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Engineers, architects | One-page overview of the API-first layout, schema groupings, and integrations. |
| [INTEGRATION.md](./INTEGRATION.md) | Engineers, integrators | Staff portal SSO (`?token=`), HMAC server bridge, Staff Share API client, home-dashboard card, WhatsApp / Teams webhooks, audit & ISO logging. |
| [SYSTEMD.md](./SYSTEMD.md) | SRE / Linux admins | systemd units: start on boot, restart queue worker on failure, scheduler + health timers. |
| [openapi.yaml](./openapi.yaml) | API consumers | OpenAPI 3 outline — expand as endpoints stabilise. |

## Related reading

- [Helpdesk top-level README](../README.md) — quick start, Apache routing summary, dev with hot-reload.
- [Helpdesk backend README](../backend/README.md) — Laravel-specific notes.
- [APM helpdesk integration notes](../../apm/documentation/HELPDESK_INTEGRATION.md) — APM ↔ Helpdesk shared concerns.
- [CBP central documentation hub](../../documentation/README.md) — platform-wide context.

## Source of truth

- Requirements specification: `../helpdesk-module.text` (URS)
- Delivery checklist: `../cursor.txt`
