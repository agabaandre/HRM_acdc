# Portal branding settings (CBP-wide)

**Date:** 2026-09-16  
**Status:** Approved

## Goal

Staff Portal `/staff/settings/branding` is the single source of truth for org branding used by Staff Portal, APM, Risk Register, Helpdesk, and Finance.

## Fields

| Key | Default |
|-----|---------|
| `company_name` | Africa CDC |
| `system_logo` | `/assets/images/AU_CDC_Logo-800.png` |
| `footer_copyright` | `Copyright © Africa CDC {year}. All rights reserved.` |
| `print_footer` | HQ address HTML (current mPDF left column) |
| `company_email` | `registry@africacdc.org` |
| `company_phone` | `` |
| `company_website` | `https://africacdc.org` |
| `company_address` | Africa CDC Headquarters address (plain text) |

## Storage & APIs

- Table `portal_branding_settings` (`setting_key`, `setting_value`)
- Sanctum: `GET/PUT /api/v1/settings/branding`, `POST .../logo`
- Share: `GET /share/branding/{token?}` (Basic Auth)

## Consumers

Chrome logo + copyright footer; mPDF header logo + print footer / address / email across modules via Share (or local DB read for Staff Portal).
