<script setup lang="ts">
import { ref, watch } from 'vue'
import CbpAvatar from '../common/CbpAvatar.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
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
const reason = ref('')
const submitting = ref(false)

function resetState(): void {
  candidates.value = []
  selectedId.value = null
  reason.value = ''
  submitting.value = false
}

function close(): void {
  resetState()
  emit('close')
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

async function submit(): Promise<void> {
  if (!props.ticket || !selectedId.value) {
    notifyError('Pick an agent first.')
    return
  }
  if (reason.value.trim().length < 5) {
    notifyError('Reason must be at least 5 characters.')
    return
  }

  submitting.value = true
  try {
    await api.post(`/api/v1/tickets/${props.ticket.id}/reassign`, {
      assignee_user_id: selectedId.value,
      reason: reason.value.trim(),
    })
    const newAgent = candidates.value.find((a) => a.id === selectedId.value)
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
  <Teleport to="body">
    <div v-if="ticket" class="modal-backdrop" @click.self="close">
      <div class="modal" role="dialog" aria-modal="true" aria-labelledby="reassign-title">
        <header class="modal-head">
          <div>
            <h2 id="reassign-title" class="modal-title">Reassign ticket</h2>
            <p class="modal-sub">
              <strong>{{ ticket.ticket_number }}</strong>
              <span v-if="ticket.subject"> — {{ ticket.subject }}</span>
            </p>
          </div>
          <button type="button" class="modal-close" aria-label="Close" @click="close">×</button>
        </header>

        <div class="modal-body">
          <section class="modal-section">
            <h3 class="modal-section-title">New assignee</h3>
            <p v-if="candidatesLoading" class="muted">Loading agents…</p>
            <p v-else-if="candidates.length === 0" class="muted">
              No other agents are available to assign this ticket to.
            </p>
            <ul v-else class="agent-list">
              <li v-for="a in candidates" :key="a.id">
                <label class="agent-choice" :class="{ 'is-checked': selectedId === a.id }">
                  <input
                    v-model="selectedId"
                    type="radio"
                    :value="a.id"
                    name="reassign-agent"
                  />
                  <CbpAvatar size="sm" :name="a.name" :image-url="a.avatar_url ?? null" />
                  <span class="agent-meta">
                    <span class="agent-name-row">{{ a.name }}</span>
                    <span class="agent-sub">
                      <span v-if="a.duty_station">{{ a.duty_station }}</span>
                      <span v-if="a.duty_station" class="dot-sep">·</span>
                      <span>{{ a.open_workload }} open</span>
                    </span>
                  </span>
                </label>
              </li>
            </ul>
          </section>

          <section class="modal-section">
            <label class="reason-label">
              <span>Reason for reassigning <span class="req">*</span></span>
              <textarea
                v-model="reason"
                rows="4"
                placeholder="e.g. Out of office for 3 days — please pick this up."
                required
                minlength="5"
                maxlength="2000"
              ></textarea>
              <span class="reason-help">
                Recorded on the ticket history and as an internal comment for the new assignee.
              </span>
            </label>
          </section>
        </div>

        <footer class="modal-foot">
          <button type="button" class="btn-secondary" :disabled="submitting" @click="close">
            Cancel
          </button>
          <button
            type="button"
            class="btn-primary"
            :disabled="submitting || !selectedId || reason.trim().length < 5"
            @click="submit"
          >
            {{ submitting ? 'Reassigning…' : 'Reassign ticket' }}
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  z-index: 70;
}
.modal {
  width: 100%;
  max-width: 560px;
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.35);
  display: flex;
  flex-direction: column;
  max-height: 92vh;
}
.modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1.15rem 0.65rem;
  border-bottom: 1px solid #f1f5f9;
}
.modal-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.modal-sub {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
  color: #475569;
  word-break: break-word;
}
.modal-close {
  background: transparent;
  border: 0;
  color: #64748b;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
}
.modal-close:hover {
  background: #f1f5f9;
}
.modal-body {
  padding: 0.9rem 1.15rem;
  overflow-y: auto;
}
.modal-section {
  margin-bottom: 1rem;
}
.modal-section:last-child {
  margin-bottom: 0;
}
.modal-section-title {
  margin: 0 0 0.4rem;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #475569;
}
.agent-list {
  list-style: none;
  margin: 0;
  padding: 0.4rem;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  max-height: 260px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
}
.agent-choice {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.5rem 0.55rem;
  border-radius: 4px;
  cursor: pointer;
  border: 1px solid transparent;
  background: #fff;
  transition: background 0.12s ease, border-color 0.12s ease;
}
.agent-choice:hover {
  background: #f8fafc;
}
.agent-choice.is-checked {
  background: rgba(13, 122, 58, 0.07);
  border-color: rgba(13, 122, 58, 0.35);
}
.agent-choice input[type='radio'] {
  margin: 0;
  accent-color: #0d7a3a;
}
.agent-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.agent-name-row {
  font-weight: 600;
  color: #0f172a;
  font-size: 0.92rem;
}
.agent-sub {
  font-size: 0.78rem;
  color: #64748b;
}
.dot-sep {
  margin: 0 0.4rem;
  color: #cbd5e1;
}
.reason-label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.84rem;
  font-weight: 600;
  color: #1f2937;
}
.req {
  color: #b91c1c;
}
.reason-label textarea {
  font-family: inherit;
  font-size: 0.9rem;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.55rem 0.7rem;
  resize: vertical;
  min-height: 100px;
}
.reason-label textarea:focus {
  outline: none;
  border-color: #0d7a3a;
  box-shadow: 0 0 0 3px rgba(13, 122, 58, 0.15);
}
.reason-help {
  font-size: 0.76rem;
  font-weight: 500;
  color: #64748b;
}
.modal-foot {
  display: flex;
  justify-content: flex-end;
  gap: 0.55rem;
  padding: 0.85rem 1.15rem 1rem;
  border-top: 1px solid #f1f5f9;
  background: #f8fafc;
  border-radius: 0 0 4px 4px;
}
.btn-secondary {
  padding: 0.5rem 0.95rem;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #1f2937;
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
}
.btn-secondary:hover {
  background: #f1f5f9;
}
.btn-primary {
  padding: 0.5rem 1.1rem;
  border-radius: 4px;
  border: 0;
  background: linear-gradient(135deg, #0d7a3a, #065f2c);
  color: #fff;
  font-weight: 700;
  font-size: 0.88rem;
  cursor: pointer;
}
.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-primary:hover:not(:disabled) {
  filter: brightness(1.05);
}
.muted {
  color: #64748b;
}
</style>
