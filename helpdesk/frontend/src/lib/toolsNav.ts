export type ToolsPermissionKey =
  | 'can_manage_it_assets'
  | 'can_manage_licenses'
  | 'can_submit_software_requests'
  | 'can_approve_software_requests'
  | 'can_manage_software_requests'
  | 'can_manage_information_systems'

export interface ToolsNavItem {
  path: string
  label: string
  icon: string
  permission?: ToolsPermissionKey
  /** Visible to any authenticated user when no permission set */
  publicToAuth?: boolean
}

export const TOOLS_NAV_DROPDOWN_ITEMS: ToolsNavItem[] = [
  {
    path: '/tools/it-assets',
    label: 'IT Assets',
    icon: 'bx bx-laptop',
    permission: 'can_manage_it_assets',
  },
  {
    path: '/tools/licenses',
    label: 'Licenses',
    icon: 'bx bx-key',
    permission: 'can_manage_licenses',
  },
  {
    path: '/tools/software-requests',
    label: 'Software requests',
    icon: 'bx bx-file-blank',
    permission: 'can_submit_software_requests',
    publicToAuth: true,
  },
  {
    path: '/tools/information-systems',
    label: 'Information Systems',
    icon: 'bx bx-server',
    permission: 'can_manage_information_systems',
  },
]

export function toolsNavIcon(path: string): string {
  return TOOLS_NAV_DROPDOWN_ITEMS.find((i) => i.path === path)?.icon ?? 'bx bx-wrench'
}
