import { api } from './api'
import { clearApiCache } from './apiCache'

export type AiDriverDef = {
  key: string
  label: string
  description: string
  api_endpoint: string
  model: string
  env_key?: string
}

export type AiProvider = {
  id: number
  uuid: string
  name: string
  slug: string
  driver: string
  api_endpoint: string
  model: string
  has_api_key: boolean
  description?: string | null
  is_default: boolean
  is_active: boolean
}

export type AiTestResult = {
  ok: boolean
  message: string
  provider?: string
  endpoint?: string
  model?: string
  key_present?: boolean
  latency_ms?: number | null
  http_status?: number | null
  reply_preview?: string | null
}

export async function fetchAiDrivers() {
  const { data } = await api.get<{ data: AiDriverDef[] }>('/api/v1/settings/ai-providers/drivers')
  return data.data
}

export async function fetchAiProviders() {
  const { data } = await api.get<{ data: AiProvider[] }>('/api/v1/settings/ai-providers')
  return data.data
}

export async function createAiProvider(payload: {
  name: string
  driver: string
  api_endpoint?: string
  model?: string
  api_key?: string
  description?: string
  is_default?: boolean
  is_active?: boolean
}) {
  const { data } = await api.post<{ data: AiProvider }>('/api/v1/settings/ai-providers', payload)
  clearApiCache()
  return data.data
}

export async function updateAiProvider(
  uuid: string,
  payload: Partial<{
    name: string
    api_endpoint: string
    model: string
    api_key: string
    description: string
    is_default: boolean
    is_active: boolean
  }>,
) {
  const { data } = await api.put<{ data: AiProvider }>(`/api/v1/settings/ai-providers/${uuid}`, payload)
  clearApiCache()
  return data.data
}

export async function deleteAiProvider(uuid: string) {
  await api.delete(`/api/v1/settings/ai-providers/${uuid}`)
  clearApiCache()
}

export async function setDefaultAiProvider(uuid: string) {
  const { data } = await api.post<{ data: AiProvider }>(`/api/v1/settings/ai-providers/${uuid}/default`)
  clearApiCache()
  return data.data
}

export async function testAiProvider(
  payload: { api_endpoint?: string; model?: string; api_key?: string; driver?: string },
  uuid?: string | null,
): Promise<AiTestResult> {
  const url = uuid
    ? `/api/v1/settings/ai-providers/${uuid}/test`
    : '/api/v1/settings/ai-providers/test'
  const { data } = await api.post<{ data: AiTestResult }>(url, payload, {
    validateStatus: (status) => status === 200 || status === 422,
  })
  if (!data?.data) {
    throw new Error('AI configuration test failed')
  }
  return data.data
}
