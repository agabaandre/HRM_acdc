import { inject, reactive, ref, type InjectionKey } from 'vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'

export interface HelpdeskSettingsPayload {
  ai_provider: string | null
  ai_api_endpoint: string | null
  ai_model_name: string | null
  ai_active: boolean
  ai_agent_assignment_enabled: boolean
  ai_fallback_order: string | null
  branding_primary_hex: string | null
  branding_secondary_hex: string | null
  default_agent_division_ids: string | null
  require_resolution_confirmation: boolean
  ai_api_key: string
  ai_api_key_configured: boolean
}

export interface HelpdeskAdminSettingsForm {
  ai_provider: string
  ai_api_endpoint: string
  ai_model_name: string
  ai_active: boolean
  ai_agent_assignment_enabled: boolean
  ai_fallback_order: string
  branding_primary_hex: string
  branding_secondary_hex: string
  default_agent_division_ids: string
  require_resolution_confirmation: boolean
  ai_api_key: string
}

export interface HelpdeskAdminSettingsContext {
  form: HelpdeskAdminSettingsForm
  keyConfigured: boolean
  err: string | null
  ok: string | null
  busy: boolean
  load: () => Promise<void>
  savePartial: (patch: Record<string, unknown>, successMessage?: string) => Promise<void>
}

export const helpdeskAdminSettingsKey: InjectionKey<HelpdeskAdminSettingsContext> = Symbol('helpdeskAdminSettings')

export function createHelpdeskAdminSettings(): HelpdeskAdminSettingsContext {
  const err = ref<string | null>(null)
  const ok = ref<string | null>(null)
  const busy = ref(false)
  const keyConfigured = ref(false)

  const form = reactive<HelpdeskAdminSettingsForm>({
    ai_provider: 'openai',
    ai_api_endpoint: '',
    ai_model_name: '',
    ai_active: false,
    ai_agent_assignment_enabled: false,
    ai_fallback_order: '',
    branding_primary_hex: '',
    branding_secondary_hex: '',
    default_agent_division_ids: '21',
    require_resolution_confirmation: true,
    ai_api_key: '',
  })

  async function load() {
    err.value = null
    try {
      const { data } = await api.get<{ data: HelpdeskSettingsPayload }>('/api/v1/admin/settings')
      const d = data.data
      form.ai_provider = d.ai_provider ?? 'openai'
      form.ai_api_endpoint = d.ai_api_endpoint ?? ''
      form.ai_model_name = d.ai_model_name ?? ''
      form.ai_active = Boolean(d.ai_active)
      form.ai_agent_assignment_enabled = Boolean(d.ai_agent_assignment_enabled)
      form.ai_fallback_order = d.ai_fallback_order ?? ''
      form.branding_primary_hex = d.branding_primary_hex ?? '#0d7a3a'
      form.branding_secondary_hex = d.branding_secondary_hex ?? '#c9a227'
      form.default_agent_division_ids = d.default_agent_division_ids ?? '21'
      form.require_resolution_confirmation = Boolean(d.require_resolution_confirmation)
      form.ai_api_key = ''
      keyConfigured.value = Boolean(d.ai_api_key_configured)
    } catch (e: unknown) {
      err.value = apiErrorMessage(e, 'Failed to load settings')
    }
  }

  async function savePartial(patch: Record<string, unknown>, successMessage = 'Saved.') {
    busy.value = true
    err.value = null
    ok.value = null
    try {
      await api.put('/api/v1/admin/settings', patch)
      form.ai_api_key = ''
      ok.value = successMessage
      await load()
    } catch (e: unknown) {
      err.value = apiErrorMessage(e, 'Save failed')
    } finally {
      busy.value = false
    }
  }

  return reactive({
    form,
    keyConfigured,
    err,
    ok,
    busy,
    load,
    savePartial,
  }) as HelpdeskAdminSettingsContext
}

export function useInjectedHelpdeskAdminSettings(): HelpdeskAdminSettingsContext {
  const ctx = inject(helpdeskAdminSettingsKey)
  if (!ctx) {
    throw new Error('Helpdesk admin settings context is missing (use inside the Settings layout).')
  }
  return ctx
}
