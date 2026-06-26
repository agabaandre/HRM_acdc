<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CbpAvatar from '../components/common/CbpAvatar.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import CbpRichTextEditor from '../components/common/CbpRichTextEditor.vue'
import { api } from '../lib/api'
import type { FormError, FormSubmitEvent } from '@nuxt/ui'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { fieldError, type SelectNumberItem } from '../lib/helpdeskForm'
import {
  canChangeTicketCategory,
  ticketStatusAllowsCategoryChange,
} from '../lib/canChangeTicketCategory'
import { formatDateTime, formatDateTimeLong } from '../lib/formatDateTime'
import { notifyError } from '../lib/notify'
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
  requester_unsatisfied_follow_up_enabled?: boolean
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
const commentForm = reactive({
  body: '',
  reopen_with_comment: true,
})
const posting = ref(false)
const resolutionNotes = ref('')
const resolving = ref(false)
const inlineImageBusy = ref(false)
const showResolveModal = ref(false)
const publishToKb = ref(false)
const kbSubject = ref('')
const resolveModalErr = ref<string | null>(null)
const cats = ref<{ id: number; name: string }[]>([])
const categoryUpdating = ref(false)

const canEditCategory = computed(() => {
  const t = ticket.value
  if (!t) {
    return false
  }
  return canChangeTicketCategory(auth.me?.profile) && ticketStatusAllowsCategoryChange(t.status)
})

const categoryItems = computed((): SelectNumberItem[] =>
  cats.value.map((c) => ({ label: c.name, value: c.id })),
)

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
  if (role === 'admin' || role === 'supervisor' || auth.me?.profile?.is_helpdesk_admin) {
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

const requesterFollowUpEnabled = computed(
  () => ticket.value?.requester_unsatisfied_follow_up_enabled !== false,
)

const canReopenWithComment = computed(
  () => isClosedForRequester.value && isRequester.value && requesterFollowUpEnabled.value,
)

const canPublishKb = computed(() => {
  const p = auth.me?.profile
  if (!p) {
    return false
  }
  return !!p.is_helpdesk_admin || p.role === 'admin' || !!p.can_manage_kb
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

async function loadCategories() {
  if (!canChangeTicketCategory(auth.me?.profile)) {
    return
  }
  try {
    const { data } = await api.get<{ data: { id: number; name: string }[] }>('/api/v1/categories')
    cats.value = Array.isArray(data.data) ? data.data : []
  } catch {
    cats.value = []
  }
}

async function updateTicketCategory(categoryId: number | undefined) {
  const t = ticket.value
  if (!t || !categoryId || categoryId === t.category?.id || categoryUpdating.value) {
    return
  }
  categoryUpdating.value = true
  try {
    const { data } = await api.patch(`/api/v1/tickets/${t.id}`, { category_id: categoryId })
    ticket.value = data.data as TicketDetail
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not update category'))
  } finally {
    categoryUpdating.value = false
  }
}

async function loadAll() {
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
    notifyError(apiErrorMessage(e, 'Failed to load ticket'))
  }
}

function validateComment(state: typeof commentForm): FormError[] {
  return [fieldError('body', state.body, 'Enter a comment')].filter(Boolean) as FormError[]
}

async function onCommentSubmit(event: FormSubmitEvent<typeof commentForm>) {
  const id = ticketId.value
  const body = event.data.body.trim()
  if (!id || !body) {
    return
  }
  posting.value = true
  try {
    const payload: Record<string, unknown> = { body }
    if (canReopenWithComment.value && commentForm.reopen_with_comment) {
      payload.reopen_ticket = true
    }
    await api.post(`/api/v1/tickets/${id}/comments`, payload)
    commentForm.body = ''
    commentForm.reopen_with_comment = true
    await loadAll()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to post comment'))
  } finally {
    posting.value = false
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
  void loadCategories()
  loadAll()
  document.addEventListener('keydown', onDocumentKeydown)
})
onUnmounted(() => {
  document.removeEventListener('keydown', onDocumentKeydown)
})
watch(ticketId, loadAll)
watch(canReopenWithComment, (can) => {
  if (can) {
    commentForm.reopen_with_comment = true
  }
})
</script>

<template>
  <div>
    <template v-if="ticket">
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
          <div v-if="ticket.category || canEditCategory" class="person-card person-card--category">
            <div class="person-meta person-meta--full">
              <span class="plabel">Category</span>
              <USelect
                v-if="canEditCategory"
                :model-value="ticket.category?.id"
                :items="categoryItems"
                :disabled="categoryUpdating || categoryItems.length === 0"
                placeholder="Select category"
                class="w-full category-select"
                value-key="value"
                @update:model-value="updateTicketCategory($event as number | undefined)"
              />
              <strong v-else class="pname">{{ ticket.category?.name || '—' }}</strong>
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
        <UButton type="button" color="primary" :disabled="resolving || inlineImageBusy" @click="openResolveModal">
          Close ticket &amp; notify requester
        </UButton>
      </section>

      <UModal
        v-model:open="showResolveModal"
        title="Close this ticket?"
        :ui="{ content: 'max-w-lg' }"
      >
        <template #body>
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

            <UForm :state="{ publishToKb, kbSubject }" class="hd-form resolve-modal-form">
              <UFormField v-if="canPublishKb" name="publishToKb">
                <UCheckbox
                  v-model="publishToKb"
                  :disabled="!hasRichTextContent(resolutionNotes)"
                  label="Publish this solution to the knowledge base"
                />
              </UFormField>

              <UFormField
                v-if="publishToKb && canPublishKb"
                label="Knowledge base subject"
                name="kbSubject"
                description="Shown as the FAQ question on the home page search."
                required
              >
                <UInput
                  v-model="kbSubject"
                  type="text"
                  maxlength="255"
                  placeholder="e.g. How to reset your VPN password"
                  :disabled="!hasRichTextContent(resolutionNotes)"
                  class="w-full"
                />
              </UFormField>
            </UForm>

            <p v-if="resolveModalErr" class="resolve-modal-err" role="alert">{{ resolveModalErr }}</p>
          </div>
        </template>
        <template #footer>
          <UButton color="neutral" variant="outline" label="Cancel" :disabled="resolving" @click="closeResolveModal" />
          <UButton
            color="primary"
            :label="resolving ? 'Closing…' : 'Confirm & close ticket'"
            :loading="resolving"
            :disabled="!canConfirmResolve"
            @click="confirmSubmitResolution"
          />
        </template>
      </UModal>

      <section v-if="canReopenWithComment" class="closed-banner closed-banner--follow-up" role="status">
        <div class="closed-banner-icon" aria-hidden="true">💬</div>
        <div class="closed-banner-copy">
          <p class="closed-banner-title">This ticket is closed</p>
          <p class="closed-banner-text">
            Review the resolution above. Add a comment below to explain what is still wrong. When you post with
            <strong>Reopen this ticket</strong> checked, your assigned agent receives one email with your comment and
            the reopen alert.
          </p>
        </div>
      </section>

      <section v-else-if="isClosedForRequester" class="closed-banner" role="status">
        <p class="closed-banner-text">
          This ticket is closed. Review the resolution above and add a comment below if you need further help.
        </p>
      </section>

      <section class="comments-section">
        <header class="comments-head">
          <h3 class="h3">Comments</h3>
          <UBadge v-if="comments.length" color="neutral" variant="subtle" size="sm">
            {{ comments.length }}
          </UBadge>
        </header>
        <ul class="comments">
          <li v-for="c in comments" :key="c.id" class="comment-item">
            <UCard variant="outline">
              <div class="citem-top">
                <UAvatar
                  :alt="c.author?.name ?? 'User'"
                  :src="c.author?.avatar_url ?? undefined"
                  size="sm"
                />
                <div class="citem-head">
                  <div class="meta">
                    <strong>{{ c.author?.name ?? 'User' }}</strong>
                    <UBadge v-if="c.is_internal" color="warning" variant="subtle" size="xs">
                      Internal
                    </UBadge>
                    <time
                      :datetime="c.created_at"
                      :title="formatDateTimeLong(c.created_at)"
                      class="comment-time"
                    >
                      {{ formatDateTime(c.created_at) }}
                    </time>
                  </div>
                  <p class="cbody">{{ c.body }}</p>
                </div>
              </div>
            </UCard>
          </li>
          <li v-if="comments.length === 0" class="comments-empty">
            <span class="comments-empty-icon" aria-hidden="true">✉️</span>
            <p>No comments yet. Be the first to add an update.</p>
          </li>
        </ul>

        <UForm
          :state="commentForm"
          :validate="validateComment"
          class="hd-form composer"
          @submit="onCommentSubmit"
        >
          <UFormField label="Add comment" name="body" required>
            <UTextarea
              id="ticket-comment-body"
              v-model="commentForm.body"
              :rows="4"
              :placeholder="
                canReopenWithComment
                  ? 'Explain what is still wrong or ask a follow-up question…'
                  : isClosedForRequester
                    ? 'Explain what is still wrong or ask a follow-up question…'
                    : 'Describe an update…'
              "
              class="w-full"
            />
          </UFormField>
          <UFormField v-if="canReopenWithComment" name="reopen_with_comment" class="reopen-check-field">
            <UCheckbox v-model="commentForm.reopen_with_comment">
              <template #label>
                <span class="reopen-check-copy">
                  <strong>I'm not satisfied — reopen this ticket</strong>
                  <span class="reopen-check-hint">Your assigned agent receives one email with your comment and the reopen alert.</span>
                </span>
              </template>
            </UCheckbox>
          </UFormField>
          <div class="composer-actions">
            <UButton
              type="submit"
              color="primary"
              :loading="posting"
              :disabled="!commentForm.body.trim()"
            >
              {{ commentForm.reopen_with_comment && canReopenWithComment ? 'Post & reopen' : 'Post comment' }}
            </UButton>
          </div>
        </UForm>
      </section>
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
  border-radius: 4px;
  border: 1px solid #fcd34d;
  background: #fffbeb;
}
.closed-banner--follow-up {
  display: flex;
  gap: 0.85rem;
  align-items: flex-start;
  border-color: #86efac;
  background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
}
.closed-banner-icon {
  font-size: 1.35rem;
  line-height: 1;
  margin-top: 0.1rem;
}
.closed-banner-copy {
  flex: 1;
  min-width: 0;
}
.closed-banner-title {
  margin: 0 0 0.35rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: #14532d;
}
.closed-banner-text {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.5;
  color: #78350f;
}
.closed-banner--follow-up .closed-banner-text {
  color: #166534;
}
.comments-section {
  margin-top: 1.25rem;
  padding-top: 1.25rem;
  border-top: 1px solid #e2e8f0;
}
.comments-head {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}
.comments-count {
  font-size: 0.75rem;
  font-weight: 700;
  color: #475569;
  background: #e2e8f0;
  border-radius: 999px;
  padding: 0.1rem 0.5rem;
}
.comments-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  padding: 1.25rem 1rem;
  border: 1px dashed #cbd5e1;
  border-radius: 4px;
  background: #f8fafc;
  color: #64748b;
  text-align: center;
}
.comments-empty p {
  margin: 0;
  font-size: 0.88rem;
}
.comments-empty-icon {
  font-size: 1.25rem;
}
.composer {
  margin-top: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.composer-label {
  font-weight: 700;
  font-size: 0.88rem;
  color: #334155;
}
.composer-input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 0.65rem 0.75rem;
  font: inherit;
  line-height: 1.45;
  resize: vertical;
  background: #fff;
}
.composer-input:focus {
  outline: none;
  border-color: var(--cdc-green, #0d7a3a);
  box-shadow: 0 0 0 3px rgba(13, 122, 58, 0.12);
}
.reopen-check {
  display: flex;
  gap: 0.65rem;
  align-items: flex-start;
  padding: 0.75rem 0.85rem;
  border-radius: 4px;
  border: 1px solid #bbf7d0;
  background: #f0fdf4;
  cursor: pointer;
}
.reopen-check input {
  margin-top: 0.2rem;
}
.reopen-check-copy {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.88rem;
  color: #14532d;
}
.reopen-check-hint {
  font-size: 0.8rem;
  color: #166534;
  font-weight: 400;
}
.composer-actions {
  display: flex;
  justify-content: flex-end;
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
  border-radius: 4px;
  background: #f8fafc;
  min-width: min(100%, 16rem);
}
.person-card--category {
  align-items: stretch;
}
.person-meta--full {
  width: 100%;
}
.person-meta .category-select {
  margin-top: 0.2rem;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
  color: #334155;
}
.resbox {
  margin-bottom: 1.25rem;
  padding: 0.85rem 1rem;
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
}
.resolve {
  margin-bottom: 1.5rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
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
.comment-item {
  margin-bottom: 0.5rem;
}
.comment-time {
  font-size: 0.8rem;
  color: #64748b;
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
.cbody {
  margin: 0;
  white-space: pre-wrap;
  font-size: 0.95rem;
  color: #1e293b;
}
.composer {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.composer-actions {
  display: flex;
  justify-content: flex-start;
}
.muted {
  color: #64748b;
  font-size: 0.95rem;
}
.err {
  color: #b91c1c;
}
</style>
