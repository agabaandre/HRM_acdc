export interface PortalNavItem {
  label: string
  to: string
  permission?: number | string
  /** Show if the user has any of these permissions (OR). */
  anyPermission?: Array<number | string>
  match?: string[]
  /** primary = always in bar; more = overflow dropdown */
  group?: 'primary' | 'more'
  /** Font Awesome 6 class (CI3 nav equivalents) */
  icon?: string
  /** SPA module key from Settings → Portal modules */
  module?: string
  /** i18n key under the `nav` group, e.g. dashboard */
  i18nKey?: string
}

/** Page heading icons (Font Awesome) keyed by route path prefix */
export const PORTAL_PAGE_ICONS: Record<string, string> = {
  '/': 'fa-solid fa-table-cells',
  '/home': 'fa-solid fa-table-cells',
  '/dashboard': 'fa-solid fa-house',
  '/staff': 'fa-solid fa-user',
  '/leave': 'fa-solid fa-calendar-check',
  '/payroll': 'fa-solid fa-money-check-dollar',
  '/performance': 'fa-solid fa-chart-line',
  '/tasks': 'fa-solid fa-list-check',
  '/workplan': 'fa-solid fa-calendar-days',
  '/admanager': 'fa-solid fa-shield-halved',
  '/settings': 'fa-solid fa-gear',
  '/permissions': 'fa-solid fa-lock',
  '/auth/users': 'fa-solid fa-users',
  '/auth/oauth-clients': 'fa-solid fa-key',
  '/auth/audit-logs': 'fa-solid fa-clock-rotate-left',
}

export function pageIconForPath(path: string): string {
  const keys = Object.keys(PORTAL_PAGE_ICONS).sort((a, b) => b.length - a.length)
  for (const key of keys) {
    if (key === '/' && (path === '/' || path === '')) return PORTAL_PAGE_ICONS[key]
    if (key !== '/' && path.startsWith(key)) return PORTAL_PAGE_ICONS[key]
  }
  return 'fa-solid fa-file'
}

/** Staff module nav only — CBP Home is the logo / top-bar “CBP Modules” link, not a primary-nav item. */
export const PORTAL_NAV_ITEMS: PortalNavItem[] = [
  {
    label: 'Dashboard',
    i18nKey: 'dashboard',
    to: '/dashboard',
    permission: 76,
    group: 'primary',
    icon: 'fa-solid fa-house',
    module: 'dashboard',
  },
  {
    label: 'Staff',
    i18nKey: 'staff',
    to: '/staff',
    permission: 72,
    match: ['/staff'],
    group: 'primary',
    icon: 'fa-solid fa-user',
    module: 'staff',
  },
  {
    label: 'Leave',
    i18nKey: 'leave',
    to: '/leave',
    match: ['/leave'],
    group: 'primary',
    icon: 'fa-solid fa-calendar-check',
    anyPermission: [37, 73, 95, 96, 97, 98, 77],
    module: 'leave',
  },
  {
    label: 'Payroll',
    i18nKey: 'payroll',
    to: '/payroll',
    match: ['/payroll'],
    group: 'primary',
    icon: 'fa-solid fa-money-check-dollar',
    anyPermission: [110, 111, 112, 113, 114, 115, 116, 117],
    module: 'payroll',
  },
  {
    label: 'Performance',
    i18nKey: 'performance',
    to: '/performance',
    permission: 74,
    match: ['/performance'],
    group: 'primary',
    icon: 'fa-solid fa-chart-line',
    module: 'performance',
  },
  {
    label: 'Tasks',
    i18nKey: 'tasks',
    to: '/tasks/weekly',
    permission: 75,
    match: ['/tasks'],
    group: 'primary',
    icon: 'fa-solid fa-list-check',
    module: 'tasks',
  },
  {
    label: 'Workplan',
    i18nKey: 'workplan',
    to: '/workplan',
    permission: 79,
    match: ['/workplan'],
    group: 'primary',
    icon: 'fa-solid fa-calendar-days',
    module: 'workplan',
  },
  {
    label: 'AD Manager',
    i18nKey: 'ad_manager',
    to: '/admanager/expired',
    permission: 77,
    match: ['/admanager'],
    group: 'primary',
    icon: 'fa-solid fa-shield-halved',
    module: 'admanager',
  },
  {
    label: 'Settings',
    i18nKey: 'settings',
    to: '/settings',
    permission: 15,
    match: ['/settings'],
    group: 'more',
    icon: 'fa-solid fa-gear',
    module: 'settings',
  },
  { label: 'Permissions', i18nKey: 'permissions', to: '/permissions', permission: 17, group: 'more', icon: 'fa-solid fa-lock' },
  { label: 'Users', i18nKey: 'users', to: '/auth/users', permission: 17, match: ['/auth/users'], group: 'more', icon: 'fa-solid fa-users' },
  { label: 'OAuth clients', i18nKey: 'oauth_clients', to: '/auth/oauth-clients', permission: 17, match: ['/auth/oauth-clients'], group: 'more', icon: 'fa-solid fa-key' },
  { label: 'Audit logs', i18nKey: 'audit_logs', to: '/auth/audit-logs', permission: 17, match: ['/auth/audit-logs'], group: 'more', icon: 'fa-solid fa-clock-rotate-left' },
]

export function isNavItemActive(item: PortalNavItem, path: string): boolean {
  const paths = item.match ?? [item.to]
  return paths.some((p) => (p === '/' ? path === '/' : path.startsWith(p)))
}
