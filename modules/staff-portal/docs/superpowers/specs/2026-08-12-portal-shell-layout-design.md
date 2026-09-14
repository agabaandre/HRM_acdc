# Staff Portal Shell Layout Polish

**Date:** 2026-08-12  
**Status:** Approved (Approach 1)  
**Skills:** AAS `frontend-design`, `ui-ux-pro-max`; Superpowers brainstorming

## Goal

Improve Staff Portal application chrome (A–E): nav density, content spacing, sticky offsets, and interaction polish — without leaving CBP brand or rewriting Leave.

## Approach

Staff-portal-owned overlay: `portal-shell.css` + nav IA tweak on top of shared `@cbp/helpdesk-lib` CBP layout CSS.

## Scope

| In | Out |
|----|-----|
| Primary nav: core links + **More** overflow | Leave page markup |
| Sticky chrome height CSS vars | Attendance |
| Content gutters / page-chrome rhythm | Performance Livewire forms |
| Focus/hover/active polish, touch targets | New sidebar IA |

## Nav IA

**Primary (always):** Home, Dashboard, Staff, Leave, Performance, Tasks, Workplan, AD Manager  

**More dropdown (permission-filtered):** Reports, Settings, Permissions, Users, Audit logs  

## Tokens

- `--portal-chrome-top`: topbar (60px) + primary nav (60px) = 120px (mobile drawer does not change offset)
- Content `margin-top` / min-heights keyed off that token
- Horizontal padding: consistent `1rem` → `1.5rem` at ≥900px
- Focus rings visible on nav links and More toggle
- `prefers-reduced-motion`: shorten/disable nav drawer transition

## Success

- Mid-width screens no longer wrap primary links into two rows
- Content never sits under fixed chrome
- More items reachable; active state when any More child is current
- Leave screens unchanged

## Differentiation

Avoid generic SaaS chrome redesign: keep CBP green topbar + slate nav; improve density and a11y only.
