<script setup lang="ts">
import { QuillEditor, Quill } from '@vueup/vue-quill'
import '@vueup/vue-quill/dist/vue-quill.snow.css'
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
  work_mode?: 'remote' | 'onsite' | null
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
const resolutionEditor = ref<InstanceType<typeof QuillEditor> | null>(null)
const inlineImageBusy = ref(false)
const inlineImageHint = ref<string | null>(null)

const MAX_INLINE_IMAGE_BYTES = 10 * 1024 * 1024
const ALLOWED_INLINE_MIME = new Set([
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/gif',
  'image/webp',
])

function isHtml(content: string | null | undefined): boolean {
  return !!content && /<[a-z][\s\S]*>/i.test(content)
}

const isHtmlDescription = computed(() => isHtml(ticket.value?.description))
const isHtmlResolution = computed(() => isHtml(ticket.value?.resolution_summary ?? null))

/** Quill produces `<p><br></p>` for an empty editor — treat that as blank. */
function hasResolutionContent(html: string): boolean {
  const stripped = html.replace(/\s+/g, '')
  if (stripped === '' || stripped === '<p><br></p>' || stripped === '<p><br/></p>') {
    return false
  }
  // Strip tags to ensure there's actually content (incl. inline images).
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent!.trim() !== '' || !!tmp.querySelector('img')
}

/** Insert an `<img>` at the current selection in the resolution editor. */
function insertImageIntoResolution(url: string): void {
  const wrapper = resolutionEditor.value as unknown as { getQuill?: () => any } | null
  const quill: any = wrapper?.getQuill ? wrapper.getQuill() : null
  if (!quill) {
    return
  }
  const sel = quill.getSelection(true)
  const index = sel ? sel.index : quill.getLength()
  quill.insertEmbed(index, 'image', url, 'user')
  quill.setSelection(index + 1, 0, 'silent')
}

async function uploadInlineImage(file: File): Promise<string | null> {
  if (!ticket.value) {
    return null
  }
  if (!ALLOWED_INLINE_MIME.has(file.type)) {
    inlineImageHint.value = `“${file.name}” is not a supported image. Use JPEG, PNG, GIF, or WebP.`
    return null
  }
  if (file.size > MAX_INLINE_IMAGE_BYTES) {
    inlineImageHint.value = `“${file.name}” is over 10 MB.`
    return null
  }
  inlineImageBusy.value = true
  inlineImageHint.value = null
  try {
    const fd = new FormData()
    fd.append('image', file)
    const { data } = await api.post(`/api/v1/tickets/${ticket.value.id}/inline-images`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data.url as string
  } catch {
    inlineImageHint.value = 'Image upload failed. Try a smaller file or a JPEG/PNG.'
    return null
  } finally {
    inlineImageBusy.value = false
  }
}

/**
 * Custom Quill image handler: instead of base64-embedding, pick a file,
 * upload via /inline-images, and insert the returned URL. Triggered both by
 * the toolbar button and by intercepting paste / drop below.
 */
function handleResolutionImagePick(this: any): void {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/jpeg,image/png,image/gif,image/webp'
  input.onchange = async () => {
    const file = input.files?.[0]
    if (!file) return
    const url = await uploadInlineImage(file)
    if (url) {
      insertImageIntoResolution(url)
    }
  }
  input.click()
}

const resolutionQuillOptions = computed(() => ({
  modules: {
    toolbar: {
      container: [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }, { indent: '-1' }, { indent: '+1' }],
        [{ align: [] }],
        ['blockquote', 'code-block'],
        ['link', 'image', 'video'],
        ['clean'],
      ],
      handlers: {
        image: handleResolutionImagePick,
      },
    },
    clipboard: {
      // Disable Quill's built-in matchers for <img> so a pasted screenshot
      // doesn't drop a giant base64 string into the HTML — the paste handler
      // below uploads it via the API instead.
      matchVisual: false,
    },
  },
  placeholder: 'Describe what was fixed. Supports headings, lists, links, code blocks, screenshots, and video embeds…',
  theme: 'snow',
}))

/** Catch native paste/drop on the editor — used for screenshot paste support. */
function onResolutionReady(quill: any): void {
  const root = quill.root as HTMLElement
  root.addEventListener('paste', async (ev: ClipboardEvent) => {
    const items = ev.clipboardData?.items
    if (!items) return
    for (const it of Array.from(items)) {
      if (it.kind === 'file' && it.type.startsWith('image/')) {
        ev.preventDefault()
        const file = it.getAsFile()
        if (file) {
          const url = await uploadInlineImage(file)
          if (url) insertImageIntoResolution(url)
        }
        return
      }
    }
  })
  root.addEventListener('drop', async (ev: DragEvent) => {
    if (!ev.dataTransfer?.files?.length) return
    ev.preventDefault()
    for (const f of Array.from(ev.dataTransfer.files)) {
      if (!f.type.startsWith('image/')) continue
      const url = await uploadInlineImage(f)
      if (url) insertImageIntoResolution(url)
    }
  })
}

// Honour Quill's link target — open external links in a new tab.
const LinkBlot = Quill.import('formats/link') as any
if (LinkBlot && !(LinkBlot as any).__cbpPatched) {
  const origCreate = LinkBlot.create
  LinkBlot.create = function (value: string) {
    const node: HTMLAnchorElement = origCreate.call(this, value)
    if (/^https?:/i.test(value)) {
      node.setAttribute('target', '_blank')
      node.setAttribute('rel', 'noopener noreferrer')
    }
    return node
  }
  ;(LinkBlot as any).__cbpPatched = true
}

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
  const summary = resolutionNotes.value
  if (!id || !hasResolutionContent(summary)) {
    err.value = 'Please describe what was done before marking the ticket resolved.'
    return
  }
  resolving.value = true
  err.value = null
  try {
    await api.post(`/api/v1/tickets/${id}/submit-resolution`, { resolution_summary: summary })
    resolutionNotes.value = ''
    inlineImageHint.value = null
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
        <div v-if="isHtmlDescription" class="html" v-html="ticket.description" />
        <pre v-else class="pre">{{ ticket.description }}</pre>
      </section>

      <section v-if="ticket.resolution_summary" class="resbox">
        <h3 class="h3">Latest resolution notes</h3>
        <div v-if="isHtmlResolution" class="res-html" v-html="ticket.resolution_summary" />
        <p v-else class="res">{{ ticket.resolution_summary }}</p>
      </section>

      <section v-if="canSubmitResolution" class="resolve">
        <h3 class="h3">Submit resolution</h3>
        <p class="muted small">
          Describe what was fixed. Use the toolbar to format text, add lists, paste screenshots, embed video, or attach links. The requester is emailed; if confirmation is enabled in settings they must click the link to close the ticket.
        </p>
        <QuillEditor
          ref="resolutionEditor"
          v-model:content="resolutionNotes"
          content-type="html"
          class="quill"
          :options="resolutionQuillOptions"
          @ready="onResolutionReady"
        />
        <p class="quill-hint">
          <strong>Pro tip:</strong> paste a screenshot directly (⌘V / Ctrl+V), drag images onto the editor, or use the image button in the toolbar. Allowed: JPEG, PNG, GIF, WebP · max 10 MB.
        </p>
        <p v-if="inlineImageBusy" class="quill-status">Uploading image…</p>
        <p v-if="inlineImageHint" class="quill-warn" role="status">{{ inlineImageHint }}</p>
        <button type="button" class="primary" :disabled="resolving || inlineImageBusy" @click="submitResolution">
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
.quill {
  background: #fff;
  border-radius: 8px;
}
.quill :deep(.ql-toolbar) {
  border-top-left-radius: 8px;
  border-top-right-radius: 8px;
  border-color: #cbd5e1;
}
.quill :deep(.ql-container) {
  min-height: 200px;
  font-size: 0.95rem;
  border-bottom-left-radius: 8px;
  border-bottom-right-radius: 8px;
  border-color: #cbd5e1;
}
.quill :deep(.ql-editor) {
  min-height: 200px;
}
.quill :deep(.ql-editor img) {
  max-width: 100%;
  height: auto;
  border-radius: 6px;
}
.quill-hint {
  margin: 0;
  font-size: 0.78rem;
  color: #64748b;
  line-height: 1.45;
}
.quill-status {
  margin: 0;
  font-size: 0.82rem;
  color: #0d7a3a;
  font-weight: 600;
}
.quill-warn {
  margin: 0;
  font-size: 0.82rem;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  padding: 0.4rem 0.55rem;
  border-radius: 6px;
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
