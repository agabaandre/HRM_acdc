# Portal Shell Layout Polish Implementation Plan

> **For agentic workers:** Implement task-by-task. Steps use checkbox syntax.

**Goal:** Ship Approach 1 shell polish (nav More overflow + sticky/content CSS overlay).

**Architecture:** Overlay CSS in staff-portal; extend `portalNav.ts` with primary vs more groups; update `PortalPrimaryNav.vue` with More dropdown mirroring Helpdesk settings-dd patterns; tighten `PortalPageChrome` spacing only.

**Tech Stack:** Vue 3, existing CBP CSS classes from helpdesk-lib.

## Global Constraints

- Do not edit Leave pages
- Do not change shared helpdesk-lib CSS source; override in staff-portal only
- Keep CBP colors/tokens

---

### Task 1: Nav model

- [ ] Extend `portalNav.ts` with `group: 'primary' | 'more'` (or split arrays)
- [ ] Include `/auth/users` and `/auth/audit-logs` in More (perm 17)

### Task 2: PortalPrimaryNav More dropdown

- [ ] Render primary links; More button + panel for overflow items
- [ ] Active when any more-item matches; close on route/doc click
- [ ] Mobile: More items list inside open drawer

### Task 3: portal-shell.css

- [ ] Create `src/styles/portal-shell.css` with chrome tokens, content padding, focus, reduced-motion
- [ ] Import from `main.ts` after cbp-finance-layout.css

### Task 4: Page chrome

- [ ] Slightly tighten `PortalPageChrome` title/tabs spacing (Leave uses own chrome)

### Task 5: Build

- [ ] `npm run build` in frontend
