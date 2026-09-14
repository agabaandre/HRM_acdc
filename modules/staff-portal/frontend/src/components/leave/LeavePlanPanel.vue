<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import {
  fetchLeavePlan,
  saveLeavePlanDraft,
  submitLeavePlan,
  workingDaysBetween,
  type LeavePlanDto,
  type LeavePlanEntryInput,
} from '@/lib/leaveApi'

const loading = ref(false)
const saving = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const year = ref(new Date().getFullYear())
const yearOptions = ref<number[]>([year.value - 1, year.value, year.value + 1])
const plan = ref<LeavePlanDto | null>(null)
const notes = ref('')
const rows = ref<LeavePlanEntryInput[]>([])

const readonly = computed(() => !!plan.value?.readonly)
const canSave = computed(() => !!plan.value?.can_save && !readonly.value)
const canSubmit = computed(() => canSave.value && rows.value.some((r) => r.start_date && r.end_date))
const plannedTotal = computed(() =>
  rows.value.reduce((sum, r) => sum + Number(r.planned_days || 0), 0),
)
const annualLeaveId = computed(
  () => plan.value?.annual_leave?.leave_id ?? plan.value?.balance_hint?.leave_id ?? 0,
)
const annualLeaveName = computed(
  () => plan.value?.annual_leave?.leave_name || plan.value?.balance_hint?.leave_name || 'Annual leave',
)

function emptyRow(): LeavePlanEntryInput {
  return {
    leave_id: annualLeaveId.value || undefined,
    start_date: '',
    end_date: '',
    planned_days: 0,
    remarks: '',
  }
}

function applyPlan(data: LeavePlanDto) {
  plan.value = data
  notes.value = data.notes || ''
  const leaveId = data.annual_leave?.leave_id ?? data.balance_hint?.leave_id ?? 0
  rows.value = data.entries.length
    ? data.entries.map((e) => ({
        leave_id: leaveId || e.leave_id,
        start_date: e.start_date,
        end_date: e.end_date,
        planned_days: e.planned_days,
        remarks: e.remarks || '',
      }))
    : readonly.value
      ? []
      : [emptyRow()]
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await fetchLeavePlan(year.value)
    yearOptions.value = res.meta.year_options?.length ? res.meta.year_options : yearOptions.value
    if (!res.data.annual_leave && !res.data.balance_hint) {
      error.value = 'Annual leave type is not configured. Ask HR to set up leave types.'
    }
    applyPlan(res.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load leave plan')
    plan.value = null
  } finally {
    loading.value = false
  }
}

function recalcDays(index: number) {
  const row = rows.value[index]
  if (!row?.start_date || !row?.end_date || row.start_date > row.end_date) return
  row.planned_days = workingDaysBetween(row.start_date, row.end_date)
}

function addRow() {
  if (readonly.value) return
  rows.value.push(emptyRow())
}

function removeRow(index: number) {
  if (readonly.value) return
  rows.value.splice(index, 1)
  if (!rows.value.length) rows.value.push(emptyRow())
}

function entryPayload() {
  return rows.value
    .filter((r) => r.start_date && r.end_date)
    .map((r) => ({
      leave_id: annualLeaveId.value || undefined,
      start_date: r.start_date,
      end_date: r.end_date,
      planned_days: Number(r.planned_days || 0) || undefined,
      remarks: r.remarks || undefined,
    }))
}

async function onSaveDraft() {
  if (!plan.value || !canSave.value) return
  if (!annualLeaveId.value) {
    error.value = 'Annual leave type is not configured. Ask HR to set up leave types.'
    return
  }
  saving.value = true
  error.value = null
  success.value = null
  try {
    const res = await saveLeavePlanDraft(plan.value.id, {
      notes: notes.value || undefined,
      entries: entryPayload(),
    })
    applyPlan(res.data)
    success.value = res.message
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save leave plan draft')
  } finally {
    saving.value = false
  }
}

async function onSubmit() {
  if (!plan.value || !canSubmit.value) return
  if (!annualLeaveId.value) {
    error.value = 'Annual leave type is not configured. Ask HR to set up leave types.'
    return
  }
  if (
    !window.confirm(
      'Submit this annual leave plan for the year? After submission you will not be able to edit it.',
    )
  ) {
    return
  }
  submitting.value = true
  error.value = null
  success.value = null
  try {
    await saveLeavePlanDraft(plan.value.id, {
      notes: notes.value || undefined,
      entries: entryPayload(),
    })
    const res = await submitLeavePlan(plan.value.id)
    applyPlan(res.data)
    success.value = res.message
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not submit leave plan')
  } finally {
    submitting.value = false
  }
}

watch(year, () => void load())
onMounted(() => void load())
</script>

<template>
  <div class="leave-plan">
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <div class="portal-staff-filters mb-3">
      <v-row dense align="center">
        <v-col cols="12" sm="4" md="3">
          <v-select
            v-model="year"
            :items="yearOptions"
            label="Calendar year"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="8" md="9" class="d-flex flex-wrap ga-2 align-center">
          <v-chip
            v-if="plan"
            size="small"
            variant="tonal"
            :color="plan.draft_status === 1 ? 'info' : 'success'"
          >
            <i
              class="me-1"
              :class="plan.draft_status === 1 ? 'fa-solid fa-pen' : 'fa-solid fa-lock'"
              aria-hidden="true"
            />
            {{ plan.status_label }}
          </v-chip>
          <v-chip size="small" variant="tonal" color="primary">
            <i class="fa-solid fa-umbrella-beach me-1" aria-hidden="true" />
            {{ annualLeaveName }} only
          </v-chip>
          <span v-if="plan?.submitted_at" class="text-caption text-medium-emphasis">
            Submitted {{ plan.submitted_at.slice(0, 10) }}
          </span>
          <span class="text-caption text-medium-emphasis">
            Plan your annual leave periods for the year (Jan–Dec). Draft is editable; submitted plans are locked.
          </span>
        </v-col>
      </v-row>
    </div>

    <div v-if="loading" class="text-medium-emphasis mb-3">Loading leave plan…</div>

    <template v-else-if="plan">
      <v-alert
        v-if="readonly"
        type="info"
        variant="tonal"
        class="mb-3"
        density="compact"
      >
        This annual leave plan is submitted and locked. Contact HR if a revision is required.
      </v-alert>

      <v-row dense class="mb-3">
        <v-col cols="6" sm="4" md="3">
          <v-sheet
            rounded
            class="pa-3 perf-kpi"
            style="--perf-kpi-bg: linear-gradient(135deg, #0f766e 0%, #0f766edd 100%)"
          >
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-uppercase" style="color: rgba(255,255,255,0.85)">Planned days</div>
                <div class="text-h6 text-white font-weight-bold">
                  {{ plannedTotal.toFixed(1).replace(/\.0$/, '') }}
                </div>
              </div>
              <i class="fa-solid fa-calendar-check" style="color: rgba(255,255,255,0.35); font-size: 1.35rem" aria-hidden="true" />
            </div>
          </v-sheet>
        </v-col>
        <v-col v-if="plan.balance_hint" cols="6" sm="4" md="3">
          <v-sheet
            rounded
            class="pa-3 perf-kpi"
            style="--perf-kpi-bg: linear-gradient(135deg, #15803d 0%, #15803ddd 100%)"
          >
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-uppercase" style="color: rgba(255,255,255,0.85)">
                  Available
                </div>
                <div class="text-h6 text-white font-weight-bold">{{ plan.balance_hint.available }}</div>
              </div>
              <i class="fa-solid fa-umbrella-beach" style="color: rgba(255,255,255,0.35); font-size: 1.35rem" aria-hidden="true" />
            </div>
          </v-sheet>
        </v-col>
        <v-col cols="6" sm="4" md="3">
          <v-sheet
            rounded
            class="pa-3 perf-kpi"
            style="--perf-kpi-bg: linear-gradient(135deg, #1d4ed8 0%, #1d4ed8dd 100%)"
          >
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-uppercase" style="color: rgba(255,255,255,0.85)">Periods</div>
                <div class="text-h6 text-white font-weight-bold">{{ rows.length }}</div>
              </div>
              <i class="fa-solid fa-list" style="color: rgba(255,255,255,0.35); font-size: 1.35rem" aria-hidden="true" />
            </div>
          </v-sheet>
        </v-col>
      </v-row>

      <v-card variant="outlined" class="mb-3">
        <v-card-title class="d-flex justify-space-between align-center flex-wrap ga-2">
          <span>
            <i class="fa-solid fa-calendar-days me-2" style="color: #119a48" aria-hidden="true" />
            Annual leave periods — {{ year }}
          </span>
          <v-btn
            v-if="canSave"
            size="small"
            variant="text"
            @click="addRow"
          >
            <i class="fa-solid fa-plus me-1" aria-hidden="true" />
            Add period
          </v-btn>
        </v-card-title>
        <v-card-text>
          <div
            v-for="(row, index) in rows"
            :key="index"
            class="leave-plan__row mb-3"
          >
            <v-row dense>
              <v-col cols="12" sm="6" md="3">
                <UDateInput
                  v-model="row.start_date"
                  label="Start"
                  placeholder="Start date"
                  density="compact"
                  hide-details
                  :disabled="readonly"
                  :max="row.end_date || `${year}-12-31`"
                  :min="`${year}-01-01`"
                  @update:model-value="recalcDays(index)"
                />
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <UDateInput
                  v-model="row.end_date"
                  label="End"
                  placeholder="End date"
                  density="compact"
                  hide-details
                  :disabled="readonly"
                  :min="row.start_date || `${year}-01-01`"
                  :max="`${year}-12-31`"
                  @update:model-value="recalcDays(index)"
                />
              </v-col>
              <v-col cols="6" md="2">
                <v-text-field
                  v-model.number="row.planned_days"
                  type="number"
                  min="0.5"
                  step="0.5"
                  label="Days"
                  density="compact"
                  hide-details
                  :disabled="readonly"
                />
              </v-col>
              <v-col cols="6" md="3">
                <v-text-field
                  v-model="row.remarks"
                  label="Remarks"
                  density="compact"
                  hide-details
                  :disabled="readonly"
                />
              </v-col>
              <v-col cols="12" md="1" class="d-flex align-center">
                <v-btn
                  v-if="canSave"
                  icon
                  variant="text"
                  size="small"
                  :disabled="rows.length < 2"
                  @click="removeRow(index)"
                >
                  <i class="fa-solid fa-trash" aria-hidden="true" />
                </v-btn>
              </v-col>
            </v-row>
          </div>

          <v-textarea
            v-model="notes"
            label="Plan notes (optional)"
            rows="2"
            density="compact"
            class="mt-2"
            :disabled="readonly"
            hide-details
          />
        </v-card-text>
        <v-card-actions v-if="canSave" class="px-4 pb-4">
          <v-spacer />
          <v-btn variant="outlined" :loading="saving" @click="onSaveDraft">
            <i class="fa-solid fa-floppy-disk me-2" aria-hidden="true" />
            Save draft
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="submitting"
            :disabled="!canSubmit"
            @click="onSubmit"
          >
            <i class="fa-solid fa-paper-plane me-2" aria-hidden="true" />
            Submit plan
          </v-btn>
        </v-card-actions>
      </v-card>
    </template>
  </div>
</template>

<style scoped>
.leave-plan__row {
  border: 1px solid #e8eef3;
  border-radius: 10px;
  padding: 0.65rem 0.75rem;
  background: #fff;
}
</style>
