export interface PortalNavItem {
  label: string
  to: string
  permission?: number | string
  match?: string[]
  /** primary = always in bar; more = overflow dropdown */
  group?: 'primary' | 'more'
  /** Font Awesome 6 class (CI3 nav equivalents) */
  icon?: string
}

/** Page heading icons (Font Awesome) keyed by route path prefix */
export const PORTAL_PAGE_ICONS: Record<string, string> = {
  '/': 'fa-solid fa-table-cells',
  '/home': 'fa-solid fa-table-cells',
  '/dashboard': 'fa-solid fa-house',
  '/staff': 'fa-solid fa-user',
  '/leave': 'fa-solid fa-calendar-check',
  '/performance': 'fa-solid fa-chart-line',
  '/tasks': 'fa-solid fa-list-check',
  '/workplan': 'fa-solid fa-calendar-days',
  '/admanager': 'fa-solid fa-shield-halved',
  '/reports': 'fa-solid fa-chart-column',
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

export const PORTAL_NAV_ITEMS: PortalNavItem[] = [
  { label: 'Home', to: '/', match: ['/', '/home'], group: 'primary', icon: 'fa-solid fa-table-cells' },
  { label: 'Dashboard', to: '/dashboard', permission: 76, group: 'primary', icon: 'fa-solid fa-house' },
  { label: 'Staff', to: '/staff', permission: 72, match: ['/staff'], group: 'primary', icon: 'fa-solid fa-user' },
  { label: 'Leave', to: '/leave', match: ['/leave'], group: 'primary', icon: 'fa-solid fa-calendar-check' },
  { label: 'Performance', to: '/performance', permission: 74, match: ['/performance'], group: 'primary', icon: 'fa-solid fa-chart-line' },
  { label: 'Tasks', to: '/tasks', permission: 78, match: ['/tasks'], group: 'primary', icon: 'fa-solid fa-list-check' },
  { label: 'Workplan', to: '/workplan', permission: 79, match: ['/workplan'], group: 'primary', icon: 'fa-solid fa-calendar-days' },
  { label: 'AD Manager', to: '/admanager', permission: 77, match: ['/admanager'], group: 'primary', icon: 'fa-solid fa-shield-halved' },
  { label: 'Reports', to: '/reports', permission: 72, match: ['/reports'], group: 'more', icon: 'fa-solid fa-chart-column' },
  { label: 'Settings', to: '/settings', permission: 15, match: ['/settings'], group: 'more', icon: 'fa-solid fa-gear' },
  { label: 'Permissions', to: '/permissions', permission: 17, group: 'more', icon: 'fa-solid fa-lock' },
  { label: 'Users', to: '/auth/users', permission: 17, match: ['/auth/users'], group: 'more', icon: 'fa-solid fa-users' },
  { label: 'OAuth clients', to: '/auth/oauth-clients', permission: 17, match: ['/auth/oauth-clients'], group: 'more', icon: 'fa-solid fa-key' },
  { label: 'Audit logs', to: '/auth/audit-logs', permission: 17, match: ['/auth/audit-logs'], group: 'more', icon: 'fa-solid fa-clock-rotate-left' },
]

export function isNavItemActive(item: PortalNavItem, path: string): boolean {
  const paths = item.match ?? [item.to]
  return paths.some((p) => (p === '/' ? path === '/' : path.startsWith(p)))
}
