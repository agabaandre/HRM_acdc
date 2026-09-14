import type { MeProfile } from '../stores/auth'
import type { ToolsNavItem, ToolsPermissionKey } from './toolsNav'
import { TOOLS_NAV_DROPDOWN_ITEMS } from './toolsNav'

export function profileHasToolsPermission(
  profile: MeProfile | null | undefined,
  key: ToolsPermissionKey,
): boolean {
  if (!profile) return false
  if (profile.is_helpdesk_admin || profile.role === 'admin') return true
  return !!profile[key]
}

export function visibleToolsNavItems(profile: MeProfile | null | undefined): ToolsNavItem[] {
  return TOOLS_NAV_DROPDOWN_ITEMS.filter((item) => {
    if (!profile) return false
    if (item.publicToAuth) return true
    if (!item.permission) return true
    return profileHasToolsPermission(profile, item.permission)
  })
}

export function hasAnyToolsNavAccess(profile: MeProfile | null | undefined): boolean {
  if (!profile) return false
  if (profile.has_tools_access) return true
  return visibleToolsNavItems(profile).length > 0
}
