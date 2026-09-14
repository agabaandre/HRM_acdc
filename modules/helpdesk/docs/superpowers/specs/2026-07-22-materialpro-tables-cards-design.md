# MaterialPro tables + cards (Helpdesk)

## Goal
Lift Helpdesk tables and cards to match Email Server Admin (MaterialPro / Vuetify) while keeping existing layout chrome and page structure.

## Approach
Theme defaults + shared CSS. No sidebar/topbar redesign. No per-view markup rewrite unless a class is missing.

## Visual tokens
- Page background: `#eef5f9` (light)
- Cards: white surface, soft MaterialPro shadow (`0 12px 24px -4px` / `0 0 2px`), radius ~10–12px, no hard outline
- Tables: comfortable density, hover rows, semibold headers (not micro uppercase 800-weight)
- Keep Africa CDC primary `#0d7a3a` / secondary `#c9a227`

## Scope
- `hd-data-table-card` / `hd-data-table` (tickets, agent desk, reports, audit)
- Settings list cards (`cat-table-card`, `matrix-table-card`, agent/cand/picker tables)
- Tool HTML `.data-table` + `.table-wrap.cbp-card` (licenses, assets, software)
- Shared `.cbp-card` surface treatment

## Out of scope
- Nav/topbar shell
- Login/auth pages
- Brand color change
