<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { minLengthError } from '../../lib/helpdeskForm'
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

const props = defineProps<{
  ticket: ReassignTicketRef | null
}>()

const emit = defineEmits<{
  close: []
  reassigned: [payload: { ticketId: number; agentName?: string }]
}>()

const candidates = ref<EligibleAgent[]>([])
const candidatesLoading = ref(false)
const selectedId = ref<number | null>(null)
const agentSearch = ref('')
const resultsOpen = ref(false)
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
  props.ticket ? `Reassign ${props.ticket.ticket_number}` : 'Reassign ticket',
)

const modalDescription = computed(() => props.ticket?.subject ?? undefined)

const selectedAgent = computed(() =>
  candidates.value.find((a) => a.id === selectedId.value) ?? null,
)

const filteredAgents = computed(() => {
  const q = agentSearch.value.trim().toLowerCase()
  if (!q) {
    return candidates.value
  }

  return candidates.value.filter((a) => {
    const haystack = [a.name, a.email, a.duty_station ?? ''].join(' ').toLowerCase()
    return haystack.includes(q)
  })
})

function resetState(): void {
  candidates.value = []
  selectedId.value = null
  agentSearch.value = ''
  resultsOpen.value = false
  reassignForm.reason = ''
  submitting.value = false
}

function close(): void {
  resetState()
  emit('close')
}

function onSearchFocus(): void {
  resultsOpen.value = true
}

function pickAgent(agent: EligibleAgent): void {
  selectedId.value = agent.id
  agentSearch.value = agent.name
  resultsOpen.value = false
}

function agentMeta(agent: EligibleAgent): string {
  const parts: string[] = []
  if (agent.email) {
    parts.push(agent.email)
  }
  if (agent.duty_station) {
    parts.push(agent.duty_station)
  }
  parts.push(`${agent.open_workload} open`)
  return parts.join(' · ')
}

async function loadCandidates(ticketId: number): Promise<void> {
  candidatesLoading.value = true
  try {
    const { data } = await api.get<{ data: { agents: EligibleAgent[] } }>(
      `/api/v1/tickets/${ticketId}/eligible-agents`,
    )
    candidates.value = Array.isArray(data.data?.agents) ? data.data.agents : []
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not load agents.'))
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

watch(agentSearch, (value) => {
  if (!value.trim()) {
    selectedId.value = null
  } else if (selectedAgent.value && value !== selectedAgent.value.name) {
    selectedId.value = null
  }
  resultsOpen.value = true
})

function validateReassign(state: typeof reassignForm): FormError[] {
  const errors: FormError[] = []
  if (!selectedId.value) {
    errors.push({ name: 'assignee', message: 'Pick an agent from the search results first.' })
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
  if (!props.ticket || !selectedId.value) {
    return
  }

  submitting.value = true
  try {
    await api.post(`/api/v1/tickets/${props.ticket.id}/reassign`, {
      assignee_user_id: selectedId.value,
      reason: reassignForm.reason.trim(),
    })
    const newAgent = selectedAgent.value
    notifySuccess(
      `Reassigned ${props.ticket.ticket_number}${newAgent ? ` to ${newAgent.name}` : ''}.`,
    )
    emit('reassigned', { ticketId: props.ticket.id, agentName: newAgent?.name })
    close()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Reassignment failed.'))
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
        <p v-if="candidatesLoading" class="muted">Loading agents…</p>
        <p v-else-if="candidates.length === 0" class="muted">
          No other agents are available to assign this ticket to.
        </p>
        <template v-else>
          <UFormField label="Search agents" name="assignee" required>
            <UInput
              v-model="agentSearch"
              type="search"
              icon="i-lucide-search"
              placeholder="Type name, email, or duty station…"
              autocomplete="off"
              @focus="onSearchFocus"
              class="w-full"
            />
          </UFormField>

          <ul
            v-if="resultsOpen && filteredAgents.length"
            class="agent-results"
            role="listbox"
            aria-label="Agent results"
          >
            <li
              v-for="a in filteredAgents"
              :key="a.id"
              role="option"
              class="agent-result"
              :class="{ selected: selectedId === a.id }"
              :aria-selected="selectedId === a.id"
              @mousedown.prevent
              @click="pickAgent(a)"
            >
              <UAvatar :alt="a.name" :src="a.avatar_url ?? undefined" size="sm" />
              <span class="agent-result-text">
                <span class="agent-name">{{ a.name }}</span>
                <span class="agent-meta">{{ agentMeta(a) }}</span>
              </span>
            </li>
          </ul>
          <p v-else-if="resultsOpen && agentSearch.trim()" class="muted">
            No agents match your search.
          </p>

          <UCard v-if="selectedAgent" variant="subtle" class="selected-card">
            <div class="selected-row">
              <UAvatar
                :alt="selectedAgent.name"
                :src="selectedAgent.avatar_url ?? undefined"
                size="sm"
              />
              <div>
                <p class="selected-label">Selected assignee</p>
                <p class="agent-name">{{ selectedAgent.name }}</p>
                <p class="agent-meta">{{ agentMeta(selectedAgent) }}</p>
              </div>
            </div>
          </UCard>
        </template>

        <UFormField
          label="Reason for reassigning"
          name="reason"
          required
          description="Recorded on the ticket history and as an internal comment for the new assignee."
          class="reason-field"
        >
          <UTextarea
            v-model="reassignForm.reason"
            :rows="4"
            placeholder="e.g. Out of office for 3 days — please pick this up."
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
        label="Reassign ticket"
        :loading="submitting"
        :disabled="!selectedId || reassignForm.reason.trim().length < 5"
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
.agent-results {
  list-style: none;
  margin: 0;
  padding: 0.25rem;
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid var(--hd-line);
  border-radius: 4px;
  background: #fff;
}
.agent-result {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.5rem 0.55rem;
  border-radius: 4px;
  cursor: pointer;
}
.agent-result:hover,
.agent-result.selected {
  background: rgba(17, 154, 72, 0.08);
}
.agent-result-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.agent-name {
  font-weight: 600;
  color: #0f172a;
  font-size: 0.92rem;
}
.agent-meta {
  font-size: 0.78rem;
  color: #64748b;
  word-break: break-word;
}
.selected-card {
  margin-top: 0.25rem;
}
.selected-row {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
}
.selected-label {
  margin: 0 0 0.15rem;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #0d7a3a;
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
