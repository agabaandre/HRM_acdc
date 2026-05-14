<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/auth'

interface AssigneeBrief {
  id: number
  name: string
  email?: string
  avatar_url?: string | null
}

interface TicketDetail {
  id: number
  ticket_number: string
  subject: string
  description: string
  resolution_summary?: string | null
  status: string
  priority: string
  assigned_user_id: number | null
  requester_name?: string | null
  requester_email?: string | null
  assignee?: AssigneeBrief | null
}

interface CommentRow {
  id: number
  body: string
  is_internal: boolean
  created_at: string
  author?: { id: number; name: string; email: string; avatar_url?: string | null }
}

const route = useRoute()
const auth = useAuthStore()
const ticketId = computed(() => Number(route.params.id))

const ticket = ref<TicketDetail | null>(null)
const comments = ref<CommentRow[]>([])
const err = ref<string | null>(null)
const newBody = ref('')
const posting = ref(false)
const resolutionNotes = ref('')
const resolving = ref(false)

const isHtmlDescription = computed(() => {
  const d = ticket.value?.description ?? ''
  return /<[a-z][\s\S]*>/i.test(d)
})

const canSubmitResolution = computed(() => {
  const t = ticket.value
  const me = auth.me
  if (!t || !me?.profile) {
    return false
  }
  const role = me.profile.role
  if (['resolved', 'closed'].includes(t.status)) {
    return false
  }
  if (role === 'admin' || role === 'supervisor') {
    return true
  }
  if (role === 'agent' && t.assigned_user_id === me.id) {
    return true
  }
  return false
})

async function loadAll() {
  err.value = null
  const id = ticketId.value
  if (!id) {
    return
  }
  try {
    const [tRes, cRes] = await Promise.all([
      api.get(`/api/v1/tickets/${id}`),
      api.get(`/api/v1/tickets/${id}/comments`),
    ])
    ticket.value = tRes.data.data as TicketDetail
    comments.value = cRes.data.data as CommentRow[]
  } catch (e: unknown) {
    err.value = e instanceof Error ? e.message : 'Failed to load ticket'
  }
}

async function postComment() {
  const id = ticketId.value
  const body = newBody.value.trim()
  if (!id || !body) {
    return
  }
  posting.value = true
  err.value = null
  try {
    await api.post(`/api/v1/tickets/${id}/comments`, { body })
    newBody.value = ''
    await loadAll()
  } catch (e: unknown) {
    err.value = e instanceof Error ? e.message : 'Failed to post comment'
  } finally {
    posting.value = false
  }
}

async function submitResolution() {
  const id = ticketId.value
  const summary = resolutionNotes.value.trim()
  if (!id || !summary) {
    return
  }
  resolving.value = true
  err.value = null
  try {
    await api.post(`/api/v1/tickets/${id}/submit-resolution`, { resolution_summary: summary })
    resolutionNotes.value = ''
    await loadAll()
  } catch (e: unknown) {
    err.value = e instanceof Error ? e.message : 'Could not submit resolution'
  } finally {
    resolving.value = false
  }
}

onMounted(loadAll)
watch(ticketId, loadAll)
</script>

<template>
  <div>
    <p v-if="err" class="err">{{ err }}</p>
    <template v-else-if="ticket">
      <CbpPageHeading :title="ticket.ticket_number" back-to="/tickets" back-label="← Tickets">
        <template #lede>
          <span class="pill">{{ ticket.status }}</span>
          <span class="pill low">{{ ticket.priority }}</span>
          <span class="subj-inline">{{ ticket.subject }}</span>
        </template>
      </CbpPageHeading>
      <div class="cbp-card detail-body">
        <section class="people-strip">
          <div v-if="ticket.requester_name || ticket.requester_email" class="person-card">
            <CbpAvatar
              size="md"
              :name="ticket.requester_name || ticket.requester_email || 'Requester'"
              :image-url="null"
            />
            <div class="person-meta">
              <span class="plabel">Requester</span>
              <strong class="pname">{{ ticket.requester_name || '—' }}</strong>
              <span v-if="ticket.requester_email" class="pemail">{{ ticket.requester_email }}</span>
            </div>
          </div>
          <div v-if="ticket.assignee" class="person-card">
            <CbpAvatar
              size="md"
              :name="ticket.assignee.name"
              :image-url="ticket.assignee.avatar_url ?? null"
            />
            <div class="person-meta">
              <span class="plabel">Assigned to</span>
              <strong class="pname">{{ ticket.assignee.name }}</strong>
              <span v-if="ticket.assignee.email" class="pemail">{{ ticket.assignee.email }}</span>
            </div>
          </div>
        </section>
      <section class="desc">
        <div v-if="isHtmlDescription" class="html" v-html="ticket.description" />
        <pre v-else class="pre">{{ ticket.description }}</pre>
      </section>

      <section v-if="ticket.resolution_summary" class="resbox">
        <h3 class="h3">Latest resolution notes</h3>
        <p class="res">{{ ticket.resolution_summary }}</p>
      </section>

      <section v-if="canSubmitResolution" class="resolve">
        <h3 class="h3">Submit resolution</h3>
        <p class="muted small">
          Describe what was fixed. The requester is emailed; if confirmation is enabled in settings they must click the link to close the ticket.
        </p>
        <textarea v-model="resolutionNotes" rows="5" placeholder="What was done to resolve this issue…" />
        <button type="button" class="primary" :disabled="resolving" @click="submitResolution">
          {{ resolving ? 'Sending…' : 'Mark resolved & notify requester' }}
        </button>
      </section>

      <h3 class="h3">Comments</h3>
      <ul class="comments">
        <li v-for="c in comments" :key="c.id" class="citem">
          <div class="citem-top">
            <CbpAvatar size="sm" :name="c.author?.name ?? 'User'" :image-url="c.author?.avatar_url ?? null" />
            <div class="citem-head">
              <div class="meta">
                <strong>{{ c.author?.name ?? 'User' }}</strong>
                <span v-if="c.is_internal" class="tag">internal</span>
                <time :datetime="c.created_at">{{ c.created_at }}</time>
              </div>
              <p class="cbody">{{ c.body }}</p>
            </div>
          </div>
        </li>
        <li v-if="comments.length === 0" class="muted">No comments yet.</li>
      </ul>

      <form class="composer" @submit.prevent="postComment">
        <label>Add comment</label>
        <textarea v-model="newBody" rows="4" required placeholder="Describe an update…" />
        <button type="submit" class="primary" :disabled="posting">{{ posting ? 'Posting…' : 'Post' }}</button>
      </form>
      </div>
    </template>
    <p v-else class="muted">Loading…</p>
  </div>
</template>

<style scoped>
.subj-inline {
  display: inline;
  font-weight: 600;
  color: #334155;
  margin-left: 0.35rem;
}
.detail-body {
  margin-top: 0.5rem;
}
.pill {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  background: #e2e8f0;
  color: #334155;
}
.pill.low {
  background: #dbeafe;
  color: #1e40af;
}
.people-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.person-card {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  padding: 0.65rem 0.85rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  min-width: min(100%, 16rem);
}
.person-meta {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}
.plabel {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  font-weight: 700;
}
.pname {
  font-size: 0.95rem;
  color: #0f172a;
}
.pemail {
  font-size: 0.8rem;
  color: #475569;
  word-break: break-all;
}
.desc {
  margin-bottom: 1.5rem;
}
.html {
  padding: 1rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.95rem;
  line-height: 1.55;
  color: #334155;
}
.html :deep(img) {
  max-width: 100%;
  height: auto;
}
.pre {
  white-space: pre-wrap;
  word-break: break-word;
  font-family: inherit;
  font-size: 0.95rem;
  line-height: 1.5;
  margin: 0;
  padding: 1rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  color: #334155;
}
.resbox {
  margin-bottom: 1.25rem;
  padding: 0.85rem 1rem;
  border-radius: 8px;
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
}
.res {
  margin: 0;
  white-space: pre-wrap;
  font-size: 0.92rem;
}
.resolve {
  margin-bottom: 1.5rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.small {
  font-size: 0.82rem;
  margin: 0;
}
.h3 {
  font-size: 1rem;
  margin: 0 0 0.75rem;
  color: #0f172a;
}
.comments {
  list-style: none;
  padding: 0;
  margin: 0 0 1.5rem;
}
.citem {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  margin-bottom: 0.5rem;
  background: #fff;
}
.citem-top {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
}
.citem-head {
  flex: 1;
  min-width: 0;
}
.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.75rem;
  font-size: 0.8rem;
  color: #64748b;
  margin-bottom: 0.35rem;
}
.tag {
  background: #fef3c7;
  color: #92400e;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  text-transform: uppercase;
}
.cbody {
  margin: 0;
  white-space: pre-wrap;
  font-size: 0.95rem;
  color: #1e293b;
}
.composer {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.composer label {
  font-weight: 600;
  font-size: 0.85rem;
  color: #334155;
}
textarea {
  padding: 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-family: inherit;
}
.primary {
  align-self: flex-start;
  padding: 0.5rem 1rem;
  background: #119a48;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
}
.primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.muted {
  color: #64748b;
  font-size: 0.95rem;
}
.err {
  color: #b91c1c;
}
</style>
