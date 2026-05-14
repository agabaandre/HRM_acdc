<script setup lang="ts">
import { computed } from 'vue'
import { useInjectedHelpdeskAdminSettings } from '../../composables/useHelpdeskAdminSettings'
import { applyAiProviderPreset, aiModelPlaceholder, normalizeAiProvider } from '../../lib/aiProviderPresets'

const ctx = useInjectedHelpdeskAdminSettings()

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

async function saveAi() {
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
</script>

<template>
  <section class="panel" aria-labelledby="ai-heading">
    <h2 id="ai-heading">AI provider &amp; models</h2>
    <p class="hint">URS §10 — provider, endpoint, model, keys, and fallback. Keys are stored encrypted; leave blank to keep the current key.</p>

    <div class="card">
      <label>Provider
        <select v-model="ctx.form.ai_provider" @change="onAiProviderChange">
          <option value="openai">OpenAI</option>
          <option value="gemini">Gemini</option>
          <option value="custom">Custom API</option>
        </select>
      </label>
      <p class="field-hint">{{ endpointHint }}</p>
      <label>API base
        <input v-model="ctx.form.ai_api_endpoint" type="url" autocomplete="off" placeholder="https://…" />
      </label>
      <label>Model name
        <input v-model="ctx.form.ai_model_name" type="text" :placeholder="modelPlaceholder" />
      </label>
      <label class="row">
        <input v-model="ctx.form.ai_active" type="checkbox" />
        AI active (subject hints &amp; optional agent routing)
      </label>
      <label class="row">
        <input v-model="ctx.form.ai_agent_assignment_enabled" type="checkbox" />
        AI-assisted agent assignment (end-user tickets only — uses the same API key; falls back to duty station, division, category &amp; workload rules)
      </label>
      <p v-if="ctx.keyConfigured" class="key-hint">API key is on file. Enter a new key only to replace it.</p>
      <label>API key (optional)
        <input v-model="ctx.form.ai_api_key" type="password" autocomplete="new-password" :placeholder="apiKeyPlaceholder" />
      </label>
      <label>Fallback order (comma-separated provider ids)
        <input v-model="ctx.form.ai_fallback_order" type="text" placeholder="openai" />
      </label>

      <div class="actions">
        <button type="button" class="primary" :disabled="ctx.busy" @click="saveAi()">
          {{ ctx.busy ? 'Saving…' : 'Save AI settings' }}
        </button>
      </div>
    </div>
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
.card {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.25rem 1.35rem;
  border-radius: 14px;
  border: 1px solid var(--cdc-line, rgba(12, 26, 18, 0.08));
  background: var(--cdc-white, #fff);
  box-shadow: var(--cdc-shadow, 0 8px 24px rgba(6, 95, 44, 0.08));
}
label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}
.row {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}
input,
select {
  padding: 0.45rem 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.95rem;
}
.key-hint {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0;
}
.actions {
  margin-top: 0.35rem;
}
.primary {
  padding: 0.55rem 1.1rem;
  border-radius: 10px;
  border: none;
  background: linear-gradient(135deg, var(--cdc-green, #0d7a3a), var(--cdc-green-deep, #065f2c));
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
code {
  font-size: 0.85em;
}
</style>
