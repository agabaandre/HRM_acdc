<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'

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
  mail_subject_prefix: 'HelpDesk',
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: EmailPayload }>('/api/v1/admin/settings/email')
    const d = data.data
    Object.assign(form, d.settings)
    Object.assign(status, d.status)
    portalUrl.value = d.portal_email_url
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not load email settings'))
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const { data } = await api.put<{ success: boolean; message: string }>('/api/v1/admin/settings/email', { ...form })
    notifySuccess(data.message || 'Email settings saved.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not save email settings'))
  } finally {
    saving.value = false
  }
}

async function sendTest() {
  const to = testTo.value.trim()
  if (!to) {
    notifyError('Enter a recipient email.')
    return
  }
  testing.value = true
  try {
    const { data } = await api.post<{ success: boolean; message: string }>('/api/v1/admin/settings/email/test', { to })
    if (data.success) notifySuccess(data.message)
    else notifyError(data.message || 'Send failed')
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Test send failed'))
  } finally {
    testing.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div v-if="loading" class="text-medium-emphasis">Loading email settings…</div>
  <div v-else class="hd-email-settings">
    <v-alert type="info" variant="tonal" class="mb-4">
      Helpdesk sends via the Staff Portal mail hub when
      <code>STAFF_MAIL_DISPATCH</code> is <strong>auto</strong> or <strong>portal</strong>.
      Configure providers in
      <a :href="portalUrl" target="_blank" rel="noopener">Staff Portal → Email settings</a>.
      <div class="text-caption mt-2">
        Share API: <code>{{ status.share_base || '—' }}</code>
        · Portal mail client: {{ status.portal_mail_client ? 'available' : 'missing' }}
      </div>
    </v-alert>

    <v-row class="mb-2" dense>
      <v-col cols="12" md="4">
        <v-card variant="outlined">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">HTTP</div>
            <div :class="status.http_configured ? 'text-success' : 'text-medium-emphasis'">
              {{ status.http_configured ? 'Configured' : 'Not set in .env' }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="4">
        <v-card variant="outlined">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">Exchange / Graph</div>
            <div :class="status.exchange_configured ? 'text-success' : 'text-medium-emphasis'">
              {{ status.exchange_configured ? 'Configured' : 'Not set in .env' }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="4">
        <v-card variant="outlined">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">SMTP</div>
            <div :class="status.smtp_configured ? 'text-success' : 'text-medium-emphasis'">
              {{ status.smtp_configured ? 'Configured' : 'Not set in .env' }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card variant="outlined" class="mb-4">
      <v-card-title class="text-subtitle-1">Dispatch &amp; local fallback</v-card-title>
      <v-card-text>
        <v-select
          v-model="form.staff_mail_dispatch"
          :items="[
            { title: 'auto — portal first, then local', value: 'auto' },
            { title: 'portal — Share /share/mail/send only', value: 'portal' },
            { title: 'local — MAIL_TRANSPORT only', value: 'local' },
          ]"
          label="STAFF_MAIL_DISPATCH"
          density="compact"
          class="mb-2"
        />
        <v-select
          v-model="form.mail_transport"
          :items="[
            { title: 'HTTP (notifications.africacdc.org)', value: 'http' },
            { title: 'Exchange / Microsoft Graph', value: 'exchange' },
            { title: 'SMTP', value: 'smtp' },
            { title: 'Zoho SMTP', value: 'zoho' },
            { title: 'Log only', value: 'log' },
          ]"
          label="MAIL_TRANSPORT (local fallback)"
          density="compact"
          class="mb-2"
        />
        <v-text-field v-model="form.mail_from_address" label="From address" density="compact" class="mb-2" />
        <v-text-field v-model="form.mail_from_name" label="From name" density="compact" class="mb-2" />
        <v-text-field v-model="form.mail_subject_prefix" label="Subject prefix" density="compact" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn color="primary" :loading="saving" @click="save">Save email settings</v-btn>
      </v-card-actions>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-subtitle-1">Send test email</v-card-title>
      <v-card-text>
        <v-text-field v-model="testTo" label="Recipient" type="email" density="compact" class="mb-2" />
        <v-btn color="primary" variant="outlined" :loading="testing" @click="sendTest">Send test</v-btn>
      </v-card-text>
    </v-card>
  </div>
</template>
