<script setup lang="ts">
import { computed, ref } from 'vue'
import type { FormSubmitEvent } from '../../types/form'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess, notifyWarning } from '../../lib/notify'
import { useInjectedHelpdeskAdminSettings } from '../../composables/useHelpdeskAdminSettings'
import type { AiProviderId } from '../../lib/aiProviderPresets'
import { applyAiProviderPreset, aiModelPlaceholder, normalizeAiProvider } from '../../lib/aiProviderPresets'

const ctx = useInjectedHelpdeskAdminSettings()

const testBusy = ref(false)

interface AiTestResult {
  ok: boolean
  message: string
  provider?: string
  endpoint?: string
  model?: string
  ai_active?: boolean
  key_present?: boolean
  latency_ms?: number | null
  http_status?: number | null
  reply_preview?: string | null
}

const testResult = ref<AiTestResult | null>(null)

const providerItems: { label: string; value: AiProviderId }[] = [
  { label: 'OpenAI', value: 'openai' },
  { label: 'Gemini', value: 'gemini' },
  { label: 'Custom API', value: 'custom' },
]

const endpointHint = computed(() => {
  switch (normalizeAiProvider(ctx.form.ai_provider)) {
    case 'gemini':
      return 'Google Gemini OpenAI-compatible base (use a Gemini API key).'
    case 'custom':
      return 'Any OpenAI-compatible API root (often ends with /v1).'
    default:
      return 'Official OpenAI API; key typically starts with sk-…'
  }
})

const modelPlaceholder = computed(() => aiModelPlaceholder(ctx.form.ai_provider))

const apiKeyPlaceholder = computed(() => {
  switch (normalizeAiProvider(ctx.form.ai_provider)) {
    case 'gemini':
      return 'Gemini API key (Google AI Studio)'
    case 'custom':
      return 'API key / token for your endpoint'
    default:
      return 'sk-…'
  }
})

function onAiProviderChange() {
  applyAiProviderPreset(ctx.form, ctx.form.ai_provider)
}

async function onSaveAi(_event: FormSubmitEvent<typeof ctx.form>) {
  const payload: Record<string, unknown> = {
    ai_provider: ctx.form.ai_provider,
    ai_api_endpoint: ctx.form.ai_api_endpoint || null,
    ai_model_name: ctx.form.ai_model_name || null,
    ai_active: ctx.form.ai_active,
    ai_agent_assignment_enabled: ctx.form.ai_agent_assignment_enabled,
    ai_fallback_order: ctx.form.ai_fallback_order || null,
  }
  if (ctx.form.ai_api_key.trim() !== '') {
    payload.ai_api_key = ctx.form.ai_api_key.trim()
  }
  await ctx.savePartial(payload, 'AI settings saved.')
}

async function testAiConfiguration() {
  testBusy.value = true
  testResult.value = null
  try {
    const payload: Record<string, unknown> = {
      ai_api_endpoint: ctx.form.ai_api_endpoint || null,
      ai_model_name: ctx.form.ai_model_name || null,
    }
    if (ctx.form.ai_api_key.trim() !== '') {
      payload.ai_api_key = ctx.form.ai_api_key.trim()
    }
    const { data } = await api.post<{ data: AiTestResult }>(
      '/api/v1/admin/settings/test-ai',
      payload,
      { validateStatus: (s) => s === 200 || s === 422 },
    )
    const result = data.data
    testResult.value = result
    if (result.ok) {
      notifySuccess(result.message)
    } else {
      notifyWarning(result.message)
    }
  } catch (e) {
    const msg = apiErrorMessage(e, 'AI configuration test failed.')
    testResult.value = { ok: false, message: msg }
    notifyError(msg)
  } finally {
    testBusy.value = false
  }
}
</script>

<template>
  <section class="panel" aria-labelledby="ai-heading">
    <h2 id="ai-heading">AI provider &amp; models</h2>
    <p class="hint">URS §10 — provider, endpoint, model, keys, and fallback. Keys are stored encrypted; leave blank to keep the current key.</p>

    <UCard>
      <UForm :state="ctx.form" class="hd-form" :disabled="ctx.busy || testBusy" @submit="onSaveAi">
        <UFormField label="Provider" name="ai_provider">
          <USelect
            v-model="ctx.form.ai_provider"
            :items="providerItems"
            class="w-full"
            @update:model-value="onAiProviderChange"
          />
        </UFormField>
        <p class="field-hint">{{ endpointHint }}</p>
        <UFormField label="API base" name="ai_api_endpoint">
          <UInput v-model="ctx.form.ai_api_endpoint" type="url" autocomplete="off" placeholder="https://…" class="w-full" />
        </UFormField>
        <UFormField label="Model name" name="ai_model_name">
          <UInput v-model="ctx.form.ai_model_name" type="text" :placeholder="modelPlaceholder" class="w-full" />
        </UFormField>
        <UFormField name="ai_active">
          <UCheckbox v-model="ctx.form.ai_active" label="AI active (subject hints & optional agent routing)" />
        </UFormField>
        <UFormField name="ai_agent_assignment_enabled">
          <UCheckbox
            v-model="ctx.form.ai_agent_assignment_enabled"
            label="AI-assisted agent assignment (end-user tickets only — uses the same API key; falls back to duty station, division, category & workload rules)"
          />
        </UFormField>
        <p v-if="ctx.keyConfigured" class="key-hint">API key is on file. Enter a new key only to replace it.</p>
        <UFormField label="API key (optional)" name="ai_api_key">
          <UInput
            v-model="ctx.form.ai_api_key"
            type="password"
            autocomplete="new-password"
            :placeholder="apiKeyPlaceholder"
            class="w-full"
          />
        </UFormField>
        <UFormField label="Fallback order (comma-separated provider ids)" name="ai_fallback_order">
          <UInput v-model="ctx.form.ai_fallback_order" type="text" placeholder="openai" class="w-full" />
        </UFormField>

        <div class="hd-form-actions">
          <UButton type="submit" color="primary" :loading="ctx.busy" :disabled="testBusy">Save AI settings</UButton>
          <UButton
            type="button"
            color="neutral"
            variant="outline"
            :loading="testBusy"
            :disabled="ctx.busy"
            @click="testAiConfiguration"
          >
            Test AI configuration
          </UButton>
        </div>

        <div
          v-if="testResult"
          class="test-result"
          :class="testResult.ok ? 'is-ok' : 'is-fail'"
          role="status"
        >
          <strong>{{ testResult.ok ? 'Connection OK' : 'Connection failed' }}</strong>
          <p>{{ testResult.message }}</p>
          <ul class="test-meta">
            <li v-if="testResult.provider">Provider: {{ testResult.provider }}</li>
            <li v-if="testResult.endpoint">Endpoint: {{ testResult.endpoint }}</li>
            <li v-if="testResult.model">Model: {{ testResult.model }}</li>
            <li>API key on file / form: {{ testResult.key_present ? 'yes' : 'no' }}</li>
            <li>AI active: {{ testResult.ai_active ? 'yes' : 'no' }}</li>
            <li v-if="testResult.latency_ms != null">Latency: {{ testResult.latency_ms }} ms</li>
            <li v-if="testResult.http_status != null">HTTP: {{ testResult.http_status }}</li>
            <li v-if="testResult.reply_preview">Reply: {{ testResult.reply_preview }}</li>
          </ul>
        </div>
      </UForm>
    </UCard>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.5;
}
.field-hint {
  font-size: 0.78rem;
  color: #64748b;
  margin: -0.35rem 0 0.25rem;
  line-height: 1.45;
}
.key-hint {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0;
}
.hd-form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: center;
}
.test-result {
  margin-top: 0.85rem;
  padding: 0.85rem 1rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}
.test-result.is-ok {
  border-color: #86efac;
  background: #f0fdf4;
}
.test-result.is-fail {
  border-color: #fecaca;
  background: #fef2f2;
}
.test-result strong {
  display: block;
  margin-bottom: 0.25rem;
}
.test-result p {
  margin: 0 0 0.5rem;
  font-size: 0.9rem;
  line-height: 1.45;
}
.test-meta {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 0.8rem;
  color: #475569;
  line-height: 1.5;
}
code {
  font-size: 0.85em;
}
</style>
