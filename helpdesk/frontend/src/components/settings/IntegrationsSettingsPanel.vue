<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'

interface SettingsPayload {
  whatsapp_enabled?: boolean | string
  whatsapp_phone_number_id?: string | null
  whatsapp_verify_token?: string | null
  whatsapp_access_token?: string
  whatsapp_access_token_configured?: boolean
  whatsapp_app_secret?: string
  whatsapp_app_secret_configured?: boolean
  teams_enabled?: boolean | string
  teams_app_id?: string | null
  teams_tenant_id?: string | null
  teams_messaging_path?: string | null
  teams_app_password?: string
  teams_app_password_configured?: boolean
  webhook_base_url?: string
}

const err = ref<string | null>(null)
const ok = ref<string | null>(null)
const busy = ref(false)

const form = reactive({
  whatsapp_enabled: false,
  whatsapp_phone_number_id: '',
  whatsapp_verify_token: '',
  whatsapp_access_token: '',
  whatsapp_app_secret: '',
  teams_enabled: false,
  teams_app_id: '',
  teams_tenant_id: '',
  teams_messaging_path: 'activities',
  teams_app_password: '',
})

const flags = reactive({
  waTok: false,
  waSec: false,
  teamsPwd: false,
})

const webhookBase = ref('')

async function load() {
  err.value = null
  try {
    const { data } = await api.get<{ data: SettingsPayload }>('/api/v1/admin/settings')
    const d = data.data
    form.whatsapp_enabled = Boolean(d.whatsapp_enabled)
    form.whatsapp_phone_number_id = d.whatsapp_phone_number_id ?? ''
    form.whatsapp_verify_token = d.whatsapp_verify_token ?? ''
    form.whatsapp_access_token = ''
    form.whatsapp_app_secret = ''
    form.teams_enabled = Boolean(d.teams_enabled)
    form.teams_app_id = d.teams_app_id ?? ''
    form.teams_tenant_id = d.teams_tenant_id ?? ''
    form.teams_messaging_path = d.teams_messaging_path ?? 'activities'
    form.teams_app_password = ''
    flags.waTok = Boolean(d.whatsapp_access_token_configured)
    flags.waSec = Boolean(d.whatsapp_app_secret_configured)
    flags.teamsPwd = Boolean(d.teams_app_password_configured)
    webhookBase.value = d.webhook_base_url ?? ''
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to load integration settings.')
  }
}

async function save() {
  busy.value = true
  err.value = null
  ok.value = null
  try {
    const payload: Record<string, unknown> = {
      whatsapp_enabled: form.whatsapp_enabled,
      whatsapp_phone_number_id: form.whatsapp_phone_number_id || null,
      whatsapp_verify_token: form.whatsapp_verify_token || null,
      teams_enabled: form.teams_enabled,
      teams_app_id: form.teams_app_id || null,
      teams_tenant_id: form.teams_tenant_id || null,
      teams_messaging_path: form.teams_messaging_path || null,
    }
    if (form.whatsapp_access_token.trim()) {
      payload.whatsapp_access_token = form.whatsapp_access_token.trim()
    }
    if (form.whatsapp_app_secret.trim()) {
      payload.whatsapp_app_secret = form.whatsapp_app_secret.trim()
    }
    if (form.teams_app_password.trim()) {
      payload.teams_app_password = form.teams_app_password.trim()
    }
    await api.put('/api/v1/admin/settings', payload)
    ok.value = 'Integration settings saved.'
    await load()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Save failed.')
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="wrap">
    <p class="intro">
      Configure inbound channels so requesters can open tickets from <strong>WhatsApp</strong> and <strong>Microsoft Teams</strong>. Webhook endpoints follow vendor
      specifications; ticket creation from raw channel events is completed in a follow-up release — this screen stores credentials and exposes stable URLs for
      Meta / Azure registration.
    </p>
    <p v-if="err" class="err">{{ err }}</p>
    <p v-if="ok" class="ok">{{ ok }}</p>

    <section class="card">
      <h3>Webhook base URL</h3>
      <p class="mono">{{ webhookBase || '—' }}</p>
    </section>

    <section class="card">
      <h3>WhatsApp Cloud API</h3>
      <p class="doc">
        Official reference:
        <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/overview" rel="noopener noreferrer" target="_blank">WhatsApp Cloud API overview</a>
        ·
        <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components" rel="noopener noreferrer" target="_blank">Webhooks</a>
      </p>
      <label class="row-check">
        <input v-model="form.whatsapp_enabled" type="checkbox" />
        Enable WhatsApp channel (webhook registration)
      </label>
      <label>Phone number ID
        <input v-model="form.whatsapp_phone_number_id" type="text" autocomplete="off" placeholder="from Meta Business Suite" />
      </label>
      <label>Verify token (webhook challenge)
        <input v-model="form.whatsapp_verify_token" type="text" autocomplete="off" />
      </label>
      <p class="ep">
        <strong>GET</strong> verification URL:
        <code>{{ webhookBase }}/whatsapp</code>
        (Meta sends <code>hub.mode</code>, <code>hub.verify_token</code>, <code>hub.challenge</code>.)
      </p>
      <p class="ep"><strong>POST</strong> inbound URL: <code>{{ webhookBase }}/whatsapp</code></p>
      <label>Permanent access token (stored encrypted)
        <input v-model="form.whatsapp_access_token" type="password" autocomplete="new-password" placeholder="leave blank to keep current" />
      </label>
      <p v-if="flags.waTok" class="flag">Access token is on file.</p>
      <label>App secret (stored encrypted — for <code>X-Hub-Signature-256</code> verification)
        <input v-model="form.whatsapp_app_secret" type="password" autocomplete="new-password" placeholder="leave blank to keep current" />
      </label>
      <p v-if="flags.waSec" class="flag">App secret is on file.</p>
    </section>

    <section class="card">
      <h3>Microsoft Teams (Azure Bot Service)</h3>
      <p class="doc">
        Official reference:
        <a href="https://learn.microsoft.com/en-us/azure/bot-service/bot-service-overview-introduction" rel="noopener noreferrer" target="_blank">Azure Bot Service</a>
        ·
        <a href="https://learn.microsoft.com/en-us/azure/bot-service/rest-api/bot-framework-rest-connector-api-reference" rel="noopener noreferrer" target="_blank">Bot Framework REST</a>
      </p>
      <label class="row-check">
        <input v-model="form.teams_enabled" type="checkbox" />
        Enable Teams bot endpoint (registration)
      </label>
      <label>Microsoft App ID
        <input v-model="form.teams_app_id" type="text" autocomplete="off" />
      </label>
      <label>Directory (tenant) ID
        <input v-model="form.teams_tenant_id" type="text" autocomplete="off" />
      </label>
      <label>Messaging path segment (appended to webhook base; default <code>activities</code>)
        <input v-model="form.teams_messaging_path" type="text" autocomplete="off" />
      </label>
      <p class="ep">
        <strong>POST</strong> messaging URL:
        <code>{{ webhookBase }}/teams/{{ form.teams_messaging_path || 'activities' }}</code>
      </p>
      <label>Client secret / app password (stored encrypted)
        <input v-model="form.teams_app_password" type="password" autocomplete="new-password" placeholder="leave blank to keep current" />
      </label>
      <p v-if="flags.teamsPwd" class="flag">Bot password is on file.</p>
    </section>

    <button type="button" class="primary" :disabled="busy" @click="save()">{{ busy ? 'Saving…' : 'Save integrations' }}</button>
  </div>
</template>

<style scoped>
.wrap {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.intro {
  font-size: 0.88rem;
  color: #475569;
  line-height: 1.55;
  margin: 0;
}
.card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1rem 1.1rem;
  background: #fafafa;
}
.card h3 {
  margin: 0 0 0.5rem;
  font-size: 0.98rem;
  color: #1e293b;
}
.doc {
  font-size: 0.82rem;
  margin: 0 0 0.75rem;
}
.doc a {
  color: #119a48;
  font-weight: 600;
}
label {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #334155;
  margin-bottom: 0.65rem;
}
.row-check {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}
input[type='text'],
input[type='password'] {
  padding: 0.4rem 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.9rem;
}
.mono,
.ep code {
  font-size: 0.82rem;
  word-break: break-all;
}
.ep {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0.35rem 0 0.75rem;
}
.flag {
  font-size: 0.78rem;
  color: #166534;
  margin: -0.35rem 0 0.5rem;
}
.primary {
  align-self: flex-start;
  padding: 0.55rem 1.1rem;
  border-radius: 8px;
  border: none;
  background: linear-gradient(135deg, #119a48, #0d7a3a);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.err {
  color: #b91c1c;
  font-weight: 600;
}
.ok {
  color: #166534;
  font-weight: 600;
}
</style>
