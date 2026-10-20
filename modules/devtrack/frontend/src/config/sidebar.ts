export type SidebarItem = {
  header?: string
  title?: string
  icon?: string
  to?: { name: string } | string
  adminOnly?: boolean
}

export const sidebarItems: SidebarItem[] = [
  { header: 'Delivery' },
  { title: 'Dashboard', icon: 'mdi-view-dashboard-outline', to: { name: 'dashboard' } },
  { title: 'Requests', icon: 'mdi-inbox-arrow-down-outline', to: { name: 'requests' } },
  { title: 'Projects', icon: 'mdi-folder-table-outline', to: { name: 'projects' } },
  { title: 'Archived Projects', icon: 'mdi-archive-outline', to: { name: 'archived-projects' }, adminOnly: true },
  { title: 'Tasks', icon: 'mdi-account-arrow-right-outline', to: { name: 'work' } },
  { title: 'My tasks', icon: 'mdi-checkbox-marked-outline', to: { name: 'my-work' } },
  { title: 'Sprints', icon: 'mdi-calendar-week-outline', to: { name: 'sprints' } },
  { title: 'Reports', icon: 'mdi-file-chart-outline', to: { name: 'reports' } },
  { title: 'Workplan', icon: 'mdi-target', to: { name: 'indicators' } },
  { header: 'Administration' },
  { title: 'Users', icon: 'mdi-account-group-outline', to: { name: 'users' }, adminOnly: true },
  { title: 'Staff', icon: 'mdi-badge-account-outline', to: { name: 'staff' }, adminOnly: true },
  { title: 'Audit logs', icon: 'mdi-clipboard-text-clock-outline', to: { name: 'audit-logs' }, adminOnly: true },
  { title: 'Blocked access', icon: 'mdi-cancel', to: { name: 'blocked-ips' }, adminOnly: true },
  { title: 'Branding', icon: 'mdi-palette-outline', to: { name: 'branding' }, adminOnly: true },
  { title: 'Backup / Restore', icon: 'mdi-database-export-outline', to: { name: 'backup' }, adminOnly: true },
  { title: 'Security', icon: 'mdi-shield-key-outline', to: { name: 'security' } },
  { header: 'Notifications', adminOnly: true },
  { title: 'Email providers', icon: 'mdi-email-multiple-outline', to: { name: 'providers' }, adminOnly: true },
  { title: 'Integrations', icon: 'mdi-api', to: { name: 'integrations' }, adminOnly: true },
  { title: 'Staff Share', icon: 'mdi-badge-account-horizontal-outline', to: { name: 'staff-share' }, adminOnly: true },
  { title: 'Send email', icon: 'mdi-email-edit-outline', to: { name: 'send-mail' }, adminOnly: true },
  { title: 'Email logs', icon: 'mdi-history', to: { name: 'logs' }, adminOnly: true },
]
