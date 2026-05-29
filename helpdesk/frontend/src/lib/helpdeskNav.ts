/** Primary nav icons (Boxicons — loaded in index.html). */
export const HELP_DESK_NAV_ICONS = {
  overview: 'bx bx-home-alt',
  tickets: 'bx bx-support',
  newRequest: 'bx bx-plus-circle',
  agentDesk: 'bx bx-desktop',
  knowledgeBase: 'bx bx-book-open',
  reports: 'bx bx-bar-chart-alt-2',
  settings: 'bx bx-cog',
} as const

export const SETTINGS_NAV_ICONS: Record<string, string> = {
  general: 'bx bx-slider-alt',
  ai: 'bx bx-bot',
  agents: 'bx bx-group',
  categories: 'bx bx-category-alt',
  jobs: 'bx bx-time-five',
  integrations: 'bx bx-plug',
  logging: 'bx bx-list-check',
}

export function settingsNavIcon(path: string): string {
  const key = path.replace('/settings/', '')
  return SETTINGS_NAV_ICONS[key] ?? 'bx bx-chevron-right'
}
