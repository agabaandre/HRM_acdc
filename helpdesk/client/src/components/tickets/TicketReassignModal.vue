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

interface CategoryOption {
  id: number
  name: string
}

interface AssignmentCurrent {
  assignee_user_ids: number[]
  assigned_group_id: number | null
  priority: TicketPriority
  category_id: number
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
const categories = ref<CategoryOption[]>([])
const candidatesLoading = ref(false)
const selectedAgentIds = ref<number[]>([])
const selectedGroupId = ref<number | null>(null)
const selectedPriority = ref<TicketPriority>('medium')
const selectedCategoryId = ref<number | null>(null)
const reassignForm = reactive({ reason: '' })
const submitting = ref(false)

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

const categorySelectItems = computed(() =>
  categories.value.map((c) => ({
    label: c.name,
    value: c.id,
  })),
)

const selectedAgents = computed(() =>
  agents.value.filter((a) => selectedAgentIds.value.includes(a.id)),
)

function resetState(): void {
  agents.value = []
  groups.value = []
  categories.value = []
  selectedAgentIds.value = []
  selectedGroupId.value = null
  selectedPriority.value = 'medium'
  selectedCategoryId.value = null
  reassignForm.reason = ''
  submitting.value = false
}

function close(): void {
  resetState()
  emit('close')
}

async function loadCategories(): Promise<void> {
  try {
    const { data } = await api.get<{ data: CategoryOption[] }>('/api/v1/categories')
    categories.value = Array.isArray(data.data) ? data.data : []
  } catch {
    categories.value = []
  }
}

async function loadCandidates(ticketId: number): Promise<void> {
  candidatesLoading.value = true
  try {
    const [{ data }, _] = await Promise.all([
      api.get<{
        data: {
          current?: AssignmentCurrent
          agents: EligibleAgent[]
          groups: SupportGroupOption[]
        }
      }>(`/api/v1/tickets/${ticketId}/eligible-agents`),
      loadCategories(),
    ])

    agents.value = Array.isArray(data.data?.agents) ? data.data.agents : []
    groups.value = Array.isArray(data.data?.groups) ? data.data.groups : []

    const current = data.data?.current
    selectedAgentIds.value = Array.isArray(current?.assignee_user_ids)
      ? [...current.assignee_user_ids]
      : []
    selectedGroupId.value = current?.assigned_group_id ?? null
    selectedPriority.value = current?.priority ?? 'medium'
    selectedCategoryId.value = current?.category_id ?? null
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not load assignment options.'))
    close()
  } finally {
    candidatesLoading.value = false
  }
}

watch(
  () => props.ticket,
  (ticket) => {
    resetState()
    if (ticket) {
      void loadCandidates(ticket.id)
    }
  },
  { immediate: true },
)

function validateReassign(state: typeof reassignForm): FormError[] {
  const errors: FormError[] = []
  if (selectedAgentIds.value.length === 0 && !selectedGroupId.value) {
    errors.push({ name: 'assignee', message: 'Select at least one agent or a support group.' })
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
  if (selectedAgentIds.value.length === 0 && !selectedGroupId.value) {
    return
  }
  if (!selectedCategoryId.value) {
    return
  }

  submitting.value = true
  try {
    await api.post(`/api/v1/tickets/${props.ticket.id}/reassign`, {
      assignee_user_ids: selectedAgentIds.value,
      assignee_group_id: selectedGroupId.value,
      priority: selectedPriority.value,
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
    notifyError(apiErrorMessage(e, 'Configuration update failed.'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <UModal
    v-if="ticket"
    v-model:open="modalOpen"
    :title="modalTitle"
    :description="modalDescription"
    :ui="{ content: 'max-w-lg' }"
  >
    <template #body>
      <UForm
        id="reassign-form"
        :state="reassignForm"
        :validate="validateReassign"
        class="hd-form reassign-body"
        @submit="onReassignSubmit"
      >
        <p v-if="candidatesLoading" class="muted">Loading configuration options…</p>
        <template v-else>
          <UFormField
            label="Agents"
            name="assignee"
            description="Select one or more agents. The first selected agent is the primary assignee."
          >
            <USelectMenu
              v-model="selectedAgentIds"
              :items="agentSelectItems"
              value-key="value"
              multiple
              searchable
              icon="mdi-account-multiple"
              placeholder="Search and select agents…"
              :disabled="agents.length === 0"
            />
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

          <UFormField label="Category" name="category" required>
            <USelect
              v-model="selectedCategoryId"
              :items="categorySelectItems"
              value-key="value"
              searchable
              icon="mdi-shape-outline"
              placeholder="Select category"
              :disabled="categories.length === 0"
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
            placeholder="e.g. Adding a second agent for coverage; raising priority for SLA."
            :maxlength="2000"
            class="w-full"
          />
        </UFormField>
      </UForm>
    </template>

    <template #footer>
      <UButton color="neutral" variant="outline" label="Cancel" :disabled="submitting" @click="close" />
      <UButton
        type="submit"
        form="reassign-form"
        color="primary"
        label="Save changes"
        :loading="submitting"
        :disabled="
          (selectedAgentIds.length === 0 && !selectedGroupId) ||
          !selectedCategoryId ||
          reassignForm.reason.trim().length < 5
        "
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
.reason-field {
  margin-top: 0.25rem;
}
.muted {
  color: #64748b;
  font-size: 0.88rem;
  margin: 0;
}
</style>
