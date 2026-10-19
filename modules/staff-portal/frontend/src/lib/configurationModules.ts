export interface ConfigStat {
  key: string
  label: string
}

export interface ConfigSection {
  path: string
  label: string
  icon: string
  stats: ConfigStat[]
}

export interface ConfigModule {
  id: 'users' | 'organisation' | 'workflows' | 'system'
  label: string
  code: string
  portalPath: string
  summaryKey: 'users' | 'organisation' | 'workflows' | 'system'
  sections: ConfigSection[]
}

export const CONFIG_MODULES: ConfigModule[] = [
  {
    id: 'users',
    label: 'Users & Roles',
    code: 'USR',
    portalPath: '/configuration/users',
    summaryKey: 'users',
    sections: [
      {
        path: 'accounts',
        label: 'User Accounts',
        icon: 'fa-user',
        stats: [{ key: 'accounts', label: 'Accounts' }],
      },
      {
        path: 'roles',
        label: 'Roles & Permissions',
        icon: 'fa-user-shield',
        stats: [
          { key: 'roles', label: 'Roles' },
          { key: 'permissions', label: 'Permissions' },
        ],
      },
      {
        path: 'delegations',
        label: 'Delegations',
        icon: 'fa-user-clock',
        stats: [
          { key: 'delegations', label: 'Acting roles' },
          { key: 'acting_contracts', label: 'Acting contracts' },
        ],
      },
    ],
  },
  {
    id: 'organisation',
    label: 'Organisation',
    code: 'ORG',
    portalPath: '/configuration/organisation',
    summaryKey: 'organisation',
    sections: [
      {
        path: 'directorates',
        label: 'Directorates & Units',
        icon: 'fa-sitemap',
        stats: [
          { key: 'directorates', label: 'Directorates' },
          { key: 'divisions', label: 'Divisions' },
          { key: 'units', label: 'Units' },
        ],
      },
      {
        path: 'locations',
        label: 'Locations & RCCs',
        icon: 'fa-location-dot',
        stats: [
          { key: 'locations', label: 'Duty stations' },
          { key: 'regions', label: 'Regions' },
        ],
      },
      {
        path: 'cost-centres',
        label: 'Cost Centres',
        icon: 'fa-coins',
        stats: [{ key: 'cost_centres', label: 'Cost centres' }],
      },
    ],
  },
  {
    id: 'workflows',
    label: 'Workflows',
    code: 'WFL',
    portalPath: '/configuration/workflows',
    summaryKey: 'workflows',
    sections: [
      {
        path: 'approval-chains',
        label: 'Approval Chains',
        icon: 'fa-diagram-project',
        stats: [{ key: 'approval_chains', label: 'Chains' }],
      },
      {
        path: 'notifications',
        label: 'Notifications',
        icon: 'fa-bell',
        stats: [
          { key: 'notifications', label: 'Emails sent' },
          { key: 'email_providers', label: 'Providers' },
        ],
      },
      {
        path: 'forms',
        label: 'Forms & Templates',
        icon: 'fa-file-lines',
        stats: [
          { key: 'leave_types', label: 'Leave types' },
          { key: 'contract_types', label: 'Contract types' },
        ],
      },
    ],
  },
  {
    id: 'system',
    label: 'System',
    code: 'SYS',
    portalPath: '/configuration/system',
    summaryKey: 'system',
    sections: [
      {
        path: 'modules',
        label: 'Module Settings',
        icon: 'fa-table-cells-large',
        stats: [{ key: 'modules', label: 'Modules' }],
      },
      {
        path: 'integrations',
        label: 'Integrations',
        icon: 'fa-plug',
        stats: [
          { key: 'email_providers', label: 'Email' },
          { key: 'ai_providers', label: 'AI' },
          { key: 'oauth_clients', label: 'OAuth' },
        ],
      },
      {
        path: 'audit',
        label: 'Audit Log',
        icon: 'fa-clipboard-list',
        stats: [{ key: 'audit', label: 'Log entries' }],
      },
      {
        path: 'languages',
        label: 'Languages',
        icon: 'fa-language',
        stats: [{ key: 'languages', label: 'Languages' }],
      },
    ],
  },
]

export function configModuleFromPath(path: string): ConfigModule | null {
  return CONFIG_MODULES.find((mod) => path === mod.portalPath || path.startsWith(`${mod.portalPath}/`)) ?? null
}
