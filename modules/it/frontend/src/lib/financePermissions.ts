/** Staff Portal permission codes for Finance (seeded in portal). */
export const FINANCE_PERMS = {
  MODULE: 92,
  SETTINGS: 93,
  MANAGE_FUNDING_PORTFOLIO: 124,
} as const

/** Any finance-related permission that unlocks dashboards / primary finance views. */
export const FINANCE_DASHBOARD_PERMS = [
  FINANCE_PERMS.MODULE,
  FINANCE_PERMS.SETTINGS,
  FINANCE_PERMS.MANAGE_FUNDING_PORTFOLIO,
] as const
