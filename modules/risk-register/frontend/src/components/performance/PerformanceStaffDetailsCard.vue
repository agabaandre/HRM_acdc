<script setup lang="ts">
import { computed } from 'vue'
import type { PerformanceContract, PerformanceFormState } from '@/lib/performanceApi'

const props = withDefaults(
  defineProps<{
    form: PerformanceFormState
    contract: PerformanceContract
    periodLabel: string
    /** PPA uses "Staff Details"; mid/endterm use "Personal Details". */
    title?: string
    initiationLabel?: string
    divisionLabel?: string
    supervisorLabel?: string
    canChangeSupervisors?: boolean
    supervisorOptions?: Array<{ staff_id: number; name: string }>
    supervisorBusy?: boolean
  }>(),
  {
    title: 'A. Staff Details',
    initiationLabel: 'Initiation Date',
    divisionLabel: 'Division/Directorate',
    supervisorLabel: 'First Supervisor',
    canChangeSupervisors: false,
    supervisorOptions: () => [],
    supervisorBusy: false,
  },
)

const emit = defineEmits<{
  'update:supervisorId': [value: number]
  'update:supervisor2Id': [value: number | null]
  'save-supervisors': []
}>()

const staffItems = computed(() =>
  props.supervisorOptions.map((row) => ({
    title: row.name,
    value: row.staff_id,
  })),
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
            <td>
              <v-autocomplete
                v-if="canChangeSupervisors"
                :model-value="form.supervisor_id || null"
                :items="staffItems"
                :loading="supervisorBusy"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Select first supervisor"
                @update:model-value="emit('update:supervisorId', Number($event || 0))"
              />
              <template v-else>{{ contract.first_supervisor_name || '—' }}</template>
            </td>
            <th class="perf-label">Second Supervisor</th>
            <td>
              <v-autocomplete
                v-if="canChangeSupervisors"
                :model-value="form.supervisor2_id || null"
                :items="staffItems"
                :loading="supervisorBusy"
                density="compact"
                variant="outlined"
                hide-details
                clearable
                placeholder="Optional"
                @update:model-value="emit('update:supervisor2Id', $event ? Number($event) : null)"
              />
              <template v-else>{{ contract.second_supervisor_name || '—' }}</template>
            </td>
          </tr>
          <tr v-if="canChangeSupervisors">
            <td colspan="4" class="perf-staff-details__actions">
              <v-btn
                size="small"
                variant="tonal"
                color="primary"
                :loading="supervisorBusy"
                :disabled="!form.supervisor_id"
                @click="emit('save-supervisors')"
              >
                Update supervisors
              </v-btn>
              <span class="text-caption text-medium-emphasis ms-2">
                Draft forms only. Approved records stay unchanged.
              </span>
            </td>
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

.perf-staff-details__actions {
  padding-top: 0.4rem !important;
  padding-bottom: 0.4rem !important;
}
</style>
