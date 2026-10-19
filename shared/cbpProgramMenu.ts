/** Programs catalog shown in the CBP Modules menu. Icons match the home catalog. */
export interface CbpProgramItem {
  label: string
  icon: string
}

export interface CbpProgramGroup {
  title: string
  items: CbpProgramItem[]
}

export const CBP_PROGRAM_GROUPS: CbpProgramGroup[] = [
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
]

export const CBP_PROGRAM_COUNT = CBP_PROGRAM_GROUPS.reduce((sum, group) => sum + group.items.length, 0)

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
