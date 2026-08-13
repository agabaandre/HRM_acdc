<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import { fetchPerformanceSettings, savePerformanceSettings } from '@/lib/settingsApi'

const tab = ref<'workflow' | 'general'>('general')
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const settings = reactive<Record<string, boolean | number | null>>({})
const workflowPreview = ref<Record<string, string[]>>({})
const windowStatuses = ref<
  Record<string, { open: boolean; label: string; message: string; opens_on?: string | null; closes_on?: string | null }>
>({})
const monthOptions = ref<Array<{ title: string; value: number | null }>>([])
const currentMonthLabel = ref('')
const currentYear = ref(new Date().getFullYear())
const help = ref<Record<string, string>>({})

const yearLabel = computed(() => String(currentYear.value))
const nextYearLabel = computed(() => String(currentYear.value + 1))

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await fetchPerformanceSettings()
    Object.keys(settings).forEach((k) => delete settings[k])
    Object.assign(settings, data.settings)
    if (settings.ppa_start == null) settings.ppa_start = 1
    workflowPreview.value = data.workflow_preview
    windowStatuses.value = data.window_statuses
    currentMonthLabel.value = data.current_month_label
    currentYear.value = data.current_year || new Date().getFullYear()
    help.value = data.help || {}
    monthOptions.value = [
      { title: '— None —', value: null },
      ...Object.entries(data.month_options).map(([k, v]) => ({
        title: v,
        value: Number(k),
      })),
    ]
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load performance settings')
  } finally {
    loading.value = false
  }
}

async function onSave() {
  saving.value = true
  success.value = null
  error.value = null
  try {
    await savePerformanceSettings({ ...settings })
    success.value = 'Performance & workflow settings saved.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save settings')
  } finally {
    saving.value = false
  }
}

onMounted(() => void load())
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-3">
      <CbpPageHeading
        title="Performance & workflows"
        subtitle="PPA variables: months in the current year, endterm into next year, and day overrides."
      />
      <RouterLink to="/settings" style="text-decoration: none">
        <v-btn variant="outlined" size="small">Settings home</v-btn>
      </RouterLink>
    </div>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else>
      <p class="text-body-2 text-medium-emphasis mb-3">
        Current month: <strong>{{ currentMonthLabel }} {{ yearLabel }}</strong>
        — deadlines use months only (no year picker).
      </p>
      <v-tabs v-model="tab" color="primary" class="mb-4">
        <v-tab value="general">Deadlines</v-tab>
        <v-tab value="workflow">Workflow</v-tab>
      </v-tabs>

      <v-card v-if="tab === 'workflow'" variant="outlined">
        <v-card-text>
          <v-checkbox v-model="settings.ppa_requires_second_supervisor" label="PPA requires second supervisor" hide-details />
          <v-checkbox
            v-model="settings.midterm_requires_second_supervisor"
            label="Midterm requires second supervisor"
            hide-details
          />
          <v-checkbox
            v-model="settings.endterm_requires_second_supervisor"
            label="Endterm requires second supervisor"
            hide-details
          />
          <v-checkbox
            v-model="settings.endterm_requires_employee_consent"
            label="Endterm requires employee consent"
            hide-details
          />

          <v-row class="mt-4">
            <v-col v-for="(steps, key) in workflowPreview" :key="key" cols="12" md="4">
              <h4 class="text-subtitle-1 text-primary text-uppercase mb-2">{{ key }}</h4>
              <ol class="ps-4">
                <li v-for="(step, i) in steps" :key="i" class="mb-1">{{ step }}</li>
              </ol>
              <v-alert
                v-if="windowStatuses[key]"
                class="mt-2 status-window-alert"
                density="compact"
                :type="windowStatuses[key].open ? 'success' : 'warning'"
                variant="tonal"
                prominent
              >
                {{ windowStatuses[key].message }}
              </v-alert>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <PortalBtn :loading="saving" @click="onSave">Save</PortalBtn>
        </v-card-actions>
      </v-card>

      <v-card v-else variant="outlined">
        <v-card-text>
          <h4 class="text-subtitle-1 text-primary mb-2">General flags</h4>
          <v-checkbox v-model="settings.allow_supervisor_return" label="Allow supervisor return" hide-details />
          <v-checkbox v-model="settings.allow_supervisor_comments" label="Allow supervisor comments" hide-details />
          <v-checkbox v-model="settings.allow_supervisor_ppa_edit" label="Allow supervisor PPA edit" hide-details />
          <v-checkbox v-model="settings.allow_employee_comments" label="Allow employee comments" hide-details />

          <h4 class="text-subtitle-1 text-primary mb-2 mt-6">Submission windows (months)</h4>
          <v-alert type="info" variant="tonal" density="compact" class="mb-4">
            {{ help.override || 'Override days keep submissions open past the last day of the deadline month.' }}
          </v-alert>

          <v-row>
            <v-col cols="12" md="4">
              <div class="text-subtitle-2 mb-2">PPA ({{ yearLabel }})</div>
              <p class="text-caption text-medium-emphasis mb-3">
                {{ help.ppa || `Opens each year from January through the deadline month in ${yearLabel}.` }}
              </p>
              <v-select
                v-model="settings.ppa_start"
                :items="monthOptions.filter((m) => m.value !== null)"
                label="Start month"
                hint="Defaults to January each new year"
                persistent-hint
                class="mb-2"
              />
              <v-select v-model="settings.ppa_deadline" :items="monthOptions" label="Deadline month" class="mb-2" />
              <v-text-field
                v-model.number="settings.ppa_deadline_override_days"
                type="number"
                min="0"
                max="365"
                label="Override days after deadline"
                hint="Extra days past the last day of the deadline month"
                persistent-hint
              />
              <v-alert
                v-if="windowStatuses.ppa"
                class="mt-3 status-window-alert"
                density="compact"
                :type="windowStatuses.ppa.open ? 'success' : 'warning'"
                variant="tonal"
                prominent
              >
                {{ windowStatuses.ppa.message }}
              </v-alert>
            </v-col>

            <v-col cols="12" md="4">
              <div class="text-subtitle-2 mb-2">Midterm</div>
              <p class="text-caption text-medium-emphasis mb-3">
                {{ help.midterm || 'Months in the current year; wrap if start is after end.' }}
              </p>
              <v-select v-model="settings.mid_term_start" :items="monthOptions" label="Start month" class="mb-2" />
              <v-select v-model="settings.mid_term_deadline" :items="monthOptions" label="Deadline month" class="mb-2" />
              <v-text-field
                v-model.number="settings.mid_term_deadline_override_days"
                type="number"
                min="0"
                max="365"
                label="Override days after deadline"
                hint="Extra days past the last day of the deadline month"
                persistent-hint
              />
              <v-alert
                v-if="windowStatuses.midterm"
                class="mt-3 status-window-alert"
                density="compact"
                :type="windowStatuses.midterm.open ? 'success' : 'warning'"
                variant="tonal"
                prominent
              >
                {{ windowStatuses.midterm.message }}
              </v-alert>
            </v-col>

            <v-col cols="12" md="4">
              <div class="text-subtitle-2 mb-2">Endterm ({{ yearLabel }} → {{ nextYearLabel }})</div>
              <p class="text-caption text-medium-emphasis mb-3">
                {{ help.endterm || `Starts in ${yearLabel} and ends in ${nextYearLabel}.` }}
              </p>
              <v-select v-model="settings.end_term_start" :items="monthOptions" label="Start month" class="mb-2" />
              <v-select
                v-model="settings.end_term_deadline"
                :items="monthOptions"
                label="Deadline month (next year)"
                class="mb-2"
              />
              <v-text-field
                v-model.number="settings.end_term_deadline_override_days"
                type="number"
                min="0"
                max="365"
                label="Override days after deadline"
                hint="Extra days past the last day of the deadline month"
                persistent-hint
              />
              <v-alert
                v-if="windowStatuses.endterm"
                class="mt-3 status-window-alert"
                density="compact"
                :type="windowStatuses.endterm.open ? 'success' : 'warning'"
                variant="tonal"
                prominent
              >
                {{ windowStatuses.endterm.message }}
              </v-alert>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <PortalBtn :loading="saving" @click="onSave">Save</PortalBtn>
        </v-card-actions>
      </v-card>
    </template>
  </div>
</template>

<style scoped>
.status-window-alert {
  font-size: 0.84rem;
  line-height: 1.35;
  font-weight: 550;
}
</style>
