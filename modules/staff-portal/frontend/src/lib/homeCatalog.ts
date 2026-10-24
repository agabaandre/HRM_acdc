export type HomeCatalogTabId = 'operations' | 'programs' | 'configuration'

export interface HomeCatalogItem {
  label: string
  /** Live cbp_modules.module_key. The card uses that module’s link when it is assigned. */
  moduleKey?: string
  /** Path under the staff portal, e.g. /performance */
  portalPath?: string
  /** Path inside the module after SSO, e.g. approver-dashboard */
  launchPath?: string
  /** Path on this host outside the staff portal, e.g. /ACDC/2026/AfricaCDCDevTrack */
  sitePath?: string
  icon?: string
}

export interface HomeCatalogGroup {
  title: string
  items: HomeCatalogItem[]
}

export interface HomeCatalogTab {
  id: HomeCatalogTabId
  label: string
  groups: HomeCatalogGroup[]
}

/**
 * Shortcuts under the home search. Ordered by how many distinct people use them:
 * Helpdesk tickets, then staff-portal page opens, then APM memos.
 */
export const FREQUENT_MODULES: HomeCatalogItem[] = [
  { label: 'Helpdesk (HDK)', moduleKey: 'helpdesk_itsm', icon: 'fa-headset' },
  { label: 'Performance (PRF)', portalPath: '/performance', icon: 'fa-chart-line' },
  { label: 'Approvals Management (APM)', moduleKey: 'approvals_management', launchPath: 'approver-dashboard', icon: 'fa-stamp' },
  { label: 'Workplan (WPL)', portalPath: '/workplan', icon: 'fa-calendar-days' },
  { label: 'Staff Portal (STP)', portalPath: '/dashboard', icon: 'fa-id-badge' },
  { label: 'Staff (STF)', portalPath: '/staff', icon: 'fa-user' },
]

export const HOME_CATALOG: HomeCatalogTab[] = [
  {
    id: 'operations',
    label: 'Operations',
    groups: [
      {
        title: 'PRA',
        items: [{ label: 'Result Based Management (RBM)', moduleKey: 'pra', icon: 'fa-calendar-days' }],
      },
      {
        title: 'Admin & Facilities',
        items: [
          { label: 'Approvals Management (APM)', moduleKey: 'approvals_management', launchPath: 'approver-dashboard', icon: 'fa-stamp' },
          { label: 'Helpdesk (HDK)', moduleKey: 'helpdesk_itsm', icon: 'fa-headset' },
          { label: 'Registry (REG)', moduleKey: 'registry', launchPath: 'dashboard', icon: 'fa-folder-open' },
          { label: 'Office Seating (OFS)', moduleKey: 'office_seating', icon: 'fa-chair' },
          { label: 'Room Booking (RMB)', moduleKey: 'room_booking', icon: 'fa-door-open' },
          { label: 'Access Control (ACL)', moduleKey: 'access_control', icon: 'fa-key' },
          { label: 'Fleet Management (FLT)', moduleKey: 'fleet', icon: 'fa-car' },
        ],
      },
      {
        title: 'HR',
        items: [
          { label: 'Recruitment (REC)', moduleKey: 'recruitment', icon: 'fa-user-plus' },
          { label: 'Staff Portal (STP)', portalPath: '/dashboard', icon: 'fa-id-badge' },
          { label: 'Staff Locator (STL)', moduleKey: 'staff_locator', icon: 'fa-location-dot' },
          { label: 'Self Service (SLS)', moduleKey: 'self_service', icon: 'fa-user' },
          { label: 'Work Time Management (WTM)', moduleKey: 'work_time', icon: 'fa-clock' },
        ],
      },
      {
        title: 'Finance',
        items: [
          { label: 'Budgeting (BDG)', moduleKey: 'budgeting', icon: 'fa-file-invoice-dollar' },
          { label: 'Financial Management (FIN)', moduleKey: 'finance_management', icon: 'fa-coins' },
          { label: 'Accounting (ACC)', moduleKey: 'accounting', icon: 'fa-calculator' },
        ],
      },
      {
        title: 'SCM',
        items: [
          { label: 'Annual Procurement Plan (APP)', moduleKey: 'procurement_plan', icon: 'fa-list-check' },
          { label: 'Sourcing (SRC)', moduleKey: 'sourcing', icon: 'fa-magnifying-glass-dollar' },
          { label: 'Electronic Procurement (EPR)', moduleKey: 'e_procurement', icon: 'fa-cart-shopping' },
          { label: 'Contracts (CTR)', moduleKey: 'contracts', icon: 'fa-file-contract' },
          { label: 'Logistics (LOG)', moduleKey: 'logistics', icon: 'fa-truck' },
          { label: 'Assets (AST)', moduleKey: 'assets', icon: 'fa-boxes-stacked' },
          { label: 'Travel (TRV)', moduleKey: 'travel', icon: 'fa-plane' },
        ],
      },
      {
        title: 'MIS',
        items: [
          { label: 'Information Technology (IT)', moduleKey: 'it', launchPath: 'dashboard', icon: 'fa-server' },
          { label: 'Development Tracker (DT)', moduleKey: 'devtrack', icon: 'fa-code-branch' },
          { label: 'MIS Dashboard (MSD)', moduleKey: 'misdashboard', icon: 'fa-chart-pie' },
        ],
      },
      {
        title: 'OIO',
        items: [
          { label: 'Risk Register (RSK)', moduleKey: 'risk_register', launchPath: 'risks', icon: 'fa-shield-halved' },
        ],
      },
    ],
  },
  {
    id: 'programs',
    label: 'Programs',
    groups: [
      {
        title: 'EPR',
        items: [
          { label: 'Logistics (LOG)', moduleKey: 'epr_logistics', icon: 'fa-truck-medical' },
          { label: 'ePHEM (EPH)', moduleKey: 'ephem', icon: 'fa-kit-medical' },
          { label: 'AVHOC (AVH)', moduleKey: 'avhoc', icon: 'fa-people-group' },
          { label: 'FRORP (FRP)', moduleKey: 'frorp', icon: 'fa-flag' },
        ],
      },
      {
        title: 'SDI',
        items: [
          { label: 'EMS (EMS)', moduleKey: 'ems', icon: 'fa-truck-medical' },
          { label: 'Events Dashboard (EVD)', moduleKey: 'events_dashboard', icon: 'fa-calendar-days' },
          { label: 'IMST tracker (IMS)', moduleKey: 'imst', icon: 'fa-virus' },
        ],
      },
      {
        title: 'LAB',
        items: [
          { label: 'NIMS (NIM)', moduleKey: 'nims', icon: 'fa-flask' },
          { label: 'Asana (ASN)', moduleKey: 'asana', icon: 'fa-list-check' },
        ],
      },
      {
        title: 'Science',
        items: [
          { label: 'Prioritization (PRI)', moduleKey: 'prioritization', icon: 'fa-ranking-star' },
          { label: 'Knowledge Hub (KH)', moduleKey: 'knowledge_hub', icon: 'fa-book' },
          { label: 'RedCap (RDC)', moduleKey: 'redcap', icon: 'fa-database' },
        ],
      },
      {
        title: 'Comms',
        items: [{ label: 'Media Hub (MDH)', moduleKey: 'media_hub', icon: 'fa-bullhorn' }],
      },
      {
        title: 'PHIR',
        items: [
          { label: 'CUE (CUE)', moduleKey: 'cue', icon: 'fa-graduation-cap' },
          { label: 'Learning Management System (LMS)', moduleKey: 'lms', icon: 'fa-chalkboard-user' },
        ],
      },
      {
        title: 'Lusaka Agenda',
        items: [{ label: 'Scorecard Dashboard (SCD)', moduleKey: 'scorecard', icon: 'fa-chart-column' }],
      },
      {
        title: 'MIS',
        items: [{ label: 'StarLink Dashboard (SLD)', moduleKey: 'starlink', icon: 'fa-satellite-dish' }],
      },
    ],
  },
  {
    id: 'configuration',
    label: 'Configuration',
    groups: [
      {
        title: '',
        items: [
          { label: 'Users & Roles (USR)', portalPath: '/configuration/users', icon: 'fa-users' },
          { label: 'Organisation (ORG)', portalPath: '/configuration/organisation', icon: 'fa-sitemap' },
          { label: 'Workflows (WFL)', portalPath: '/configuration/workflows', icon: 'fa-diagram-project' },
          { label: 'System (SYS)', portalPath: '/configuration/system', icon: 'fa-gear' },
        ],
      },
    ],
  },
]
