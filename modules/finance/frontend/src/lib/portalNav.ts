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
  '/dashboard': 'fa-solid fa-chart-pie',
  '/portfolio/entry': 'fa-solid fa-table',
  '/sap-import': 'fa-solid fa-file-import',
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
    label: 'Funding Portfolio',
    i18nKey: 'finance.portfolio',
    to: '/dashboard',
    match: ['/dashboard', '/portfolio'],
    group: 'primary',
    icon: 'fa-solid fa-chart-pie',
    module: 'dashboard',
  },
  {
    label: 'Data entry',
    i18nKey: 'finance.entry',
    to: '/portfolio/entry',
    match: ['/portfolio/entry'],
    group: 'primary',
    icon: 'fa-solid fa-table',
    module: 'portfolio',
  },
  {
    label: 'SAP import',
    i18nKey: 'finance.sap',
    to: '/sap-import',
    match: ['/sap-import'],
    group: 'primary',
    icon: 'fa-solid fa-file-import',
    module: 'sap',
  },
]

export function isNavItemActive(item: PortalNavItem, path: string): boolean {
  if (item.match?.length) {
    return item.match.some((prefix) => path === prefix || path.startsWith(prefix + '/'))
  }
  return path === item.to || path.startsWith(item.to + '/')
}
