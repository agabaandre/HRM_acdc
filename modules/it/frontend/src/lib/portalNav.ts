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
  '/settings/financial-year': 'fa-solid fa-calendar-days',
  '/settings/azure-sap-storage': 'fa-brands fa-microsoft',
}

export function pageIconForPath(path: string): string {
  const keys = Object.keys(PORTAL_PAGE_ICONS).sort((a, b) => b.length - a.length)
  for (const key of keys) {
    if (key === '/' && (path === '/' || path === '')) return PORTAL_PAGE_ICONS[key]
    if (key !== '/' && path.startsWith(key)) return PORTAL_PAGE_ICONS[key]
  }
  return 'fa-solid fa-file'
}

/** IT module primary navigation. */
export const PORTAL_NAV_ITEMS: PortalNavItem[] = [
  {
    label: 'Licenses',
    to: '/licenses',
    match: ['/licenses'],
    group: 'primary',
    icon: 'fa-solid fa-key',
  },
  {
    label: 'ITSM',
    to: '/itsm',
    match: ['/itsm'],
    group: 'primary',
    icon: 'fa-solid fa-laptop',
  },
  {
    label: 'Information Systems',
    to: '/information-systems',
    match: ['/information-systems'],
    group: 'primary',
    icon: 'fa-solid fa-server',
  },
  {
    label: 'Servers',
    to: '/servers',
    match: ['/servers'],
    group: 'primary',
    icon: 'fa-solid fa-cloud',
  },
  {
    label: 'Settings',
    to: '/settings/license-alerts',
    match: ['/settings/license-alerts'],
    group: 'primary',
    icon: 'fa-solid fa-gear',
  },
]

export function isNavItemActive(item: PortalNavItem, path: string): boolean {
  if (item.match?.length) {
    return item.match.some((prefix) => path === prefix || path.startsWith(prefix + '/'))
  }
  return path === item.to || path.startsWith(item.to + '/')
}
