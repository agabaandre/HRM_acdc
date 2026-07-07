export interface PortalNavItem {
  label: string
  to: string
  permission?: number | string
  match?: string[]
}

export const PORTAL_NAV_ITEMS: PortalNavItem[] = [
  { label: 'Home', to: '/', match: ['/', '/home'] },
  { label: 'Dashboard', to: '/dashboard', permission: 76 },
  { label: 'Staff', to: '/staff' },
  { label: 'Leave', to: '/leave' },
  { label: 'Performance', to: '/performance' },
  { label: 'Attendance', to: '/attendance' },
  { label: 'Tasks', to: '/tasks' },
  { label: 'Workplan', to: '/workplan' },
  { label: 'Settings', to: '/settings' },
  { label: 'Permissions', to: '/permissions' },
]
