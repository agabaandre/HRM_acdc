export interface StatusPriorityMeta {
  label: string
  color: string
  bg: string
}

export function statusMeta(status: string): StatusPriorityMeta {
  switch (status) {
    case 'open':
      return { label: 'Open', color: '#1d4ed8', bg: '#dbeafe' }
    case 'pending':
      return { label: 'Pending', color: '#4338ca', bg: '#e0e7ff' }
    case 'in_progress':
      return { label: 'In progress', color: '#6d28d9', bg: '#ede9fe' }
    case 'awaiting_requester_confirmation':
      return { label: 'Awaiting confirm', color: '#b45309', bg: '#fef3c7' }
    case 'resolved':
      return { label: 'Resolved', color: '#15803d', bg: '#dcfce7' }
    case 'closed':
      return { label: 'Closed', color: '#334155', bg: '#e2e8f0' }
    default:
      return { label: status, color: '#334155', bg: '#e2e8f0' }
  }
}

export function priorityMeta(priority: string): StatusPriorityMeta {
  switch (priority) {
    case 'urgent':
      return { label: 'Urgent', color: '#991b1b', bg: '#fee2e2' }
    case 'high':
      return { label: 'High', color: '#9a3412', bg: '#ffedd5' }
    case 'medium':
      return { label: 'Medium', color: '#1e3a8a', bg: '#dbeafe' }
    case 'low':
      return { label: 'Low', color: '#334155', bg: '#e2e8f0' }
    default:
      return { label: priority, color: '#334155', bg: '#e2e8f0' }
  }
}

/** e.g. "1-20 of 45 tickets" or "12 tickets" */
export function formatTableCountLabel(
  _shown: number,
  total: number,
  page: number,
  perPage: number,
): string {
  if (total === 0) {
    return '0 tickets'
  }
  const start = (page - 1) * perPage + 1
  const end = Math.min(total, page * perPage)
  if (start === 1 && end === total) {
    return `${total} ticket${total === 1 ? '' : 's'}`
  }
  return `${start}-${end} of ${total} ticket${total === 1 ? '' : 's'}`
}

export function rowIndex(page: number, perPage: number, index: number): number {
  return (page - 1) * perPage + index + 1
}
