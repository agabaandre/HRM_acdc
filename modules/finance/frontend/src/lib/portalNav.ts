export interface PortalNavItem {
  label: string
  to: string
  permission?: number | string
  anyPermission?: Array<number | string>
  match?: string[]
  group?: 'primary' | 'more'
  icon?: string
  module?: string
  i18nKey?: string
}

export const PORTAL_PAGE_ICONS: Record<string, string> = {
  '/': 'fa-solid fa-chart-pie',
  '/budget-execution': 'fa-solid fa-chart-column',
  '/dashboard': 'fa-solid fa-chart-pie',
  '/performance': 'fa-solid fa-gauge-high',
  '/portfolio/entry': 'fa-solid fa-table',
  '/sap-import': 'fa-solid fa-file-import',
  '/settings/email': 'fa-solid fa-envelope',
  '/settings/staff-api': 'fa-solid fa-key',
  '/settings/app-logs': 'fa-solid fa-file-lines',
  '/settings/apm-mapping': 'fa-solid fa-diagram-project',
  '/settings/apm-mapping/divisions': 'fa-solid fa-sitemap',
  '/settings/apm-mapping/funders': 'fa-solid fa-hand-holding-heart',
  '/settings/grants': 'fa-solid fa-hand-holding-dollar',
  '/settings/geography': 'fa-solid fa-earth-africa',
  '/settings/afef-categories': 'fa-solid fa-shield-heart',
}

export function pageIconForPath(path: string): string {
  const keys = Object.keys(PORTAL_PAGE_ICONS).sort((a, b) => b.length - a.length)
  for (const key of keys) {
    if (key === '/' && (path === '/' || path === '')) return PORTAL_PAGE_ICONS[key]
    if (key !== '/' && path.startsWith(key)) return PORTAL_PAGE_ICONS[key]
  }
  return 'fa-solid fa-file'
}

/** Finance Funding Portfolio primary navigation. */
export const PORTAL_NAV_ITEMS: PortalNavItem[] = [
  {
    label: 'Budgeted execution',
    i18nKey: 'finance.budget_execution',
    to: '/budget-execution',
    match: ['/budget-execution'],
    group: 'primary',
    icon: 'fa-solid fa-chart-column',
    module: 'dashboard',
    // Visible to all authenticated staff (division-scoped for employees).
  },
  {
    label: 'Funding Portfolio',
    i18nKey: 'finance.portfolio',
    to: '/dashboard',
    match: ['/dashboard'],
    group: 'primary',
    icon: 'fa-solid fa-chart-pie',
    module: 'dashboard',
    anyPermission: [92, 93, 124],
  },
  {
    label: 'Performance',
    i18nKey: 'finance.performance',
    to: '/performance',
    match: ['/performance'],
    group: 'primary',
    icon: 'fa-solid fa-gauge-high',
    module: 'dashboard',
    anyPermission: [92, 93, 124],
  },
  {
    label: 'Data entry',
    i18nKey: 'finance.entry',
    to: '/portfolio/entry',
    match: ['/portfolio/entry'],
    group: 'primary',
    icon: 'fa-solid fa-table',
    module: 'portfolio',
    anyPermission: [92, 93, 124],
  },
  {
    label: 'SAP import',
    i18nKey: 'finance.sap',
    to: '/sap-import',
    match: ['/sap-import'],
    group: 'primary',
    icon: 'fa-solid fa-file-import',
    module: 'sap',
    anyPermission: [92, 93, 124],
  },
  {
    label: 'Email settings',
    i18nKey: 'finance.email_settings',
    to: '/settings/email',
    match: ['/settings/email'],
    group: 'more',
    icon: 'fa-solid fa-envelope',
    module: 'settings',
  },
  {
    label: 'Staff API',
    i18nKey: 'finance.staff_api',
    to: '/settings/staff-api',
    match: ['/settings/staff-api'],
    group: 'more',
    icon: 'fa-solid fa-key',
    module: 'settings',
  },
  {
    label: 'App logs',
    i18nKey: 'finance.app_logs',
    to: '/settings/app-logs',
    match: ['/settings/app-logs'],
    group: 'more',
    icon: 'fa-solid fa-file-lines',
    module: 'settings',
  },
  {
    label: 'APM divisions',
    i18nKey: 'finance.apm_divisions',
    to: '/settings/apm-mapping/divisions',
    match: ['/settings/apm-mapping/divisions'],
    group: 'more',
    icon: 'fa-solid fa-sitemap',
    module: 'settings',
  },
  {
    label: 'APM funders',
    i18nKey: 'finance.apm_funders',
    to: '/settings/apm-mapping/funders',
    match: ['/settings/apm-mapping/funders'],
    group: 'more',
    icon: 'fa-solid fa-hand-holding-heart',
    module: 'settings',
  },
  {
    label: 'Grants',
    i18nKey: 'finance.grants',
    to: '/settings/grants',
    match: ['/settings/grants'],
    group: 'more',
    icon: 'fa-solid fa-hand-holding-dollar',
    module: 'settings',
  },
  {
    label: 'Geography',
    i18nKey: 'finance.geography',
    to: '/settings/geography',
    match: ['/settings/geography'],
    group: 'more',
    icon: 'fa-solid fa-earth-africa',
    module: 'settings',
  },
  {
    label: 'AfEF categories',
    i18nKey: 'finance.afef_categories',
    to: '/settings/afef-categories',
    match: ['/settings/afef-categories'],
    group: 'more',
    icon: 'fa-solid fa-shield-heart',
    module: 'settings',
  },
]

export function isNavItemActive(item: PortalNavItem, path: string): boolean {
  if (item.match?.length) {
    return item.match.some((prefix) => path === prefix || path.startsWith(prefix + '/'))
  }
  return path === item.to || path.startsWith(item.to + '/')
}
