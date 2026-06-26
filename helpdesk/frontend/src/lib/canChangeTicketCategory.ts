interface CategoryChangeProfile {
  is_helpdesk_admin?: boolean
  role?: string
}

export function canChangeTicketCategory(profile: CategoryChangeProfile | null | undefined): boolean {
  if (!profile) {
    return false
  }

  return !!profile.is_helpdesk_admin || profile.role === 'admin' || profile.role === 'supervisor'
}

export function ticketStatusAllowsCategoryChange(status: string): boolean {
  return !['closed', 'resolved'].includes(status)
}
