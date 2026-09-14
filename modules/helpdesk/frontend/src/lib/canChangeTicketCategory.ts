interface CategoryChangeProfile {
  is_helpdesk_admin?: boolean
  can_change_ticket_category?: boolean
}

interface AttachmentDeleteProfile {
  is_helpdesk_admin?: boolean
  can_delete_request_attachments?: boolean
}

export function canChangeTicketCategory(profile: CategoryChangeProfile | null | undefined): boolean {
  if (!profile) {
    return false
  }

  return !!profile.is_helpdesk_admin || !!profile.can_change_ticket_category
}

export function canDeleteRequestAttachments(profile: AttachmentDeleteProfile | null | undefined): boolean {
  if (!profile) {
    return false
  }

  return !!profile.is_helpdesk_admin || !!profile.can_delete_request_attachments
}

export function ticketStatusAllowsCategoryChange(status: string): boolean {
  return !['closed', 'resolved'].includes(status)
}

export function ticketStatusAllowsAttachmentDelete(status: string): boolean {
  return ticketStatusAllowsCategoryChange(status)
}
