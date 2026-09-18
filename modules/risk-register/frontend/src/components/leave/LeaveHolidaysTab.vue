<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import {
  deleteHolidayRule,
  fetchHolidayDutyStations,
  fetchHolidayPreview,
  fetchHolidayRules,
  fetchIndependenceRows,
  fetchOpenHolidaysCountries,
  fetchOpenHolidaysPreview,
  importOpenHolidays,
  saveHolidayDutyStations,
  saveHolidayRule,
  saveIndependenceRows,
  type LeaveHolidayRuleDto,
} from '@/lib/leaveApi'

const emit = defineEmits<{
  (e: 'status', payload: { success?: string | null; error?: string | null }): void
}>()

const loading = ref(false)
const saving = ref(false)
const rules = ref<LeaveHolidayRuleDto[]>([])
const previewYear = ref(new Date().getFullYear())
const previewIso = ref('ET')
const previewRows = ref<Array<{ date: string; name: string }>>([])
const ohIso = ref('ZA')
const ohYear = ref(new Date().getFullYear())
const ohCountries = ref<Array<{ iso: string; name: string }>>([])
const ohPreview = ref<Array<{ name: string; start_date: string; recurrence: string; is_movable: boolean }>>([])
const independence = ref<
  Array<{ nationality_id: number; nationality: string; iso2?: string | null; independence_month?: number | null; independence_day?: number | null }>
>([])
const stations = ref<
  Array<{ duty_station_id: number; duty_station_name: string; country?: string | null; country_iso2?: string | null }>
>([])

const editingId = ref<number | null>(null)
const form = reactive({
  name: '',
  recurrence: 'yearly_md',
  month: 1,
  day: 1,
  once_date: '',
  scope: 'country',
  country_iso2: 'ET',
  grants_compensatory_if_weekend: true,
  is_movable: false,
  is_active: true,
})

function resetForm() {
  editingId.value = null
  form.name = ''
  form.recurrence = 'yearly_md'
  form.month = 1
  form.day = 1
  form.once_date = ''
  form.scope = 'country'
  form.country_iso2 = 'ET'
  form.grants_compensatory_if_weekend = true
  form.is_movable = false
  form.is_active = true
}

function editRule(rule: LeaveHolidayRuleDto) {
  editingId.value = rule.id
  form.name = rule.name
  form.recurrence = rule.recurrence
  form.month = rule.month || 1
  form.day = rule.day || 1
  form.once_date = rule.once_date || ''
  form.scope = rule.scope
  form.country_iso2 = rule.country_iso2 || 'ET'
  form.grants_compensatory_if_weekend = rule.grants_compensatory_if_weekend
  form.is_movable = rule.is_movable
  form.is_active = rule.is_active
}

async function load() {
  loading.value = true
  emit('status', { error: null })
  try {
    const [r, ind, st] = await Promise.all([
      fetchHolidayRules(),
      fetchIndependenceRows(),
      fetchHolidayDutyStations(),
    ])
    rules.value = r
    independence.value = ind
    stations.value = st
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'Could not load holidays') })
  } finally {
    loading.value = false
  }
}

async function onSaveRule() {
  saving.value = true
  emit('status', { success: null, error: null })
  try {
    await saveHolidayRule(
      {
        name: form.name,
        recurrence: form.recurrence,
        month: form.recurrence === 'yearly_md' ? form.month : null,
        day: form.recurrence === 'yearly_md' ? form.day : null,
        once_date: form.recurrence === 'once' ? form.once_date : null,
        scope: form.scope,
        country_iso2: form.scope === 'country' ? form.country_iso2 : null,
        grants_compensatory_if_weekend: form.grants_compensatory_if_weekend,
        is_movable: form.is_movable,
        is_active: form.is_active,
      },
      editingId.value,
    )
    emit('status', { success: editingId.value ? 'Holiday rule updated.' : 'Holiday rule created.' })
    resetForm()
    rules.value = await fetchHolidayRules()
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'Could not save holiday rule') })
  } finally {
    saving.value = false
  }
}

async function onDelete(id: number) {
  if (!confirm('Delete this holiday rule?')) return
  saving.value = true
  try {
    await deleteHolidayRule(id)
    emit('status', { success: 'Holiday rule deleted.' })
    rules.value = await fetchHolidayRules()
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'Could not delete holiday rule') })
  } finally {
    saving.value = false
  }
}

async function onPreview() {
  try {
    const data = await fetchHolidayPreview({
      year: previewYear.value,
      country_iso2: previewIso.value,
    })
    previewRows.value = data.holidays
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'Could not preview calendar') })
  }
}

async function onLoadOhCountries() {
  try {
    ohCountries.value = await fetchOpenHolidaysCountries()
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'OpenHolidays countries unavailable (Africa coverage is limited).') })
  }
}

async function onOhPreview() {
  try {
    ohPreview.value = await fetchOpenHolidaysPreview(ohIso.value, ohYear.value)
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'OpenHolidays preview failed') })
  }
}

async function onOhImport() {
  saving.value = true
  try {
    const result = await importOpenHolidays(ohIso.value, ohYear.value)
    emit('status', { success: `Imported ${result.created} holiday(s); skipped ${result.skipped}.` })
    rules.value = await fetchHolidayRules()
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'OpenHolidays import failed') })
  } finally {
    saving.value = false
  }
}

async function onSaveIndependence() {
  saving.value = true
  try {
    await saveIndependenceRows(
      independence.value.map((row) => ({
        nationality_id: row.nationality_id,
        independence_month: row.independence_month ? Number(row.independence_month) : null,
        independence_day: row.independence_day ? Number(row.independence_day) : null,
      })),
    )
    emit('status', { success: 'Independence dates saved.' })
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'Could not save independence dates') })
  } finally {
    saving.value = false
  }
}

async function onSaveStations() {
  saving.value = true
  try {
    await saveHolidayDutyStations(
      stations.value.map((row) => ({
        duty_station_id: row.duty_station_id,
        country_iso2: row.country_iso2 || null,
      })),
    )
    emit('status', { success: 'Duty station countries saved.' })
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'Could not save duty station ISO codes') })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
  void onLoadOhCountries()
})
</script>

<template>
  <div>
    <p class="text-body-2 text-medium-emphasis mb-4">
      Shared holidays (Christmas, New Year, Labour Day, Africa Day, AU Day) are stored once globally.
      Country holidays use ISO2 (Ethiopia = ET). Staff calendars add the nationality independence day.
      Weekend holidays grant holiday compensatory (max 15 days, unused days forfeit 31 Dec).
      OpenHolidays covers few African countries today — Ethiopia HQ dates are seeded; edit Eid/Mawlid each year.
    </p>

    <v-row>
      <v-col cols="12" lg="5">
        <v-card variant="outlined">
          <v-card-title>{{ editingId ? 'Edit holiday' : 'Add holiday' }}</v-card-title>
          <v-card-text>
            <v-text-field v-model="form.name" label="Name" />
            <v-select
              v-model="form.recurrence"
              :items="[
                { title: 'Every year (month/day)', value: 'yearly_md' },
                { title: 'Once (specific date)', value: 'once' },
              ]"
              label="Recurrence"
            />
            <v-row v-if="form.recurrence === 'yearly_md'" dense>
              <v-col cols="6"><v-text-field v-model.number="form.month" type="number" min="1" max="12" label="Month" /></v-col>
              <v-col cols="6"><v-text-field v-model.number="form.day" type="number" min="1" max="31" label="Day" /></v-col>
            </v-row>
            <v-text-field v-else v-model="form.once_date" type="date" label="Date" />
            <v-select
              v-model="form.scope"
              :items="[
                { title: 'Global (all staff)', value: 'global' },
                { title: 'Country (ISO2)', value: 'country' },
                { title: 'Duty station only', value: 'duty_station' },
              ]"
              label="Scope"
            />
            <v-text-field v-if="form.scope === 'country'" v-model="form.country_iso2" label="Country ISO2" maxlength="2" hint="ET, KE, ZA…" persistent-hint />
            <v-checkbox v-model="form.grants_compensatory_if_weekend" label="Grant holiday compensatory if this day falls on a weekend" hide-details />
            <v-checkbox v-model="form.is_movable" label="Movable (HR must confirm the date each year)" hide-details />
            <v-checkbox v-model="form.is_active" label="Active" hide-details />
          </v-card-text>
          <v-card-actions>
            <PortalBtn size="small" :loading="saving" @click="onSaveRule">Save holiday</PortalBtn>
            <v-btn v-if="editingId" variant="text" size="small" @click="resetForm">Cancel</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
      <v-col cols="12" lg="7">
        <v-card variant="outlined">
          <v-card-title>Holiday rules</v-card-title>
          <v-table density="compact">
            <thead>
              <tr>
                <th>Name</th>
                <th>When</th>
                <th>Scope</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rule in rules" :key="rule.id">
                <td>
                  {{ rule.name }}
                  <span v-if="rule.is_movable" class="text-caption text-medium-emphasis"> · movable</span>
                  <span v-if="!rule.is_active" class="text-caption text-error"> · inactive</span>
                </td>
                <td class="text-caption">
                  <template v-if="rule.recurrence === 'yearly_md'">{{ rule.month }}/{{ rule.day }} yearly</template>
                  <template v-else>{{ rule.once_date || 'date needed' }}</template>
                </td>
                <td class="text-caption">{{ rule.scope }}{{ rule.country_iso2 ? ` ${rule.country_iso2}` : '' }}</td>
                <td class="text-end">
                  <PortalBtn size="x-small" variant="outlined" color="primary" @click="editRule(rule)">Edit</PortalBtn>
                  <v-btn size="x-small" variant="text" color="error" @click="onDelete(rule.id)">Delete</v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </v-col>
    </v-row>

    <v-card variant="outlined" class="mt-4">
      <v-card-title>Year preview</v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="3"><v-text-field v-model.number="previewYear" type="number" label="Year" /></v-col>
          <v-col cols="12" md="3"><v-text-field v-model="previewIso" label="Country ISO2" maxlength="2" /></v-col>
          <v-col cols="12" md="3" class="d-flex align-center"><PortalBtn size="small" @click="onPreview">Preview</PortalBtn></v-col>
        </v-row>
        <v-table v-if="previewRows.length" density="compact" class="mt-2">
          <thead><tr><th>Date</th><th>Holiday</th></tr></thead>
          <tbody>
            <tr v-for="row in previewRows" :key="row.date"><td>{{ row.date }}</td><td>{{ row.name }}</td></tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>

    <v-card variant="outlined" class="mt-4">
      <v-card-title>OpenHolidays import</v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="4">
            <v-select
              v-model="ohIso"
              :items="ohCountries.length ? ohCountries.map((c) => ({ title: `${c.name} (${c.iso})`, value: c.iso })) : [{ title: 'ZA (South Africa)', value: 'ZA' }]"
              label="Country"
            />
          </v-col>
          <v-col cols="12" md="3"><v-text-field v-model.number="ohYear" type="number" label="Year" /></v-col>
          <v-col cols="12" md="5" class="d-flex align-center ga-2">
            <PortalBtn size="small" variant="outlined" @click="onOhPreview">Preview</PortalBtn>
            <PortalBtn size="small" :loading="saving" @click="onOhImport">Import nationwide</PortalBtn>
          </v-col>
        </v-row>
        <v-table v-if="ohPreview.length" density="compact" class="mt-2">
          <thead><tr><th>Date</th><th>Name</th><th>Store as</th></tr></thead>
          <tbody>
            <tr v-for="row in ohPreview" :key="row.start_date + row.name">
              <td>{{ row.start_date }}</td>
              <td>{{ row.name }}</td>
              <td>{{ row.is_movable ? 'once (movable)' : 'yearly' }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>

    <v-row class="mt-2">
      <v-col cols="12" md="6">
        <v-card variant="outlined">
          <v-card-title>Independence days</v-card-title>
          <v-card-text>
            <v-table density="compact">
              <thead><tr><th>Nationality</th><th>ISO</th><th>Month</th><th>Day</th></tr></thead>
              <tbody>
                <tr v-for="row in independence" :key="row.nationality_id">
                  <td>{{ row.nationality }}</td>
                  <td>{{ row.iso2 }}</td>
                  <td><v-text-field v-model.number="row.independence_month" type="number" min="1" max="12" density="compact" hide-details /></td>
                  <td><v-text-field v-model.number="row.independence_day" type="number" min="1" max="31" density="compact" hide-details /></td>
                </tr>
              </tbody>
            </v-table>
          </v-card-text>
          <v-card-actions>
            <PortalBtn size="small" :loading="saving" @click="onSaveIndependence">Save independence dates</PortalBtn>
          </v-card-actions>
        </v-card>
      </v-col>
      <v-col cols="12" md="6">
        <v-card variant="outlined">
          <v-card-title>Duty station country ISO</v-card-title>
          <v-card-text>
            <v-table density="compact">
              <thead><tr><th>Station</th><th>Country</th><th>ISO2</th></tr></thead>
              <tbody>
                <tr v-for="row in stations" :key="row.duty_station_id">
                  <td>{{ row.duty_station_name }}</td>
                  <td>{{ row.country }}</td>
                  <td><v-text-field v-model="row.country_iso2" maxlength="2" density="compact" hide-details /></td>
                </tr>
              </tbody>
            </v-table>
          </v-card-text>
          <v-card-actions>
            <PortalBtn size="small" :loading="saving" @click="onSaveStations">Save station countries</PortalBtn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
