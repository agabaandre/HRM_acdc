<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import {
  fetchLeaveApprovalWorkflow,
  saveLeaveApprovalWorkflow,
  type LeaveApprovalLevelDto,
  type LeaveApprovalStaffOption,
} from '@/lib/leaveApi'

const emit = defineEmits<{
  (e: 'status', payload: { success?: string | null; error?: string | null }): void
}>()

const loading = ref(false)
const saving = ref(false)
const enabled = ref(false)
const levels = ref<LeaveApprovalLevelDto[]>([])
const staffOptions = ref<LeaveApprovalStaffOption[]>([])

const officerChoices = computed(() =>
  staffOptions.value.map((o) => ({
    value: Number(o.staff_id),
    title: String(o.name || `Staff #${o.staff_id}`),
    subtitle: String(o.work_email || ''),
    searchText: [o.name, o.work_email, o.sap_number].filter(Boolean).join(' ').toLowerCase(),
  })),
)

function filterOfficers(
  _value: string,
  query: string,
  item?: { raw?: { searchText?: string; title?: string; subtitle?: string } },
): boolean {
  const q = query.trim().toLowerCase()
  if (!q) return true
  const hay = String(
    item?.raw?.searchText || [item?.raw?.title, item?.raw?.subtitle].filter(Boolean).join(' ') || _value || '',
  ).toLowerCase()
  return hay.includes(q)
}

function ensureHodFirst(rows: LeaveApprovalLevelDto[]): LeaveApprovalLevelDto[] {
  const hod = rows.find((row) => row.role === 'hod') ?? {
    id: 0,
    sort_order: 0,
    role: 'hod' as const,
    staff_id: null,
    staff_name: null,
    label: 'Head of Division',
    locked: true,
  }
  const hr = rows.filter((row) => row.role !== 'hod')
  return [{ ...hod, locked: true, staff_id: null }, ...hr]
}

async function load() {
  loading.value = true
  emit('status', { error: null })
  try {
    const data = await fetchLeaveApprovalWorkflow()
    enabled.value = !!data.enabled
    levels.value = ensureHodFirst(data.levels || [])
    staffOptions.value = data.staff_options || []
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'Could not load leave approval workflow') })
  } finally {
    loading.value = false
  }
}

function addHrLevel() {
  levels.value = [
    ...levels.value,
    {
      id: 0,
      sort_order: levels.value.length,
      role: 'hr',
      staff_id: null,
      label: `HR approver ${Math.max(1, levels.value.filter((l) => l.role === 'hr').length + 1)}`,
      locked: false,
    },
  ]
}

function removeLevel(index: number) {
  if (levels.value[index]?.role === 'hod') return
  levels.value = levels.value.filter((_, i) => i !== index)
}

function moveLevel(index: number, dir: -1 | 1) {
  const next = index + dir
  if (index < 1 || next < 1 || next >= levels.value.length) return
  const copy = [...levels.value]
  const tmp = copy[index]
  copy[index] = copy[next]
  copy[next] = tmp
  levels.value = copy
}

async function save() {
  saving.value = true
  emit('status', { success: null, error: null })
  try {
    if (enabled.value) {
      const missingHr = levels.value.find((row) => row.role === 'hr' && !row.staff_id)
      if (missingHr) {
        emit('status', { error: 'Assign a staff member to every HR approval level.' })
        return
      }
    }
    const data = await saveLeaveApprovalWorkflow({
      enabled: enabled.value,
      levels: levels.value
        .filter((row) => row.role === 'hod' || row.staff_id)
        .map((row) => ({
          role: row.role,
          staff_id: row.staff_id ?? null,
          label: row.label,
        })),
    })
    enabled.value = !!data.enabled
    levels.value = ensureHodFirst(data.levels || [])
    staffOptions.value = data.staff_options || staffOptions.value
    emit('status', { success: 'Leave approval workflow saved.' })
  } catch (e) {
    emit('status', { error: apiErrorMessage(e, 'Could not save leave approval workflow') })
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <v-card variant="outlined">
    <v-card-text>
      <div v-if="loading" class="text-medium-emphasis">Loading…</div>
      <template v-else>
        <h3 class="text-h6 text-primary mb-2">Sequential leave approval</h3>
        <p class="text-body-2 text-medium-emphasis mb-4">
          When enabled, each request is first approved by the employee’s Head of Division, then by the HR
          approvers below in order. Staff can change the HOD when they apply, because acting heads vary.
        </p>

        <v-switch
          v-model="enabled"
          color="primary"
          hide-details
          class="mb-6"
          label="Enable sequential HOD → HR approval workflow"
        />

        <div class="d-flex align-center justify-space-between mb-3">
          <h4 class="text-subtitle-1 font-weight-medium mb-0">Approval levels</h4>
          <PortalBtn size="small" variant="outlined" color="primary" :disabled="!enabled" @click="addHrLevel">
            <i class="fa-solid fa-plus me-2" aria-hidden="true" />
            Add HR level
          </PortalBtn>
        </div>

        <div
          v-for="(level, index) in levels"
          :key="`${level.role}-${index}`"
          class="leave-wf-level"
          :class="{ 'leave-wf-level--locked': level.role === 'hod' }"
        >
          <div class="leave-wf-level__index">{{ index + 1 }}</div>
          <div class="leave-wf-level__fields">
            <v-text-field
              v-model="level.label"
              :label="level.role === 'hod' ? 'Level 1 · Head of Division' : `Level ${index + 1} · HR approver`"
              density="comfortable"
              hide-details="auto"
              :disabled="!enabled"
            />
            <div v-if="level.role === 'hod'" class="text-caption text-medium-emphasis mt-1">
              Taken from the employee’s division by default. They can pick a different HOD on the apply form.
            </div>
            <v-autocomplete
              v-else
              v-model="level.staff_id"
              :items="officerChoices"
              item-title="title"
              item-value="value"
              :custom-filter="filterOfficers"
              label="Assigned HR staff"
              placeholder="Type a name or email"
              density="comfortable"
              hide-details="auto"
              class="mt-3"
              :disabled="!enabled"
              clearable
            >
              <template #item="{ props: itemProps, item }">
                <v-list-item
                  v-bind="itemProps"
                  :title="String(item.raw.title)"
                  :subtitle="item.raw.subtitle || undefined"
                />
              </template>
            </v-autocomplete>
          </div>
          <div class="leave-wf-level__actions">
            <v-btn
              icon
              variant="text"
              size="small"
              :disabled="!enabled || index < 2"
              aria-label="Move up"
              @click="moveLevel(index, -1)"
            >
              <i class="fa-solid fa-arrow-up" aria-hidden="true" />
            </v-btn>
            <v-btn
              icon
              variant="text"
              size="small"
              :disabled="!enabled || index < 1 || index === levels.length - 1"
              aria-label="Move down"
              @click="moveLevel(index, 1)"
            >
              <i class="fa-solid fa-arrow-down" aria-hidden="true" />
            </v-btn>
            <v-btn
              icon
              variant="text"
              size="small"
              color="error"
              :disabled="!enabled || level.role === 'hod'"
              aria-label="Remove level"
              @click="removeLevel(index)"
            >
              <i class="fa-solid fa-trash" aria-hidden="true" />
            </v-btn>
          </div>
        </div>
      </template>
    </v-card-text>
    <v-card-actions>
      <PortalBtn :loading="saving" :disabled="loading" @click="save">Save workflow</PortalBtn>
    </v-card-actions>
  </v-card>
</template>

<style scoped>
.leave-wf-level {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 0.9rem 0;
  border-bottom: 1px solid rgba(58, 71, 82, 0.1);
}

.leave-wf-level:last-of-type {
  border-bottom: 0;
}

.leave-wf-level__index {
  flex: 0 0 2rem;
  width: 2rem;
  height: 2rem;
  margin-top: 0.35rem;
  border-radius: 999px;
  background: #119a48;
  color: #fff;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.leave-wf-level--locked .leave-wf-level__index {
  background: #1d4ed8;
}

.leave-wf-level__fields {
  flex: 1 1 auto;
  min-width: 0;
}

.leave-wf-level__actions {
  display: flex;
  flex-direction: column;
  flex: 0 0 auto;
}
</style>
