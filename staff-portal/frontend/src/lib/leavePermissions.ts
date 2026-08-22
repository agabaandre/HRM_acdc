/** Canonical leave permission IDs — keep in sync with LeavePermissions.php */
export const LEAVE_PERMS = {
  MAKE_REQUEST: 37,
  APPROVE_REQUEST: 73,
  /** Legacy CI3 overload (domain_controller) still accepted for view-all. */
  LEGACY_VIEW_ALL: 77,
  VIEW_ALL: 95,
  MANAGE_BALANCES: 96,
  MANAGE_SETTINGS: 97,
  MANAGE_HOLIDAYS: 98,
} as const

/** System Administrator, HR Manager, HR Admin — leave opening-balance admins. */
export const LEAVE_BALANCE_ADMIN_ROLES = [10, 20, 22] as const

export function canManageLeaveBalances(opts: {
  roleId?: number | string | null
  hasPermission: (code: number) => boolean
}): boolean {
  const roleId = Number(opts.roleId || 0)
  if ((LEAVE_BALANCE_ADMIN_ROLES as readonly number[]).includes(roleId)) {
    return true
  }
  return opts.hasPermission(LEAVE_PERMS.MANAGE_BALANCES)
}

export const LEAVE_MODULE_PERMS = [
  LEAVE_PERMS.MAKE_REQUEST,
  LEAVE_PERMS.APPROVE_REQUEST,
  LEAVE_PERMS.VIEW_ALL,
  LEAVE_PERMS.MANAGE_BALANCES,
  LEAVE_PERMS.MANAGE_SETTINGS,
  LEAVE_PERMS.MANAGE_HOLIDAYS,
  LEAVE_PERMS.LEGACY_VIEW_ALL,
] as const
