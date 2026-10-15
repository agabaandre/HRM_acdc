<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { notifyError, notifySuccess } from '@/lib/notify'

interface AlertSettings {
  enabled: boolean
  interval_days: number
  intervals: number[]
  last_run_at?: string | null
}

interface SendResult {
  enabled: boolean
  checked: number
  sent: number
  skipped: number
  errors: string[]
}

const loading = ref(true)
const saving = ref(false)
const sending = ref(false)
const draftDays = ref<number | null>(7)
const form = reactive<AlertSettings>({
  enabled: true,
  interval_days: 7,
  intervals: [1, 3, 7],
  last_run_at: null,
})

const intervalItems = computed(() =>
  form.intervals.map((days) => ({
    label: days === 1 ? 'Every day' : `Every ${days} days`,
    value: days,
  })),
)

const lastRunLabel = computed(() => {
  if (!form.last_run_at) return 'Not run yet'
  const date = new Date(form.last_run_at)
  return Number.isNaN(date.getTime()) ? form.last_run_at : date.toLocaleString()
})

function apply(data: AlertSettings) {
  form.enabled = data.enabled
  form.interval_days = data.interval_days
  form.intervals = [...data.intervals].sort((a, b) => a - b)
  form.last_run_at = data.last_run_at ?? null
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: AlertSettings }>('/api/v1/settings/license-alerts')
    apply(data.data)
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load reminder settings.'))
  } finally {
    loading.value = false
  }
}

function addInterval() {
  const days = Number(draftDays.value)
  if (!Number.isInteger(days) || days < 1 || days > 365) {
    notifyError('Enter a reminder interval between 1 and 365 days.')
    return
  }
  if (!form.intervals.includes(days)) {
    form.intervals = [...form.intervals, days].sort((a, b) => a - b)
  }
  form.interval_days = days
  draftDays.value = null
}

function removeInterval(days: number) {
  if (form.intervals.length < 2) {
    notifyError('Keep at least one reminder interval.')
    return
  }
  form.intervals = form.intervals.filter((item) => item !== days)
  if (!form.intervals.includes(form.interval_days)) {
    form.interval_days = form.intervals[0]
  }
}

async function save() {
  saving.value = true
  try {
    const { data } = await api.put<{ data: AlertSettings }>('/api/v1/settings/license-alerts', {
      enabled: form.enabled,
      interval_days: form.interval_days,
      intervals: form.intervals,
    })
    apply(data.data)
    notifySuccess('Reminder settings saved.')
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to save reminder settings.'))
  } finally {
    saving.value = false
  }
}

async function sendNow() {
  sending.value = true
  try {
    const { data } = await api.post<{ data: SendResult }>('/api/v1/settings/license-alerts/send-now')
    const result = data.data
    if (!result.enabled) {
      notifyError('Turn reminders on before sending.')
      return
    }
    const detail = result.errors.length ? ` ${result.errors[0]}` : ''
    notifySuccess(`Sent ${result.sent} reminder(s). Skipped ${result.skipped}.${detail}`)
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to send reminders.'))
  } finally {
    sending.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <CbpPageHeading title="Settings" back-to="/" back-label="← Overview">
      <template #lede>
        Configure how often license expiry reminders are emailed to each license’s responsible person.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <p v-if="loading" class="muted">Loading settings…</p>
      <UCard v-else class="form-panel">
        <template #header>
          <div class="form-header">
            <h3>License expiry reminders</h3>
            <p class="form-intro">
              A license is included once it is inside its own warning window, or after it has expired.
              The active interval is how often that email is sent again until the license is renewed.
              Set each license’s warning window on the Licenses form.
            </p>
          </div>
        </template>

        <label class="toggle">
          <input v-model="form.enabled" type="checkbox" />
          <span>
            <strong>Send expiry reminders</strong>
            <span class="hint">Daily at 09:00 when the scheduler is running. You can also send the reminders that are due now.</span>
          </span>
        </label>

        <div class="intervals">
          <span class="label">Reminder intervals</span>
          <div class="chips">
            <span v-for="days in form.intervals" :key="days" class="chip" :class="{ active: days === form.interval_days }">
              {{ days === 1 ? 'Every day' : `Every ${days} days` }}
              <button type="button" class="chip-remove" :aria-label="`Remove ${days} day interval`" @click="removeInterval(days)">×</button>
            </span>
          </div>
          <div class="add-row">
            <UFormField label="Days" class="days-field">
              <UInput v-model.number="draftDays" type="number" min="1" max="365" class="w-full" />
            </UFormField>
            <UButton color="neutral" variant="outline" class="add-btn" @click="addInterval">Add interval</UButton>
          </div>
        </div>

        <UFormField label="Active reminder interval" class="active-field">
          <USelect v-model="form.interval_days" :items="intervalItems" :clearable="false" class="w-full" />
        </UFormField>

        <p class="last-run">Last run: {{ lastRunLabel }}</p>

        <div class="form-actions">
          <UButton color="neutral" variant="outline" :loading="sending" :disabled="saving" @click="sendNow">
            Send due reminders
          </UButton>
          <UButton color="primary" :loading="saving" :disabled="sending" @click="save">Save settings</UButton>
        </div>
      </UCard>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; max-width: 760px; }
.form-panel { border-style: solid; }
.form-header h3 { margin: 0; font-size: 1rem; }
.form-intro { margin: 0.35rem 0 0; color: #64748b; font-size: 0.85rem; }
.toggle { display: flex; gap: 0.75rem; align-items: flex-start; margin: 0.5rem 0 1rem; }
.toggle strong { display: block; }
.hint, .last-run, .muted { color: #64748b; font-size: 0.85rem; }
.intervals { display: flex; flex-direction: column; gap: 0.55rem; margin-bottom: 1rem; }
.label { font-size: 0.78rem; font-weight: 700; color: #334155; }
.chips { display: flex; flex-wrap: wrap; gap: 0.45rem; }
.chip {
  display: inline-flex; align-items: center; gap: 0.35rem;
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px;
  padding: 0.25rem 0.45rem 0.25rem 0.7rem; font-size: 0.82rem; font-weight: 600;
}
.chip.active { background: #f0fdf4; border-color: #86efac; color: #166534; }
.chip-remove {
  border: 0; background: transparent; cursor: pointer; color: #64748b;
  font-size: 1rem; line-height: 1; padding: 0 0.2rem;
}
.add-row { display: flex; gap: 0.6rem; align-items: flex-end; flex-wrap: wrap; }
.days-field { width: 8rem; }
.add-btn, .form-actions { margin-bottom: 0.15rem; }
.active-field { max-width: 18rem; }
.form-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem; flex-wrap: wrap; }
</style>
