<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

type EmailPayload = {
  settings: {
    staff_mail_dispatch: string
    mail_transport: string
    mail_from_address: string
    mail_from_name: string
    mail_subject_prefix: string
  }
  status: {
    exchange_configured: boolean
    http_configured: boolean
    smtp_configured: boolean
    share_base: string
    portal_mail_client: boolean
  }
  portal_email_url: string
}

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const testTo = ref('')
const portalUrl = ref('#')
const status = reactive({
  exchange_configured: false,
  http_configured: false,
  smtp_configured: false,
  share_base: '',
  portal_mail_client: false,
})
const form = reactive({
  staff_mail_dispatch: 'auto',
  mail_transport: 'exchange',
  mail_from_address: '',
  mail_from_name: '',
  mail_subject_prefix: 'Finance',
})

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: EmailPayload }>('/api/v1/settings/email')
    Object.assign(form, data.data.settings)
    Object.assign(status, data.data.status)
    portalUrl.value = data.data.portal_email_url
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load email settings')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put<{ success: boolean; message: string }>('/api/v1/settings/email', { ...form })
    message.value = data.message || 'Email settings saved.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save email settings')
  } finally {
    saving.value = false
  }
}

async function sendTest() {
  const to = testTo.value.trim()
  if (!to) {
    error.value = 'Enter a recipient email.'
    return
  }
  testing.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.post<{ success: boolean; message: string }>('/api/v1/settings/email/test', { to })
    if (data.success) message.value = data.message
    else error.value = data.message || 'Send failed'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Test send failed')
  } finally {
    testing.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="fn-page">
    <header class="fn-page__header">
      <div>
        <h1>Email settings</h1>
        <p class="fn-page__sub">
          Outbound mail dispatch for Finance. Providers are managed in
          <a :href="portalUrl" target="_blank" rel="noopener">Staff Portal → Email settings</a>.
        </p>
      </div>
      <RouterLink class="fn-link" :to="{ name: 'dashboard' }">Back to portfolio</RouterLink>
    </header>

    <p v-if="error" class="fn-error">{{ error }}</p>
    <p v-if="message" class="fn-ok">{{ message }}</p>
    <p v-if="loading" class="fn-muted">Loading…</p>

    <template v-else>
      <section class="fn-card">
        <p class="fn-muted">
          Share API: <code>{{ status.share_base || '—' }}</code>
          · Portal mail client: {{ status.portal_mail_client ? 'available' : 'missing' }}
        </p>
        <div class="fn-status-grid">
          <div class="fn-status">
            <strong>HTTP</strong>
            <span>{{ status.http_configured ? 'Configured' : 'Not set' }}</span>
          </div>
          <div class="fn-status">
            <strong>Exchange</strong>
            <span>{{ status.exchange_configured ? 'Configured' : 'Not set' }}</span>
          </div>
          <div class="fn-status">
            <strong>SMTP</strong>
            <span>{{ status.smtp_configured ? 'Configured' : 'Not set' }}</span>
          </div>
        </div>
      </section>

      <section class="fn-card">
        <h2>Dispatch &amp; local fallback</h2>
        <label class="fn-field">
          <span>STAFF_MAIL_DISPATCH</span>
          <select v-model="form.staff_mail_dispatch">
            <option value="auto">auto — portal first, then local</option>
            <option value="portal">portal — Share send only</option>
            <option value="local">local — MAIL_TRANSPORT only</option>
          </select>
        </label>
        <label class="fn-field">
          <span>MAIL_TRANSPORT</span>
          <select v-model="form.mail_transport">
            <option value="http">HTTP</option>
            <option value="exchange">Exchange</option>
            <option value="smtp">SMTP</option>
            <option value="zoho">Zoho</option>
            <option value="log">Log</option>
          </select>
        </label>
        <label class="fn-field">
          <span>From address</span>
          <input v-model="form.mail_from_address" type="email" />
        </label>
        <label class="fn-field">
          <span>From name</span>
          <input v-model="form.mail_from_name" type="text" />
        </label>
        <label class="fn-field">
          <span>Subject prefix</span>
          <input v-model="form.mail_subject_prefix" type="text" />
        </label>
        <button type="button" class="fn-btn" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save email settings' }}
        </button>
      </section>

      <section class="fn-card">
        <h2>Send test email</h2>
        <label class="fn-field">
          <span>Recipient</span>
          <input v-model="testTo" type="email" placeholder="you@africacdc.org" />
        </label>
        <button type="button" class="fn-btn" :disabled="testing" @click="sendTest">
          {{ testing ? 'Sending…' : 'Send test' }}
        </button>
      </section>
    </template>
  </div>
</template>

<style scoped>
.fn-page { max-width: 920px; margin: 0 auto; padding: 1rem 1.25rem 2rem; }
.fn-page__header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.fn-page__sub { color: #64748b; margin: 0.35rem 0 0; }
.fn-card { background: #fff; border: 1px solid rgba(58,71,82,.12); border-radius: .75rem; padding: 1rem 1.15rem; margin-bottom: 1rem; }
.fn-status-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: .75rem; }
.fn-status { border: 1px solid rgba(58,71,82,.1); border-radius: .5rem; padding: .75rem; display: flex; flex-direction: column; gap: .25rem; }
.fn-field { display: flex; flex-direction: column; gap: .35rem; margin-bottom: .85rem; }
.fn-field span { font-size: .85rem; font-weight: 600; }
.fn-field input, .fn-field select { border: 1px solid rgba(58,71,82,.2); border-radius: .45rem; padding: .5rem .65rem; font: inherit; }
.fn-btn { background: #0d7a3a; color: #fff; border: 0; border-radius: .45rem; padding: .55rem 1rem; font-weight: 600; cursor: pointer; }
.fn-btn:disabled { opacity: .6; cursor: wait; }
.fn-link { color: #0d7a3a; font-weight: 600; text-decoration: none; }
.fn-error { color: #b91c1c; }
.fn-ok { color: #0d7a3a; }
.fn-muted { color: #64748b; }
@media (max-width: 720px) {
  .fn-page__header { flex-direction: column; }
  .fn-status-grid { grid-template-columns: 1fr; }
}
</style>
