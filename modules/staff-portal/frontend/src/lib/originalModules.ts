export interface OriginalModuleSection {
  label: string
  /** Path inside the module, after SSO. Empty opens the module home. */
  path: string
  icon: string
}

export interface OriginalModulePage {
  title: string
  lede: string
  icon: string
  moduleKey: string
  sections: OriginalModuleSection[]
}

/** Original Approvals Management menu. */
export const APM_MODULE: OriginalModulePage = {
  title: 'Approvals Management',
  lede: 'Memos, approvals, budget execution, and the rest of Approvals Management.',
  icon: 'fa-solid fa-stamp',
  moduleKey: 'approvals_management',
  sections: [
    { label: 'Dashboard', path: 'approver-dashboard', icon: 'fa-solid fa-gauge-high' },
    { label: 'APM Home', path: 'home', icon: 'fa-solid fa-house' },
    { label: 'Budget Execution', path: 'budget-execution', icon: 'fa-solid fa-chart-column' },
    { label: 'Weekly brief', path: 'weekly-briefing', icon: 'fa-solid fa-calendar-week' },
    { label: 'Returns', path: 'returned-memos', icon: 'fa-solid fa-rotate-left' },
    { label: 'Approvals', path: 'pending-approvals', icon: 'fa-solid fa-check-double' },
    { label: 'Memos', path: 'matrices', icon: 'fa-solid fa-file-lines' },
    { label: 'Activities', path: 'activities', icon: 'fa-solid fa-list-check' },
    { label: 'Staff List', path: 'staff', icon: 'fa-solid fa-users' },
    { label: 'Reporting', path: 'reports', icon: 'fa-solid fa-chart-pie' },
    { label: 'Workflows', path: 'workflows', icon: 'fa-solid fa-diagram-project' },
  ],
}

/** Original IT module menu. */
export const IT_MODULE: OriginalModulePage = {
  title: 'Information Technology',
  lede: 'Licenses, IT assets, information systems, and servers from the IT module.',
  icon: 'fa-solid fa-server',
  moduleKey: 'it',
  sections: [
    { label: 'Dashboard', path: 'dashboard', icon: 'fa-solid fa-chart-pie' },
    { label: 'Licenses', path: 'licenses', icon: 'fa-solid fa-key' },
    { label: 'ITSM', path: 'itsm', icon: 'fa-solid fa-laptop' },
    { label: 'Information Systems', path: 'information-systems', icon: 'fa-solid fa-server' },
    { label: 'Servers', path: 'servers', icon: 'fa-solid fa-cloud' },
    { label: 'Settings', path: 'settings/license-alerts', icon: 'fa-solid fa-gear' },
  ],
}

/** Original registry menu. */
export const REGISTRY_MODULE: OriginalModulePage = {
  title: 'Registry',
  lede: 'Weekly documents, the current year, archives, and settings from the registry.',
  icon: 'fa-solid fa-folder-open',
  moduleKey: 'registry',
  sections: [
    { label: 'Dashboard', path: 'dashboard', icon: 'fa-solid fa-chart-pie' },
    { label: 'Weekly Documents', path: 'documents/week', icon: 'fa-solid fa-calendar-week' },
    { label: 'Current Year Documents', path: 'documents/year', icon: 'fa-solid fa-folder-open' },
    { label: 'Archives', path: 'archives', icon: 'fa-solid fa-box-archive' },
    { label: 'Settings', path: 'settings', icon: 'fa-solid fa-gear' },
  ],
}

/** Original Helpdesk menu. */
export const HELPDESK_MODULE: OriginalModulePage = {
  title: 'Helpdesk',
  lede: 'Tickets, the agent desk, knowledge base, and the rest of Helpdesk.',
  icon: 'fa-solid fa-headset',
  moduleKey: 'helpdesk_itsm',
  sections: [
    { label: 'Overview', path: '', icon: 'fa-solid fa-house' },
    { label: 'Agent desk', path: 'desk/agent', icon: 'fa-solid fa-desktop' },
    { label: 'Tickets', path: 'tickets', icon: 'fa-solid fa-headset' },
    { label: 'Create ticket', path: 'tickets/new', icon: 'fa-solid fa-plus' },
    { label: 'Knowledge base', path: 'knowledge-base/manage', icon: 'fa-solid fa-book-open' },
    { label: 'Reports', path: 'reports', icon: 'fa-solid fa-chart-column' },
    { label: 'Live screen', path: 'screen', icon: 'fa-solid fa-tv' },
    { label: 'Software requests', path: 'tools/software-requests', icon: 'fa-solid fa-box' },
    { label: 'Hosting', path: 'tools/hosting-requests', icon: 'fa-solid fa-cloud' },
    { label: 'Innovations', path: 'tools/innovation-requests', icon: 'fa-solid fa-lightbulb' },
    { label: 'Settings', path: 'settings', icon: 'fa-solid fa-gear' },
  ],
}
