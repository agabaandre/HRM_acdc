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
          { label: 'Office Seating (OFS)', icon: 'fa-chair' },
          { label: 'Room Booking (RMB)', icon: 'fa-door-open' },
          { label: 'Access Control (ACL)', icon: 'fa-key' },
          { label: 'Fleet Management (FLT)', icon: 'fa-car' },
        ],
      },
      {
        title: 'HR',
        items: [
          { label: 'Recruitment (REC)', icon: 'fa-user-plus' },
          { label: 'Staff Portal (STP)', portalPath: '/dashboard', icon: 'fa-id-badge' },
          { label: 'Staff Locator (STL)', icon: 'fa-location-dot' },
          { label: 'Self Service (SLS)', icon: 'fa-user' },
          { label: 'Work Time Management (WTM)', icon: 'fa-clock' },
        ],
      },
      {
        title: 'Finance',
        items: [
          { label: 'Budgeting (BDG)', icon: 'fa-file-invoice-dollar' },
          { label: 'Financial Management (FIN)', moduleKey: 'finance_management', icon: 'fa-coins' },
          { label: 'Accounting (ACC)', icon: 'fa-calculator' },
        ],
      },
      {
        title: 'SCM',
        items: [
          { label: 'Annual Procurement Plan (APP)', icon: 'fa-list-check' },
          { label: 'Sourcing (SRC)', icon: 'fa-magnifying-glass-dollar' },
          { label: 'Electronic Procurement (EPR)', icon: 'fa-cart-shopping' },
          { label: 'Contracts (CTR)', icon: 'fa-file-contract' },
          { label: 'Logistics (LOG)', icon: 'fa-truck' },
          { label: 'Assets (AST)', icon: 'fa-boxes-stacked' },
          { label: 'Travel (TRV)', icon: 'fa-plane' },
        ],
      },
      {
        title: 'MIS',
        items: [
          { label: 'Information Technology (IT)', moduleKey: 'it', launchPath: 'dashboard', icon: 'fa-server' },
          { label: 'Development Tracker (DT)', moduleKey: 'devtrack', icon: 'fa-code-branch' },
          { label: 'MIS Dashboard (MSD)', icon: 'fa-chart-pie' },
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
          { label: 'Logistics (LOG)', icon: 'fa-truck-medical' },
          { label: 'ePHEM (EPH)', icon: 'fa-kit-medical' },
          { label: 'AVHOC (AVH)', icon: 'fa-people-group' },
          { label: 'FRORP (FRP)', icon: 'fa-flag' },
        ],
      },
      {
        title: 'SDI',
        items: [
          { label: 'EMS (EMS)', icon: 'fa-truck-medical' },
          { label: 'Events Dashboard (EVD)', icon: 'fa-calendar-days' },
          { label: 'IMST tracker (IMS)', icon: 'fa-virus' },
        ],
      },
      {
        title: 'LAB',
        items: [
          { label: 'NIMS (NIM)', icon: 'fa-flask' },
          { label: 'Asana (ASN)', icon: 'fa-list-check' },
        ],
      },
      {
        title: 'Science',
        items: [
          { label: 'Prioritization (PRI)', icon: 'fa-ranking-star' },
          { label: 'Knowledge Hub (KH)', icon: 'fa-book' },
          { label: 'RedCap (RDC)', icon: 'fa-database' },
        ],
      },
      {
        title: 'Comms',
        items: [{ label: 'Media Hub (MDH)', icon: 'fa-bullhorn' }],
      },
      {
        title: 'PHIR',
        items: [
          { label: 'CUE (CUE)', icon: 'fa-graduation-cap' },
          { label: 'Learning Management System (LMS)', icon: 'fa-chalkboard-user' },
        ],
      },
      {
        title: 'Lusaka Agenda',
        items: [{ label: 'Scorecard Dashboard (SCD)', icon: 'fa-chart-column' }],
      },
      {
        title: 'MIS',
        items: [{ label: 'StarLink Dashboard (SLD)', icon: 'fa-satellite-dish' }],
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
