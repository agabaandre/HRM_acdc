/**
 * Default endpoint / model / fallback hints when the admin picks an AI provider on Settings → AI.
 * Backend uses OpenAI-compatible POST {base}/chat/completions (see TicketSubjectAiService).
 */
export type AiProviderId = 'openai' | 'gemini' | 'custom'

export interface AiProviderPreset {
  ai_api_endpoint: string
  ai_model_name: string
  ai_fallback_order: string
}

export const AI_PROVIDER_PRESETS: Record<AiProviderId, AiProviderPreset> = {
  openai: {
    ai_api_endpoint: 'https://api.openai.com/v1',
    ai_model_name: 'gpt-4o-mini',
    ai_fallback_order: 'openai',
  },
  gemini: {
    ai_api_endpoint: 'https://generativelanguage.googleapis.com/v1beta/openai',
    ai_model_name: 'gemini-2.0-flash',
    ai_fallback_order: 'gemini',
  },
  custom: {
    ai_api_endpoint: '',
    ai_model_name: '',
    ai_fallback_order: 'openai,gemini',
  },
}

export function normalizeAiProvider(raw: string | null | undefined): AiProviderId {
  if (raw === 'gemini' || raw === 'custom') {
    return raw
  }
  return 'openai'
}

/** Apply preset fields for the given provider (used after user changes the provider select). */
export function applyAiProviderPreset(
  form: { ai_api_endpoint: string; ai_model_name: string; ai_fallback_order: string },
  provider: string,
): void {
  const id = normalizeAiProvider(provider)
  const p = AI_PROVIDER_PRESETS[id]
  form.ai_api_endpoint = p.ai_api_endpoint
  form.ai_model_name = p.ai_model_name
  form.ai_fallback_order = p.ai_fallback_order
}

export function aiModelPlaceholder(provider: string): string {
  const id = normalizeAiProvider(provider)
  return AI_PROVIDER_PRESETS[id].ai_model_name || 'e.g. gpt-4o-mini or your-model-id'
}
