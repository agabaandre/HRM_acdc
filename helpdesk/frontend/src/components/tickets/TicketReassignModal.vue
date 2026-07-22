<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { minLengthError, PRIORITY_ITEMS, type TicketPriority } from '../../lib/helpdeskForm'
import { notifyError, notifySuccess } from '../../lib/notify'

export interface ReassignTicketRef {
  id: number
  ticket_number: string
  subject: string
}

interface EligibleAgent {
  id: number
  name: string
  email: string
  avatar_url?: string | null
  duty_station?: string | null
  open_workload: number
}

interface SupportGroupOption {
  id: number
  name: string
  members_count: number
  open_workload: number
}

interface BusinessUnitOption {
  id: number
  name: string
  slug: string
  description?: string | null
  categories: Array<{ id: number; name: string }>
}

interface AssignmentCurrent {
  assignee_user_ids: number[]
  assigned_group_id: number | null
  priority: TicketPriority
  business_unit_id: number | null
  category_id: number | null
}

const props = defineProps<{
  ticket: ReassignTicketRef | null
}>()

const emit = defineEmits<{
  close: []
  reassigned: [payload: { ticketId: number; agentName?: string }]
}>()

const agents = ref<EligibleAgent[]>([])
const groups = ref<SupportGroupOption[]>([])
const businessUnits = ref<BusinessUnitOption[]>([])
const candidatesLoading = ref(false)
const selectedAgentIds = ref<number[]>([])
const selectedGroupId = ref<number | null>(null)
const selectedPriority = ref<TicketPriority>('medium')
const selectedBusinessUnitId = ref<number | null>(null)
const selectedCategoryId = ref<number | null>(null)
const reassignForm = reactive({ reason: '' })
const submitting = ref(false)
const submitError = ref('')
const reassignFormEl = ref<{ submit: () => void } | null>(null)
let loadSeq = 0
let skipBuWatch = false

const modalOpen = computed({
  get: () => props.ticket !== null,
  set: (open: boolean) => {
    if (!open) {
      close()
    }
  },
})

const modalTitle = computed(() =>
  props.ticket ? `Configure ${props.ticket.ticket_number}` : 'Configure ticket',
)

const modalDescription = computed(() => props.ticket?.subject ?? undefined)

const agentSelectItems = computed(() =>
  agents.value.map((a) => ({
    label: `${a.name} (${a.open_workload} open)`,
    value: a.id,
  })),
)

const groupSelectItems = computed(() =>
  groups.value.map((g) => ({
    label: `${g.name} (${g.members_count} members · ${g.open_workload} open)`,
    value: g.id,
  })),
)

const businessUnitSelectItems = computed(() =>
  businessUnits.value.map((u) => ({
    label: u.name,
    value: u.id,
  })),
)

const selectedBusinessUnit = computed(() =>
  businessUnits.value.find((u) => u.id === selectedBusinessUnitId.value) ?? null,
)

const categorySelectItems = computed(() =>
  (selectedBusinessUnit.value?.categories ?? []).map((c) => ({
    label: c.name,
    value: c.id,
  })),
)

const selectedAgents = computed(() =>
  selectedAgentIds.value
    .map((id) => agents.value.find((a) => a.id === id))
    .filter((a): a is EligibleAgent => a != null),
)

/** Normalise Vuetify autocomplete values (number | string | object | array). */
function normalizeAgentIds(value: unknown): number[] {
  if (value == null || value === '') {
    return []
  }
  const raw = Array.isArray(value) ? value : [value]
  const ids: number[] = []
  for (const item of raw) {
    if (typeof item === 'number' && item > 0) {
      ids.push(item)
      continue
    }
    if (typeof item === 'string' && item.trim() !== '') {
      const parsed = parseInt(item, 10)
      if (parsed > 0) {
        ids.push(parsed)
      }
      continue
    }
    if (item && typeof item === 'object') {
      const obj = item as Record<string, unknown>
      if ('value' in obj) {
        const parsed = Number(obj.value)
        if (parsed > 0) {
          ids.push(parsed)
        }
      } else if ('id' in obj) {
        const parsed = Number(obj.id)
        if (parsed > 0) {
          ids.push(parsed)
        }
      }
    }
  }
  return [...new Set(ids)]
}

const agentIdsModel = computed({
  get: () => selectedAgentIds.value,
  set: (value: unknown) => {
    selectedAgentIds.value = normalizeAgentIds(value)
  },
})

function resetState(): void {
  agents.value = []
  groups.value = []
  businessUnits.value = []
  selectedAgentIds.value = []
  selectedGroupId.value = null
  selectedPriority.value = 'medium'
  selectedBusinessUnitId.value = null
  selectedCategoryId.value = null
  reassignForm.reason = ''
  submitting.value = false
  submitError.value = ''
}

function close(): void {
  resetState()
  emit('close')
}

function makePrimary(agentId: number): void {
  const rest = selectedAgentIds.value.filter((id) => id !== agentId)
  selectedAgentIds.value = [agentId, ...rest]
}

async function loadBusinessUnits(): Promise<void> {
  try {
    const { data } = await api.get<{ data: BusinessUnitOption[] }>('/api/v1/business-units')
    businessUnits.value = Array.isArray(data.data) ? data.data : []
  } catch {
    businessUnits.value = []
  }
}

async function loadCandidates(ticketId: number): Promise<void> {
  const seq = ++loadSeq
  candidatesLoading.value = true
  try {
    const [{ data }] = await Promise.all([
      api.get<{
        data: {
          current?: AssignmentCurrent
          agents: EligibleAgent[]
          groups: SupportGroupOption[]
        }
      }>(`/api/v1/tickets/${ticketId}/eligible-agents`),
      loadBusinessUnits(),
    ])

    if (seq !== loadSeq) {
      return
    }

    agents.value = Array.isArray(data.data?.agents) ? data.data.agents : []
    groups.value = Array.isArray(data.data?.groups) ? data.data.groups : []

    const current = data.data?.current
    selectedAgentIds.value = normalizeAgentIds(current?.assignee_user_ids ?? [])
    selectedGroupId.value = current?.assigned_group_id ?? null
    selectedPriority.value = current?.priority ?? 'medium'

    skipBuWatch = true
    let buId = current?.business_unit_id ?? null
    const catId = current?.category_id ?? null
    if (!buId && catId) {
      const match = businessUnits.value.find((u) => u.categories.some((c) => c.id === catId))
      buId = match?.id ?? null
    }
    selectedBusinessUnitId.value = buId
    selectedCategoryId.value = catId
    queueMicrotask(() => {
      skipBuWatch = false
    })
  } catch (e: unknown) {
    if (seq !== loadSeq) {
      return
    }
    notifyError(apiErrorMessage(e, 'Could not load assignment options.'))
    close()
  } finally {
    if (seq === loadSeq) {
      candidatesLoading.value = false
    }
  }
}

watch(
  () => props.ticket,
  (ticket) => {
    if (ticket) {
      resetState()
      void loadCandidates(ticket.id)
    } else {
      resetState()
    }
  },
  { immediate: true },
)

watch(selectedBusinessUnitId, () => {
  if (skipBuWatch) return
  const allowed = new Set((selectedBusinessUnit.value?.categories ?? []).map((c) => c.id))
  if (selectedCategoryId.value && !allowed.has(selectedCategoryId.value)) {
    selectedCategoryId.value = null
  }
})

function validateReassign(state: typeof reassignForm): FormError[] {
  const errors: FormError[] = []
  if (selectedAgentIds.value.length === 0 && !selectedGroupId.value) {
    errors.push({ name: 'assignee', message: 'Select at least one agent or a support group.' })
  }
  if (!selectedBusinessUnitId.value) {
    errors.push({ name: 'business_unit', message: 'Select a business unit.' })
  }
  if (!selectedCategoryId.value) {
    errors.push({ name: 'category', message: 'Select a category.' })
  }
  const reasonErr = minLengthError(
    'reason',
    state.reason,
    5,
    'Reason must be at least 5 characters.',
  )
  if (reasonErr) {
    errors.push(reasonErr)
  }
  return errors
}

async function onReassignSubmit(_event: FormSubmitEvent<typeof reassignForm>): Promise<void> {
  if (!props.ticket) {
    return
  }
  submitError.value = ''
  if (selectedAgentIds.value.length === 0 && !selectedGroupId.value) {
    const message = 'Select at least one agent or a support group.'
    submitError.value = message
    notifyError(message)
    return
  }
  if (!selectedBusinessUnitId.value) {
    const message = 'Select a business unit.'
    submitError.value = message
    notifyError(message)
    return
  }
  if (!selectedCategoryId.value) {
    const message = 'Select a category.'
    submitError.value = message
    notifyError(message)
    return
  }

  submitting.value = true
  try {
    await api.post(`/api/v1/tickets/${props.ticket.id}/reassign`, {
      assignee_user_ids: selectedAgentIds.value,
      assignee_group_id: selectedGroupId.value,
      priority: selectedPriority.value,
      business_unit_id: selectedBusinessUnitId.value,
      category_id: selectedCategoryId.value,
      reason: reassignForm.reason.trim(),
    })
    const names = selectedAgents.value.map((a) => a.name)
    const label =
      names.length > 0
        ? names.join(', ')
        : groups.value.find((g) => g.id === selectedGroupId.value)?.name
    notifySuccess(
      `Updated ${props.ticket.ticket_number}${label ? ` (${label})` : ''}.`,
    )
    emit('reassigned', { ticketId: props.ticket.id, agentName: names[0] })
    close()
  } catch (e: unknown) {
    const message = apiErrorMessage(e, 'Configuration update failed.')
    submitError.value = message
    notifyError(message)
  } finally {
    submitting.value = false
  }
}

function onValidationFailed(errors: FormError[]): void {
  const message = errors[0]?.message ?? 'Please fix the highlighted fields.'
  submitError.value = message
  notifyError(message)
}

function submitReassign(): void {
  reassignFormEl.value?.submit()
}
</script>

<template>
  <UModal
    v-if="ticket"
    v-model:open="modalOpen"
    :title="modalTitle"
    :description="modalDescription"
    :ui="{ content: 'max-w-xl' }"
  >
    <template #body>
      <UForm
        ref="reassignFormEl"
        id="reassign-form"
        :state="reassignForm"
        :validate="validateReassign"
        class="hd-form reassign-body"
        @submit="onReassignSubmit"
        @validation-failed="onValidationFailed"
      >
        <p v-if="submitError" class="reassign-error" role="alert">{{ submitError }}</p>
        <p v-if="candidatesLoading" class="muted">Loading configuration options…</p>
        <template v-else>
          <UFormField
            label="Agents"
            name="assignee"
            description="Select one or more agents. The first agent is the primary assignee — use “Make primary” to change who leads."
          >
            <USelectMenu
              v-model="agentIdsModel"
              :items="agentSelectItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-account-multiple"
              placeholder="Search and select agents…"
              :disabled="agents.length === 0"
            />
            <ul
              v-if="selectedAgents.length > 1"
              class="agent-primary-list"
              aria-label="Agent primary order"
            >
              <li v-for="(agent, index) in selectedAgents" :key="agent.id" class="agent-primary-row">
                <span class="agent-primary-name">
                  {{ agent.name }}
                  <span v-if="index === 0" class="agent-chip-primary">Primary</span>
                </span>
                <button
                  v-if="index > 0"
                  type="button"
                  class="agent-primary-btn"
                  @click="makePrimary(agent.id)"
                >
                  Make primary
                </button>
              </li>
            </ul>
          </UFormField>

          <UFormField
            label="Support group"
            name="group"
            description="Optional queue or team assignment."
          >
            <USelect
              v-model="selectedGroupId"
              :items="groupSelectItems"
              value-key="value"
              clearable
              icon="mdi-account-group-outline"
              placeholder="No group"
              :disabled="groups.length === 0"
            />
          </UFormField>

          <UFormField label="Priority" name="priority">
            <USelect
              v-model="selectedPriority"
              :items="PRIORITY_ITEMS"
              icon="mdi-flag-outline"
            />
          </UFormField>

          <UFormField label="Business unit" name="business_unit" required>
            <USelectMenu
              v-model="selectedBusinessUnitId"
              :items="businessUnitSelectItems"
              value-key="value"
              searchable
              icon="mdi-office-building-outline"
              placeholder="Search or select business unit"
              :disabled="businessUnits.length === 0"
            />
            <p v-if="selectedBusinessUnit?.description" class="field-hint">
              {{ selectedBusinessUnit.description }}
            </p>
          </UFormField>

          <UFormField label="Category" name="category" required>
            <USelectMenu
              v-model="selectedCategoryId"
              :items="categorySelectItems"
              value-key="value"
              searchable
              icon="mdi-shape-outline"
              :placeholder="!selectedBusinessUnitId ? 'Select a business unit first' : categorySelectItems.length === 0 ? 'No categories in this unit' : 'Search or select category'"
              :disabled="!selectedBusinessUnitId || categorySelectItems.length === 0"
            />
          </UFormField>
        </template>

        <UFormField
          label="Reason for change"
          name="reason"
          required
          description="Recorded on the ticket history and as an internal comment."
          class="reason-field"
        >
          <UTextarea
            v-model="reassignForm.reason"
            :rows="4"
            placeholder="e.g. Reassigning to Dennis for APM expertise."
            :maxlength="2000"
            class="w-full"
          />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <UButton color="neutral" variant="outline" label="Cancel" :disabled="submitting" @click="close" />
      <UButton
        color="primary"
        label="Save changes"
        :loading="submitting"
        :disabled="
          candidatesLoading ||
          (selectedAgentIds.length === 0 && !selectedGroupId) ||
          !selectedBusinessUnitId ||
          !selectedCategoryId ||
          reassignForm.reason.trim().length < 5
        "
        @click="submitReassign"
      />
    </template>
  </UModal>
</template>

<style scoped>
.reassign-body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.reassign-error {
  margin: 0;
  padding: 0.65rem 0.75rem;
  border-radius: 6px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  font-size: 0.85rem;
  line-height: 1.4;
}
.agent-primary-list {
  list-style: none;
  margin: 0.5rem 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.agent-primary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.82rem;
  color: #334155;
}
.agent-primary-name {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.agent-chip-primary {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #0d7a3a;
  background: #dcfce7;
  border-radius: 999px;
  padding: 0.05rem 0.35rem;
}
.agent-primary-btn {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #0d7a3a;
  border-radius: 4px;
  padding: 0.15rem 0.45rem;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}
.agent-primary-btn:hover {
  background: #f0fdf4;
}
.reason-field {
  margin-top: 0.25rem;
}
.field-hint {
  margin: 0.4rem 0 0;
  font-size: 0.84rem;
  line-height: 1.4;
  color: #64748b;
}
.muted {
  color: #64748b;
  font-size: 0.88rem;
  margin: 0;
}
</style>
