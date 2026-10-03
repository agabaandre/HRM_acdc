export const SETTINGS_SECTIONS = ['general', 'staff-api', 'app-logs', 'email', 'ai', 'agents', 'categories', 'it-assets', 'risk-matrix', 'jobs', 'integrations', 'software-requests', 'logging'] as const

export type SettingsSectionId = (typeof SETTINGS_SECTIONS)[number]

export function parseSettingsSection(value: unknown): SettingsSectionId {
  const s = typeof value === 'string' ? value : ''
  return (SETTINGS_SECTIONS as readonly string[]).includes(s) ? (s as SettingsSectionId) : 'general'
}

export const SETTINGS_SECTION_LABELS: Record<SettingsSectionId, string> = {
  general: 'General',
  'staff-api': 'Staff API',
  'app-logs': 'App logs & telemetry',
  email: 'Email settings',
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
  { path: '/settings/staff-api', label: SETTINGS_SECTION_LABELS['staff-api'] },
  { path: '/settings/app-logs', label: SETTINGS_SECTION_LABELS['app-logs'] },
  { path: '/settings/email', label: SETTINGS_SECTION_LABELS.email },
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
