<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PayrollPageShell from '@/components/payroll/PayrollPageShell.vue'
import {
  createPeriod,
  createRun,
  fetchPeriods,
  fetchRuns,
  type PayrollPeriod,
  type PayrollRun,
} from '@/lib/payrollApi'

const loading = ref(true)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const runs = ref<PayrollRun[]>([])
const periods = ref<PayrollPeriod[]>([])
const year = ref(new Date().getFullYear())
const month = ref(new Date().getMonth() + 1)
const periodId = ref<number | null>(null)
const busy = ref(false)

async function load() {
  loading.value = true
  error.value = null
  try {
    ;[runs.value, periods.value] = await Promise.all([fetchRuns(), fetchPeriods()])
    if (!periodId.value && periods.value[0]) periodId.value = periods.value[0].id
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}

async function addPeriod() {
  busy.value = true
  error.value = null
  try {
    await createPeriod({ year: year.value, month: month.value })
    success.value = 'Period created.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

async function addRun() {
  if (!periodId.value) return
  busy.value = true
  error.value = null
  try {
    await createRun({ period_id: periodId.value })
    success.value = 'Run created.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <PayrollPageShell title="Payroll runs" lede="Create monthly periods, then simulate and post wage runs.">
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>

    <v-row dense class="mb-3">
      <v-col cols="12" md="6">
        <div class="payroll-panel">
          <div class="payroll-panel__title">New period</div>
          <div class="d-flex ga-2 align-center flex-wrap">
            <v-text-field v-model.number="year" label="Year" type="number" density="compact" hide-details style="max-width: 110px" />
            <v-text-field v-model.number="month" label="Month" type="number" density="compact" hide-details style="max-width: 100px" />
            <v-btn color="primary" size="small" :loading="busy" @click="addPeriod">Create period</v-btn>
          </div>
        </div>
      </v-col>
      <v-col cols="12" md="6">
        <div class="payroll-panel">
          <div class="payroll-panel__title">New run</div>
          <div class="d-flex ga-2 align-center flex-wrap">
            <v-select
              v-model="periodId"
              :items="periods"
              item-title="label"
              item-value="id"
              label="Period"
              density="compact"
              hide-details
              style="min-width: 160px"
            />
            <v-btn color="primary" size="small" :loading="busy" :disabled="!periodId" @click="addRun">
              Create run
            </v-btn>
          </div>
        </div>
      </v-col>
    </v-row>

    <div class="payroll-table-wrap">
      <v-table density="compact">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Period</th>
            <th>Status</th>
            <th>Staff</th>
            <th>Net (default)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in runs" :key="r.id">
            <td>{{ r.id }}</td>
            <td>{{ r.title }}</td>
            <td>{{ r.period?.label }}</td>
            <td><span class="payroll-status" :class="`payroll-status--${r.status}`">{{ r.status }}</span></td>
            <td>{{ r.staff_count }}</td>
            <td>{{ Number(r.total_net_default).toFixed(2) }}</td>
            <td>
              <v-btn size="small" variant="text" color="primary" :to="{ name: 'payroll-run-detail', params: { id: r.id } }">
                Open
              </v-btn>
            </td>
          </tr>
          <tr v-if="!loading && !runs.length">
            <td colspan="7" class="text-center text-medium-emphasis py-6">No runs yet. Create a period, then a run.</td>
          </tr>
        </tbody>
      </v-table>
    </div>
    <div v-if="loading" class="mt-3 text-medium-emphasis">Loading…</div>
  </PayrollPageShell>
</template>
