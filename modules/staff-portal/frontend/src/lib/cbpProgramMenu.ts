/** Programs catalog shown in the CBP Modules menu. Icons match the home catalog. */
export interface CbpProgramItem {
  label: string
  icon: string
  /** cbp_modules.module_key. Shown only when that module is enabled or shown for admins. */
  moduleKey: string
}

export interface CbpProgramGroup {
  title: string
  items: CbpProgramItem[]
}

export const CBP_PROGRAM_GROUPS: CbpProgramGroup[] = [
  {
    title: 'EPR',
    items: [
      { label: 'Logistics (LOG)', icon: 'fa-truck-medical', moduleKey: 'epr_logistics' },
      { label: 'ePHEM (EPH)', icon: 'fa-kit-medical', moduleKey: 'ephem' },
      { label: 'AVHOC (AVH)', icon: 'fa-people-group', moduleKey: 'avhoc' },
      { label: 'FRORP (FRP)', icon: 'fa-flag', moduleKey: 'frorp' },
    ],
  },
  {
    title: 'SDI',
    items: [
      { label: 'EMS (EMS)', icon: 'fa-truck-medical', moduleKey: 'ems' },
      { label: 'Events Dashboard (EVD)', icon: 'fa-calendar-days', moduleKey: 'events_dashboard' },
      { label: 'IMST tracker (IMS)', icon: 'fa-virus', moduleKey: 'imst' },
    ],
  },
  {
    title: 'LAB',
    items: [
      { label: 'NIMS (NIM)', icon: 'fa-flask', moduleKey: 'nims' },
      { label: 'Asana (ASN)', icon: 'fa-list-check', moduleKey: 'asana' },
    ],
  },
  {
    title: 'Science',
    items: [
      { label: 'Prioritization (PRI)', icon: 'fa-ranking-star', moduleKey: 'prioritization' },
      { label: 'Knowledge Hub (KH)', icon: 'fa-book', moduleKey: 'knowledge_hub' },
      { label: 'RedCap (RDC)', icon: 'fa-database', moduleKey: 'redcap' },
    ],
  },
  {
    title: 'Comms',
    items: [{ label: 'Media Hub (MDH)', icon: 'fa-bullhorn', moduleKey: 'media_hub' }],
  },
  {
    title: 'PHIR',
    items: [
      { label: 'CUE (CUE)', icon: 'fa-graduation-cap', moduleKey: 'cue' },
      { label: 'Learning Management System (LMS)', icon: 'fa-chalkboard-user', moduleKey: 'lms' },
    ],
  },
  {
    title: 'Lusaka Agenda',
    items: [{ label: 'Scorecard Dashboard (SCD)', icon: 'fa-chart-column', moduleKey: 'scorecard' }],
  },
  {
    title: 'MIS',
    items: [{ label: 'StarLink Dashboard (SLD)', icon: 'fa-satellite-dish', moduleKey: 'starlink' }],
  },
]

export const CBP_PROGRAM_KEYS = new Set(
  CBP_PROGRAM_GROUPS.flatMap((group) => group.items.map((item) => item.moduleKey)),
)

/** Home-catalog icons for live operations modules, keyed by cbp_modules.module_key. */
const OPERATION_ICONS: Record<string, string> = {
  pra: 'fa-calendar-days',
  approvals_management: 'fa-stamp',
  helpdesk_itsm: 'fa-headset',
  registry: 'fa-folder-open',
  staff_portal: 'fa-id-badge',
  finance_management: 'fa-coins',
  devtrack: 'fa-code-branch',
  it: 'fa-server',
  risk_register: 'fa-shield-halved',
}

export function solidIcon(icon?: string | null): string {
  const raw = (icon || 'fa-th').trim()
  if (raw.includes('fa-solid') || raw.includes('fa-regular') || raw.includes('fa-brands') || raw.includes('fas ')) {
    return raw
  }
  const name = raw.replace(/^(?:fa\s+)+/, '').replace(/^fa-/, '')
  return `fa-solid fa-${name}`
}

export function moduleMenuIcon(moduleKey?: string | null, fallback?: string | null): string {
  const key = (moduleKey || '').trim()
  return solidIcon(OPERATION_ICONS[key] || fallback)
}
