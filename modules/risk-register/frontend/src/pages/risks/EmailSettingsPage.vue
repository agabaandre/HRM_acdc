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
  mail_subject_prefix: 'Risk Register',
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
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>Email settings</h1>
        <p class="rr-page__sub">
          Outbound mail dispatch for Risk Register. Providers are managed in
          <a :href="portalUrl" target="_blank" rel="noopener">Staff Portal → Email settings</a>.
        </p>
      </div>
      <RouterLink class="rr-btn rr-btn--ghost" :to="{ name: 'risk-settings' }">Back to settings</RouterLink>
    </header>

    <p v-if="error" class="rr-error">{{ error }}</p>
    <p v-if="message" class="rr-ok">{{ message }}</p>
    <p v-if="loading" class="rr-muted">Loading…</p>

    <template v-else>
      <section class="rr-card">
        <p class="rr-muted">
          Share API: <code>{{ status.share_base || '—' }}</code>
          · Portal mail client: {{ status.portal_mail_client ? 'available' : 'missing' }}
        </p>
        <div class="rr-lookup-grid">
          <div class="rr-lookup-card">
            <div class="rr-lookup-card__title">HTTP</div>
            <div class="rr-lookup-card__count">{{ status.http_configured ? 'Configured' : 'Not set' }}</div>
          </div>
          <div class="rr-lookup-card">
            <div class="rr-lookup-card__title">Exchange</div>
            <div class="rr-lookup-card__count">{{ status.exchange_configured ? 'Configured' : 'Not set' }}</div>
          </div>
          <div class="rr-lookup-card">
            <div class="rr-lookup-card__title">SMTP</div>
            <div class="rr-lookup-card__count">{{ status.smtp_configured ? 'Configured' : 'Not set' }}</div>
          </div>
        </div>
      </section>

      <section class="rr-card">
        <h2>Dispatch &amp; local fallback</h2>
        <label class="rr-field">
          <span>STAFF_MAIL_DISPATCH</span>
          <select v-model="form.staff_mail_dispatch">
            <option value="auto">auto — portal first, then local</option>
            <option value="portal">portal — Share send only</option>
            <option value="local">local — MAIL_TRANSPORT only</option>
          </select>
        </label>
        <label class="rr-field">
          <span>MAIL_TRANSPORT</span>
          <select v-model="form.mail_transport">
            <option value="http">HTTP</option>
            <option value="exchange">Exchange</option>
            <option value="smtp">SMTP</option>
            <option value="zoho">Zoho</option>
            <option value="log">Log</option>
          </select>
        </label>
        <label class="rr-field">
          <span>From address</span>
          <input v-model="form.mail_from_address" type="email" />
        </label>
        <label class="rr-field">
          <span>From name</span>
          <input v-model="form.mail_from_name" type="text" />
        </label>
        <label class="rr-field">
          <span>Subject prefix</span>
          <input v-model="form.mail_subject_prefix" type="text" />
        </label>
        <button type="button" class="rr-btn" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save email settings' }}
        </button>
      </section>

      <section class="rr-card">
        <h2>Send test email</h2>
        <label class="rr-field">
          <span>Recipient</span>
          <input v-model="testTo" type="email" placeholder="you@africacdc.org" />
        </label>
        <button type="button" class="rr-btn" :disabled="testing" @click="sendTest">
          {{ testing ? 'Sending…' : 'Send test' }}
        </button>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 0.85rem;
}
.rr-field span {
  font-size: 0.85rem;
  font-weight: 600;
}
.rr-field input,
.rr-field select {
  border: 1px solid rgba(58, 71, 82, 0.2);
  border-radius: 0.45rem;
  padding: 0.5rem 0.65rem;
  font: inherit;
}
.rr-btn--ghost {
  text-decoration: none;
}
</style>
