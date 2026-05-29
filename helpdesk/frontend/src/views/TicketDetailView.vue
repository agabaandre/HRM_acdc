<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import CbpRichTextEditor from '../components/common/CbpRichTextEditor.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { hasRichTextContent, isHtmlContent } from '../lib/richText'
import { useAuthStore } from '../stores/auth'

interface AssigneeBrief {
  id: number
  name: string
  email?: string
  avatar_url?: string | null
  work_mode?: 'remote' | 'onsite' | null
}

interface TicketAttachment {
  id: number
  url: string
  original_name: string
  mime_type?: string | null
}

interface TicketCategory {
  id: number
  name: string
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
  requester_staff_id?: number | null
  requester_name?: string | null
  requester_email?: string | null
  assignee?: AssigneeBrief | null
  attachments?: TicketAttachment[]
  category?: TicketCategory | null
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
const reopening = ref(false)
const inlineImageBusy = ref(false)
const showResolveModal = ref(false)
const publishToKb = ref(false)
const kbSubject = ref('')
const resolveModalErr = ref<string | null>(null)

const isHtmlDescription = computed(() => isHtmlContent(ticket.value?.description))
const isHtmlResolution = computed(() => isHtmlContent(ticket.value?.resolution_summary ?? null))

/** Files uploaded with the request (excludes inline editor images under …/inline/). */
const requestAttachments = computed(() => {
  const list = ticket.value?.attachments ?? []
  return list.filter((a) => !a.url.includes('/inline/'))
})

function isImageAttachment(a: TicketAttachment): boolean {
  const mime = (a.mime_type ?? '').toLowerCase()
  if (mime.startsWith('image/')) {
    return true
  }
  const name = a.original_name.toLowerCase()
  return /\.(jpe?g|png|gif|webp)$/i.test(name)
}

const previewAttachment = ref<TicketAttachment | null>(null)

function openImagePreview(a: TicketAttachment) {
  previewAttachment.value = a
}

function closeImagePreview() {
  previewAttachment.value = null
}

function onPreviewKeydown(ev: KeyboardEvent) {
  if (ev.key === 'Escape' && previewAttachment.value) {
    closeImagePreview()
  }
}

const canSubmitResolution = computed(() => {
  const t = ticket.value
  const me = auth.me
  if (!t || !me?.profile) {
    return false
  }
  const role = me.profile.role
  if (['resolved', 'closed', 'awaiting_requester_confirmation'].includes(t.status)) {
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

const isRequester = computed(() => {
  const me = auth.me?.profile
  const t = ticket.value
  if (!me || !t || me.role !== 'user' || me.staff_id == null) {
    return false
  }
  return t.requester_staff_id != null && me.staff_id === t.requester_staff_id
})

const isClosedForRequester = computed(() => {
  const t = ticket.value
  if (!t || !isRequester.value) {
    return false
  }
  return ['closed', 'resolved', 'awaiting_requester_confirmation'].includes(t.status)
})

const canPublishKb = computed(() => {
  const p = auth.me?.profile
  if (!p) {
    return false
  }
  return p.role === 'admin' || !!p.can_manage_kb
})

const canConfirmResolve = computed(() => {
  if (!hasRichTextContent(resolutionNotes.value)) {
    return false
  }
  if (publishToKb.value && !kbSubject.value.trim()) {
    return false
  }
  return true
})

function openResolveModal() {
  resolveModalErr.value = null
  publishToKb.value = false
  kbSubject.value = ticket.value?.subject?.trim() ?? ''
  showResolveModal.value = true
  if (!hasRichTextContent(resolutionNotes.value)) {
    resolveModalErr.value =
      'Please describe what was fixed in the resolution editor above before closing this ticket.'
  }
}

function closeResolveModal() {
  showResolveModal.value = false
  resolveModalErr.value = null
}

function onResolveModalKeydown(ev: KeyboardEvent) {
  if (ev.key === 'Escape' && showResolveModal.value) {
    closeResolveModal()
  }
}

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

async function reopenTicket() {
  const id = ticketId.value
  if (!id) {
    return
  }
  reopening.value = true
  err.value = null
  try {
    await api.post(`/api/v1/tickets/${id}/reopen`)
    await loadAll()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Could not reopen ticket')
  } finally {
    reopening.value = false
  }
}

async function confirmSubmitResolution() {
  const id = ticketId.value
  const summary = resolutionNotes.value
  if (!id || !hasRichTextContent(summary)) {
    resolveModalErr.value =
      'Please describe what was fixed in the resolution editor above before closing this ticket.'
    return
  }
  if (publishToKb.value && !kbSubject.value.trim()) {
    resolveModalErr.value = 'Enter a subject for the knowledge base article.'
    return
  }
  resolving.value = true
  resolveModalErr.value = null
  err.value = null
  try {
    const payload: Record<string, unknown> = { resolution_summary: summary }
    if (publishToKb.value && canPublishKb.value) {
      payload.publish_to_kb = true
      payload.kb_question = kbSubject.value.trim()
    }
    await api.post(`/api/v1/tickets/${id}/submit-resolution`, payload)
    resolutionNotes.value = ''
    publishToKb.value = false
    kbSubject.value = ''
    showResolveModal.value = false
    await loadAll()
  } catch (e: unknown) {
    resolveModalErr.value = apiErrorMessage(e, 'Could not submit resolution')
  } finally {
    resolving.value = false
  }
}

function onDocumentKeydown(ev: KeyboardEvent) {
  onPreviewKeydown(ev)
  onResolveModalKeydown(ev)
}

onMounted(() => {
  loadAll()
  document.addEventListener('keydown', onDocumentKeydown)
})
onUnmounted(() => {
  document.removeEventListener('keydown', onDocumentKeydown)
})
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
              <strong class="pname">
                {{ ticket.assignee.name }}
                <span
                  v-if="ticket.assignee.work_mode === 'remote'"
                  class="wm-pill wm-remote"
                  title="This agent is currently working remotely"
                >Remote</span>
                <span
                  v-else-if="ticket.assignee.work_mode === 'onsite'"
                  class="wm-pill wm-onsite"
                  title="This agent is currently working from the office"
                >Onsite</span>
              </strong>
              <span v-if="ticket.assignee.email" class="pemail">{{ ticket.assignee.email }}</span>
            </div>
          </div>
        </section>
      <section class="desc">
        <div v-if="isHtmlDescription" class="html rich-text-content" v-html="ticket.description" />
        <pre v-else class="pre">{{ ticket.description }}</pre>
      </section>

      <section v-if="requestAttachments.length" class="attach-section">
        <h3 class="h3">Request attachments</h3>
        <ul class="attach-list">
          <li v-for="a in requestAttachments" :key="a.id" class="attach-item">
            <button
              v-if="isImageAttachment(a)"
              type="button"
              class="attach-link attach-link--image"
              :title="`Preview ${a.original_name}`"
              @click="openImagePreview(a)"
            >
              <img
                class="attach-thumb"
                :src="a.url"
                :alt="a.original_name"
                loading="lazy"
              />
              <span class="attach-name">{{ a.original_name }}</span>
            </button>
            <a
              v-else
              class="attach-link"
              :href="a.url"
              target="_blank"
              rel="noopener noreferrer"
              :title="a.original_name"
            >
              <span class="attach-file-icon" aria-hidden="true">📄</span>
              <span class="attach-name">{{ a.original_name }}</span>
            </a>
          </li>
        </ul>
      </section>

      <Teleport to="body">
        <div
          v-if="previewAttachment"
          class="img-modal-backdrop"
          role="presentation"
          @click.self="closeImagePreview"
        >
          <div
            class="img-modal"
            role="dialog"
            aria-modal="true"
            :aria-label="previewAttachment.original_name"
          >
            <header class="img-modal-head">
              <p class="img-modal-title">{{ previewAttachment.original_name }}</p>
              <button
                type="button"
                class="img-modal-close"
                aria-label="Close preview"
                @click="closeImagePreview"
              >
                ×
              </button>
            </header>
            <div class="img-modal-body">
              <img
                :src="previewAttachment.url"
                :alt="previewAttachment.original_name"
                class="img-modal-img"
              />
            </div>
            <footer class="img-modal-foot">
              <a
                class="primary"
                :href="previewAttachment.url"
                :download="previewAttachment.original_name"
                target="_blank"
                rel="noopener noreferrer"
              >
                Download
              </a>
              <button type="button" class="ghost" @click="closeImagePreview">Close</button>
            </footer>
          </div>
        </div>
      </Teleport>

      <section v-if="ticket.resolution_summary" class="resbox">
        <h3 class="h3">Latest resolution notes</h3>
        <div v-if="isHtmlResolution" class="res-html rich-text-content" v-html="ticket.resolution_summary" />
        <p v-else class="res">{{ ticket.resolution_summary }}</p>
      </section>

      <section v-if="canSubmitResolution" class="resolve">
        <h3 class="h3">Submit resolution</h3>
        <p class="muted small">
          Describe what was fixed. Use the toolbar to format text, add lists, paste screenshots, embed video, or attach links. The requester is emailed; if confirmation is enabled in settings they must click the link to close the ticket.
        </p>
        <CbpRichTextEditor
          v-model="resolutionNotes"
          variant="full"
          :ticket-id="ticket.id"
          :min-rows="6"
          placeholder="Describe what was fixed. Supports headings, lists, links, code blocks, screenshots, and video embeds…"
          @uploading="inlineImageBusy = $event"
        />
        <button type="button" class="primary" :disabled="resolving || inlineImageBusy" @click="openResolveModal">
          Close ticket &amp; notify requester
        </button>
      </section>

      <Teleport to="body">
        <div
          v-if="showResolveModal"
          class="resolve-modal-backdrop"
          role="presentation"
          @click.self="closeResolveModal"
        >
          <div class="resolve-modal" role="dialog" aria-modal="true" aria-labelledby="resolve-modal-title">
            <header class="resolve-modal-head">
              <h3 id="resolve-modal-title" class="resolve-modal-title">Close this ticket?</h3>
              <button
                type="button"
                class="resolve-modal-close"
                aria-label="Close"
                @click="closeResolveModal"
              >
                ×
              </button>
            </header>
            <div class="resolve-modal-body">
              <p
                v-if="!hasRichTextContent(resolutionNotes)"
                class="resolve-modal-warn"
                role="alert"
              >
                Please describe what was fixed in the <strong>Submit resolution</strong> editor above before you continue.
              </p>
              <p v-else class="resolve-modal-ok muted small">
                The ticket will be <strong>closed</strong> and the requester will receive an email with your
                resolution notes and a link to review the ticket, add comments, or reopen if the issue persists.
                <span v-if="ticket.category?.name">
                  If you publish to the knowledge base, the article will appear under
                  <strong>{{ ticket.category.name }}</strong>.
                </span>
              </p>

              <label v-if="canPublishKb" class="resolve-kb-check">
                <input v-model="publishToKb" type="checkbox" :disabled="!hasRichTextContent(resolutionNotes)" />
                <span>Publish this solution to the knowledge base</span>
              </label>

              <label v-if="publishToKb && canPublishKb" class="resolve-kb-subject">
                <span>Knowledge base subject</span>
                <input
                  v-model="kbSubject"
                  type="text"
                  maxlength="255"
                  required
                  placeholder="e.g. How to reset your VPN password"
                  :disabled="!hasRichTextContent(resolutionNotes)"
                />
                <span class="resolve-kb-hint muted small">Shown as the FAQ question on the home page search.</span>
              </label>

              <p v-if="resolveModalErr" class="resolve-modal-err" role="alert">{{ resolveModalErr }}</p>
            </div>
            <footer class="resolve-modal-foot">
              <button type="button" class="ghost" :disabled="resolving" @click="closeResolveModal">
                Cancel
              </button>
              <button
                type="button"
                class="primary"
                :disabled="resolving || !canConfirmResolve"
                @click="confirmSubmitResolution"
              >
                {{ resolving ? 'Closing…' : 'Confirm & close ticket' }}
              </button>
            </footer>
          </div>
        </div>
      </Teleport>

      <section v-if="isClosedForRequester" class="closed-banner" role="status">
        <p class="closed-banner-text">
          This ticket is closed. Review the resolution above. If the issue is not fixed, add a comment below or
          reopen the ticket so support can continue.
        </p>
        <button type="button" class="primary" :disabled="reopening" @click="reopenTicket">
          {{ reopening ? 'Reopening…' : 'Reopen ticket' }}
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
        <textarea
          v-model="newBody"
          rows="4"
          required
          :placeholder="isClosedForRequester ? 'Explain what is still wrong or ask a follow-up question…' : 'Describe an update…'"
        />
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
.closed-banner {
  margin: 1rem 0 1.25rem;
  padding: 1rem 1.1rem;
  border-radius: 10px;
  border: 1px solid #fcd34d;
  background: #fffbeb;
}
.closed-banner-text {
  margin: 0 0 0.75rem;
  font-size: 0.9rem;
  line-height: 1.5;
  color: #78350f;
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
.wm-pill {
  display: inline-block;
  margin-left: 0.4rem;
  padding: 0.05rem 0.45rem;
  border-radius: 999px;
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  vertical-align: middle;
}
.wm-pill.wm-remote {
  background: #e0f2fe;
  color: #0369a1;
  border: 1px solid #bae6fd;
}
.wm-pill.wm-onsite {
  background: #dcfce7;
  color: #15803d;
  border: 1px solid #bbf7d0;
}
.desc {
  margin-bottom: 1.5rem;
}
.attach-section {
  margin-bottom: 1.5rem;
}
.attach-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}
.attach-item {
  margin: 0;
}
.attach-link {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.4rem;
  padding: 0.65rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  text-decoration: none;
  color: #0f172a;
  max-width: 12rem;
  transition: border-color 0.15s, box-shadow 0.15s;
  font: inherit;
  cursor: pointer;
}
.attach-link:hover,
.attach-link--image:focus-visible {
  border-color: #94a3b8;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}
.attach-link--image {
  width: 100%;
}
.img-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  background: rgba(15, 23, 42, 0.72);
  backdrop-filter: blur(4px);
}
.img-modal {
  display: flex;
  flex-direction: column;
  width: min(92vw, 56rem);
  max-height: min(90vh, 48rem);
  margin: auto;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.35);
  overflow: hidden;
}
.img-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}
.img-modal-title {
  margin: 0;
  font-size: 0.9rem;
  font-weight: 600;
  color: #0f172a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.img-modal-close {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: #64748b;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
}
.img-modal-close:hover {
  background: #e2e8f0;
  color: #0f172a;
}
.img-modal-body {
  flex: 1;
  min-height: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: #0f172a;
}
.img-modal-img {
  display: block;
  max-width: 100%;
  max-height: min(68vh, 36rem);
  width: auto;
  height: auto;
  object-fit: contain;
  border-radius: 4px;
}
.img-modal-foot {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  padding: 0.85rem 1rem;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}
.img-modal-foot .primary {
  text-decoration: none;
}
.resolve-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 10001;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  background: rgba(15, 23, 42, 0.72);
  backdrop-filter: blur(4px);
}
.resolve-modal {
  display: flex;
  flex-direction: column;
  width: min(92vw, 28rem);
  margin: auto;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.35);
  overflow: hidden;
}
.resolve-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1rem 0.5rem;
  border-bottom: 1px solid #e2e8f0;
}
.resolve-modal-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.resolve-modal-close {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: #64748b;
  font-size: 1.5rem;
  line-height: 1;
  cursor: pointer;
}
.resolve-modal-close:hover {
  background: #e2e8f0;
  color: #0f172a;
}
.resolve-modal-body {
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.resolve-modal-warn {
  margin: 0;
  padding: 0.65rem 0.75rem;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 0.88rem;
  line-height: 1.45;
}
.resolve-modal-ok {
  margin: 0;
}
.resolve-modal-err {
  margin: 0;
  font-size: 0.85rem;
  color: #b91c1c;
}
.resolve-kb-check {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 0.88rem;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
}
.resolve-kb-check input {
  margin-top: 0.2rem;
}
.resolve-kb-subject {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
}
.resolve-kb-subject input {
  font-weight: 400;
  padding: 0.5rem 0.65rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
}
.resolve-kb-hint {
  font-weight: 400;
}
.resolve-modal-foot {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.65rem;
  padding: 0.85rem 1rem;
  border-top: 1px solid #e2e8f0;
  background: #f8fafc;
}
.attach-thumb {
  width: 100%;
  max-height: 8rem;
  object-fit: contain;
  border-radius: 6px;
  background: #fff;
}
.attach-file-icon {
  font-size: 2rem;
  line-height: 1;
}
.attach-name {
  font-size: 0.78rem;
  font-weight: 600;
  text-align: center;
  word-break: break-word;
  line-height: 1.3;
  color: #334155;
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
.res-html {
  font-size: 0.95rem;
  line-height: 1.55;
  color: #14532d;
}
.res-html :deep(img) {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
  margin: 0.35rem 0;
}
.res-html :deep(p) {
  margin: 0 0 0.5rem;
}
.res-html :deep(p:last-child) {
  margin-bottom: 0;
}
.res-html :deep(blockquote) {
  border-left: 3px solid #16a34a;
  margin: 0.5rem 0;
  padding: 0.15rem 0.85rem;
  color: #166534;
}
.res-html :deep(pre),
.res-html :deep(code) {
  background: #ecfdf5;
  border-radius: 4px;
}
.res-html :deep(pre) {
  padding: 0.6rem 0.75rem;
  overflow-x: auto;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.85rem;
}
.res-html :deep(a) {
  color: #047857;
  text-decoration: underline;
}
.res-html :deep(iframe.ql-video) {
  width: 100%;
  aspect-ratio: 16 / 9;
  border: 0;
  border-radius: 8px;
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
