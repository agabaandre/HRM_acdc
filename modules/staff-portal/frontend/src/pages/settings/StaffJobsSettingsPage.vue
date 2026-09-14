<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import {
  fetchStaffJobsSettings,
  runStaffJob,
  saveStaffJobsSettings,
  type StaffJobsDailyMeta,
  type StaffJobsInstantJob,
  type StaffJobsScheduleSpec,
} from '@/lib/settingsApi'

const loading = ref(false)
const saving = ref(false)
const runningKey = ref<string | null>(null)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const schedulePath = ref('application/cache/staff_jobs_schedule.json')
const dailyMeta = ref<Record<string, StaffJobsDailyMeta>>({})
const instantJobs = ref<StaffJobsInstantJob[]>([])

const form = reactive({
  send_instant_mails: true,
  send_mails_interval_minutes: 15,
  manage_accounts_hourly_minute: '' as string | number,
})

type DailyRow = {
  key: string
  enabled: boolean
  hour: number
  minute: number
  weekday: number
}

const dailyRows = ref<DailyRow[]>([])

const hourOptions = Array.from({ length: 24 }, (_, i) => ({
  title: String(i).padStart(2, '0'),
  value: i,
}))
const minuteOptions = Array.from({ length: 60 }, (_, i) => ({
  title: String(i).padStart(2, '0'),
  value: i,
}))
const weekdayOptions = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
].map((title, value) => ({ title, value }))

const manageMinuteOptions = computed(() => [
  { title: 'Disabled', value: '' },
  ...minuteOptions.map((o) => ({ title: o.title, value: o.value })),
])

function applySchedule(schedule: Record<string, StaffJobsScheduleSpec>) {
  form.send_instant_mails = Boolean(schedule.send_instant_mails)
  form.send_mails_interval_minutes = Number(schedule.send_mails_interval_minutes ?? 0)
  const mah = schedule.manage_accounts_hourly_minute
  form.manage_accounts_hourly_minute =
    mah === null || mah === undefined || mah === false ? '' : Number(mah)

  dailyRows.value = Object.entries(dailyMeta.value).map(([key]) => {
    const spec = schedule[key]
    const enabled = typeof spec === 'object' && spec !== null && !Array.isArray(spec)
    const row = enabled ? (spec as { hour?: number; minute?: number; weekday?: number }) : null
    return {
      key,
      enabled,
      hour: row?.hour ?? 8,
      minute: row?.minute ?? 0,
      weekday: row?.weekday ?? 2,
    }
  })
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await fetchStaffJobsSettings()
    schedulePath.value = data.schedule_path
    dailyMeta.value = data.daily_jobs_meta
    instantJobs.value = data.instant_jobs
    applySchedule(data.schedule)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load staff jobs settings')
  } finally {
    loading.value = false
  }
}

function buildPayload(): Record<string, unknown> {
  const payload: Record<string, unknown> = {
    send_instant_mails: form.send_instant_mails,
    send_mails_interval_minutes: form.send_mails_interval_minutes,
    manage_accounts_hourly_minute: form.manage_accounts_hourly_minute,
  }
  for (const row of dailyRows.value) {
    payload[`${row.key}_enabled`] = row.enabled
    payload[`${row.key}_hour`] = row.hour
    payload[`${row.key}_minute`] = row.minute
    if (dailyMeta.value[row.key]?.weekday_select) {
      payload[`${row.key}_weekday`] = row.weekday
    }
  }
  return payload
}

async function onSave() {
  saving.value = true
  success.value = null
  error.value = null
  try {
    const res = await saveStaffJobsSettings(buildPayload())
    success.value = res.message || 'Schedule saved.'
    applySchedule(res.data.schedule)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save schedule')
  } finally {
    saving.value = false
  }
}

async function onRun(jobKey: string) {
  runningKey.value = jobKey
  success.value = null
  error.value = null
  try {
    const res = await runStaffJob(jobKey)
    success.value = res.message
    if (res.data?.output) {
      success.value += ` — ${res.data.output.slice(0, 240)}`
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Job failed')
  } finally {
    runningKey.value = null
  }
}

onMounted(() => void load())
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-3 flex-wrap ga-2">
      <CbpPageHeading
        title="Staff jobs"
        subtitle="Adjust cron times used by the Laravel scheduler and run jobs once."
      />
      <RouterLink to="/settings" style="text-decoration: none">
        <PortalBtn variant="outlined" color="secondary">Back to settings</PortalBtn>
      </RouterLink>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>

    <v-progress-linear v-if="loading" indeterminate class="mb-3" />

    <v-row v-else>
      <v-col cols="12" lg="7">
        <v-card variant="outlined">
          <v-card-title class="text-h6">Cron schedule</v-card-title>
          <v-card-subtitle>
            Saved to <code>{{ schedulePath }}</code> (shared with CI3).
          </v-card-subtitle>
          <v-card-text class="d-flex flex-column ga-4">
            <v-checkbox
              v-model="form.send_instant_mails"
              label="Run instant mail queue every minute"
              hide-details
              density="compact"
            />
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="form.send_mails_interval_minutes"
                  type="number"
                  min="0"
                  max="1440"
                  label="Full mail queue interval (minutes)"
                  hint="0 = disabled"
                  persistent-hint
                  variant="outlined"
                  density="comfortable"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.manage_accounts_hourly_minute"
                  :items="manageMinuteOptions"
                  item-title="title"
                  item-value="value"
                  label="Manage accounts (hourly at minute)"
                  variant="outlined"
                  density="comfortable"
                />
              </v-col>
            </v-row>

            <div class="text-subtitle-2 text-medium-emphasis">Daily / weekly jobs (server local time)</div>

            <v-sheet
              v-for="row in dailyRows"
              :key="row.key"
              border
              rounded
              class="pa-3"
            >
              <v-checkbox
                v-model="row.enabled"
                :label="dailyMeta[row.key]?.label || row.key"
                hide-details
                density="compact"
              />
              <p class="text-caption text-medium-emphasis mb-2 mt-1">
                {{ dailyMeta[row.key]?.help }}
              </p>
              <v-row dense>
                <v-col v-if="dailyMeta[row.key]?.weekday_select" cols="4">
                  <v-select
                    v-model="row.weekday"
                    :items="weekdayOptions"
                    item-title="title"
                    item-value="value"
                    label="Day"
                    variant="outlined"
                    density="compact"
                    hide-details
                  />
                </v-col>
                <v-col cols="4">
                  <v-select
                    v-model="row.hour"
                    :items="hourOptions"
                    item-title="title"
                    item-value="value"
                    label="Hour"
                    variant="outlined"
                    density="compact"
                    hide-details
                  />
                </v-col>
                <v-col cols="4">
                  <v-select
                    v-model="row.minute"
                    :items="minuteOptions"
                    item-title="title"
                    item-value="value"
                    label="Minute"
                    variant="outlined"
                    density="compact"
                    hide-details
                  />
                </v-col>
              </v-row>
            </v-sheet>

            <div>
              <PortalBtn color="primary" :loading="saving" @click="onSave">Save schedule</PortalBtn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="5">
        <v-card variant="outlined">
          <v-card-title class="text-h6">Run now</v-card-title>
          <v-card-subtitle>Executes the Laravel job once (Artisan).</v-card-subtitle>
          <v-list lines="two">
            <v-list-item v-for="job in instantJobs" :key="job.key">
              <v-list-item-title>{{ job.label }}</v-list-item-title>
              <template #append>
                <PortalBtn
                  size="small"
                  variant="outlined"
                  color="primary"
                  :loading="runningKey === job.key"
                  :disabled="runningKey !== null && runningKey !== job.key"
                  @click="onRun(job.key)"
                >
                  Run
                </PortalBtn>
              </template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
