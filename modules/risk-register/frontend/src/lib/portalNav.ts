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
  '/': 'fa-solid fa-shield-halved',
  '/risks': 'fa-solid fa-shield-halved',
  '/dashboard': 'fa-solid fa-chart-pie',
  '/approvals': 'fa-solid fa-check-double',
  '/workflows': 'fa-solid fa-diagram-project',
  '/import': 'fa-solid fa-file-import',
  '/reference': 'fa-solid fa-book',
  '/risk-settings': 'fa-solid fa-gear',
}

export function pageIconForPath(path: string): string {
  const keys = Object.keys(PORTAL_PAGE_ICONS).sort((a, b) => b.length - a.length)
  for (const key of keys) {
    if (key === '/' && (path === '/' || path === '')) return PORTAL_PAGE_ICONS[key]
    if (key !== '/' && path.startsWith(key)) return PORTAL_PAGE_ICONS[key]
  }
  return 'fa-solid fa-file'
}

/** Risk Register only — Staff Portal clone modules removed from nav. */
export const PORTAL_NAV_ITEMS: PortalNavItem[] = [
  {
    label: 'Risks',
    i18nKey: 'risks',
    to: '/risks',
    match: ['/risks'],
    group: 'primary',
    icon: 'fa-solid fa-shield-halved',
    module: 'risks',
  },
  {
    label: 'Dashboard',
    i18nKey: 'dashboard',
    to: '/dashboard',
    match: ['/dashboard'],
    group: 'primary',
    icon: 'fa-solid fa-chart-pie',
    module: 'dashboard',
  },
  {
    label: 'Approvals',
    i18nKey: 'approvals',
    to: '/approvals',
    match: ['/approvals'],
    group: 'primary',
    icon: 'fa-solid fa-check-double',
    module: 'risks',
  },
  {
    label: 'Workflows',
    i18nKey: 'workflows',
    to: '/workflows',
    match: ['/workflows'],
    group: 'primary',
    icon: 'fa-solid fa-diagram-project',
    module: 'risks',
  },
  {
    label: 'Import',
    i18nKey: 'import',
    to: '/import',
    match: ['/import'],
    group: 'primary',
    icon: 'fa-solid fa-file-import',
    module: 'import',
  },
  {
    label: 'Reference',
    i18nKey: 'reference',
    to: '/reference',
    match: ['/reference'],
    group: 'primary',
    icon: 'fa-solid fa-book',
    module: 'reference',
  },
  {
    label: 'Settings',
    i18nKey: 'settings',
    to: '/risk-settings',
    match: ['/risk-settings'],
    group: 'primary',
    icon: 'fa-solid fa-gear',
    module: 'settings',
  },
]

export function isNavItemActive(item: PortalNavItem, path: string): boolean {
  if (item.match?.length) {
    return item.match.some((prefix) => path === prefix || path.startsWith(prefix + '/'))
  }
  return path === item.to || path.startsWith(item.to + '/')
}
