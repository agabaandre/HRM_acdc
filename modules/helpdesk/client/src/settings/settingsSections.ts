export const SETTINGS_SECTIONS = ['general', 'ai', 'agents', 'categories', 'it-assets', 'risk-matrix', 'jobs', 'integrations', 'software-requests', 'logging'] as const

export type SettingsSectionId = (typeof SETTINGS_SECTIONS)[number]

export function parseSettingsSection(value: unknown): SettingsSectionId {
  const s = typeof value === 'string' ? value : ''
  return (SETTINGS_SECTIONS as readonly string[]).includes(s) ? (s as SettingsSectionId) : 'general'
}

export const SETTINGS_SECTION_LABELS: Record<SettingsSectionId, string> = {
  general: 'General',
  ai: 'AI models & provider',
  agents: 'Agents & support groups',
  categories: 'Issue categories',
  'it-assets': 'IT Assets',
  'risk-matrix': 'Priority matrix',
  jobs: 'Jobs',
  integrations: 'WhatsApp & Teams',
  'software-requests': 'Software requests',
  logging: 'Audit & ISO logging',
}

/** Top primary nav → Settings dropdown (paths must match router children). */
export const SETTINGS_NAV_DROPDOWN_ITEMS = [
  { path: '/settings/general', label: SETTINGS_SECTION_LABELS.general },
  { path: '/settings/ai', label: SETTINGS_SECTION_LABELS.ai },
  { path: '/settings/agents', label: SETTINGS_SECTION_LABELS.agents },
  { path: '/settings/categories', label: SETTINGS_SECTION_LABELS.categories },
  { path: '/settings/it-assets', label: SETTINGS_SECTION_LABELS['it-assets'] },
  { path: '/settings/risk-matrix', label: SETTINGS_SECTION_LABELS['risk-matrix'] },
  { path: '/settings/jobs', label: SETTINGS_SECTION_LABELS.jobs },
  { path: '/settings/integrations', label: SETTINGS_SECTION_LABELS.integrations },
  { path: '/settings/software-requests', label: SETTINGS_SECTION_LABELS['software-requests'] },
  { path: '/settings/logging', label: SETTINGS_SECTION_LABELS.logging },
] as const
