<script setup lang="ts">
import type { PerformanceContract, PerformanceFormState } from '@/lib/performanceApi'

withDefaults(
  defineProps<{
    form: PerformanceFormState
    contract: PerformanceContract
    periodLabel: string
    /** PPA uses "Staff Details"; mid/endterm use "Personal Details". */
    title?: string
    initiationLabel?: string
    divisionLabel?: string
    supervisorLabel?: string
  }>(),
  {
    title: 'A. Staff Details',
    initiationLabel: 'Initiation Date',
    divisionLabel: 'Division/Directorate',
    supervisorLabel: 'First Supervisor',
  },
)
</script>

<template>
  <v-card variant="outlined" class="perf-staff-details h-100 d-flex flex-column">
    <v-card-title class="text-h6">{{ title }}</v-card-title>
    <v-card-text class="flex-grow-1">
      <v-table density="compact" class="perf-staff-details__table">
        <tbody>
          <tr>
            <th class="perf-label">Name</th>
            <td>{{ [contract.fname, contract.lname].filter(Boolean).join(' ') || '—' }}</td>
            <th class="perf-label">SAP NO</th>
            <td>{{ contract.SAPNO || '—' }}</td>
          </tr>
          <tr>
            <th class="perf-label">Position</th>
            <td>{{ contract.job_name || '—' }}</td>
            <th class="perf-label">{{ initiationLabel }}</th>
            <td>{{ contract.initiation_date || '—' }}</td>
          </tr>
          <tr>
            <th class="perf-label">{{ divisionLabel }}</th>
            <td>{{ contract.division_name || '—' }}</td>
            <th class="perf-label">Performance Period</th>
            <td>{{ periodLabel }}</td>
          </tr>
          <tr>
            <th class="perf-label">{{ supervisorLabel }}</th>
            <td>{{ contract.first_supervisor_name || '—' }}</td>
            <th class="perf-label">Second Supervisor</th>
            <td>{{ contract.second_supervisor_name || '—' }}</td>
          </tr>
          <tr>
            <th class="perf-label">Funder</th>
            <td>{{ contract.funder || '—' }}</td>
            <th class="perf-label">Contract Type</th>
            <td>{{ contract.contract_type || '—' }}</td>
          </tr>
        </tbody>
      </v-table>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.perf-label {
  width: 10.5rem;
  font-weight: 700;
  color: rgba(0, 0, 0, 0.82);
}

.perf-staff-details :deep(.v-card-title) {
  padding-top: 1.15rem;
  padding-bottom: 0.85rem;
}

.perf-staff-details :deep(.v-card-text) {
  padding-top: 0.85rem;
  padding-bottom: 1.25rem;
}

.perf-staff-details__table :deep(th),
.perf-staff-details__table :deep(td) {
  border-bottom: 1px solid rgba(0, 0, 0, 0.06) !important;
  vertical-align: middle;
  /* ~25% taller rows vs compact defaults */
  padding-top: 0.85rem !important;
  padding-bottom: 0.85rem !important;
  line-height: 1.45;
}
</style>
