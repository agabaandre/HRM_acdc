export const SETTINGS_SECTIONS = ['general', 'ai', 'agents', 'categories', 'jobs', 'integrations', 'logging'] as const

export type SettingsSectionId = (typeof SETTINGS_SECTIONS)[number]

export function parseSettingsSection(value: unknown): SettingsSectionId {
  const s = typeof value === 'string' ? value : ''
  return (SETTINGS_SECTIONS as readonly string[]).includes(s) ? (s as SettingsSectionId) : 'general'
}

export const SETTINGS_SECTION_LABELS: Record<SettingsSectionId, string> = {
  general: 'General',
  ai: 'AI models & provider',
  agents: 'Agents & category routing',
  categories: 'Issue categories',
  jobs: 'Jobs',
  integrations: 'WhatsApp & Teams',
  logging: 'Audit & ISO logging',
}

/** Top primary nav → Settings dropdown (paths must match router children). */
export const SETTINGS_NAV_DROPDOWN_ITEMS = [
  { path: '/settings/general', label: SETTINGS_SECTION_LABELS.general },
  { path: '/settings/ai', label: SETTINGS_SECTION_LABELS.ai },
  { path: '/settings/agents', label: SETTINGS_SECTION_LABELS.agents },
  { path: '/settings/categories', label: SETTINGS_SECTION_LABELS.categories },
  { path: '/settings/jobs', label: SETTINGS_SECTION_LABELS.jobs },
  { path: '/settings/integrations', label: SETTINGS_SECTION_LABELS.integrations },
  { path: '/settings/logging', label: SETTINGS_SECTION_LABELS.logging },
] as const
