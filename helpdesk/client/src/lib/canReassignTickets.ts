interface ReassignProfile {
  is_helpdesk_admin?: boolean
  role?: string
  can_reassign_tickets?: boolean
}

export function canReassignTickets(profile: ReassignProfile | null | undefined): boolean {
  if (!profile) {
    return false
  }

  return !!profile.is_helpdesk_admin || profile.role === 'admin' || !!profile.can_reassign_tickets
}

export function ticketStatusAllowsReassign(status: string): boolean {
  return ['open', 'pending', 'in_progress'].includes(status)
}
