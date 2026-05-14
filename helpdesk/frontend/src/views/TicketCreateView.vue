<script setup lang="ts">
import { QuillEditor } from '@vueup/vue-quill'
import '@vueup/vue-quill/dist/vue-quill.snow.css'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { useAuthStore } from '../stores/auth'

interface DivisionRow {
  id: number
  name: string
  short_name: string | null
  directorate_id: number | null
}

interface DirectorateRow {
  id: number
  name: string
  director_id?: number | null
  director?: { id: number; name: string; fname?: string; lname?: string; title?: string | null } | null
}

interface StaffRow {
  id: number
  name: string
  work_email: string | null
  division_id: number | null
  directorate_id: number | null
  duty_station_name?: string | null
}

const router = useRouter()
const auth = useAuthStore()
const cats = ref<{ id: number; name: string }[]>([])
const form = reactive({
  category_id: 0 as number,
  description: '',
  priority: 'medium' as string,
})
const MAX_ATTACHMENTS = 3
const MAX_FILE_BYTES = 10 * 1024 * 1024 // matches API max:10240 (KB)

/** Accept string + validation aligned with API `mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx`. */
const FILE_ACCEPT =
  '.jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'

const ALLOWED_EXT = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'])
const ALLOWED_MIME = new Set([
  'image/jpeg',
  'image/jpg',
  'image/png',
  'image/gif',
  'image/webp',
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
])

const pendingFiles = ref<File[]>([])
const fileHint = ref<string | null>(null)
const isDragOver = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)
const cameraInputRef = ref<HTMLInputElement | null>(null)
const err = ref<string | null>(null)
const refErr = ref<string | null>(null)
const busy = ref(false)

const directorates = ref<DirectorateRow[]>([])
const divisions = ref<DivisionRow[]>([])
const staffRows = ref<StaffRow[]>([])
const selectedDirectorateId = ref('')
const selectedDivisionId = ref('')
const selectedStaffId = ref('')
const staffSearch = ref('')
let staffSearchTimer: ReturnType<typeof setTimeout> | null = null

/** End user: open ticket for another staff member (loads directory picker). */
const forSomeoneElse = ref(false)

const quillOptions = {
  modules: {
    toolbar: [
      ['bold', 'italic', 'underline'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      ['link', 'image'],
      ['clean'],
    ],
  },
  placeholder: 'Describe the issue (formatting & images supported)…',
}

const canSetPriority = computed(
  () => auth.me?.profile?.role && auth.me.profile.role !== 'user',
)

const isStaff = computed(() => auth.me?.profile?.role && auth.me.profile.role !== 'user')

const isEndUser = computed(() => auth.me?.profile?.role === 'user')

const needsDirectoryPicker = computed(() => isStaff.value || (isEndUser.value && forSomeoneElse.value))

const filteredDivisions = computed(() => {
  const dir = selectedDirectorateId.value === '' ? null : Number(selectedDirectorateId.value)
  if (!dir) {
    return divisions.value
  }
  return divisions.value.filter((d) => (d.directorate_id ?? null) === dir)
})

const selfRequesterLine = computed(() => {
  const m = auth.me
  if (!m?.profile?.staff_id) {
    return null
  }
  const email = (m.email ?? '').trim() || '—'
  const duty = (m.profile.duty_station ?? '').trim()
  const dutyPart = duty ? ` · ${duty}` : ''
  const sap = (m.profile.sap_no ?? '').trim()
  const idPart = sap ? `SAP ${sap}` : `Staff ID ${m.profile.staff_id}`
  return `${m.name} · ${email} · ${idPart}${dutyPart}`
})

const selectedStaffRow = computed(() => {
  if (!selectedStaffId.value) {
    return null
  }
  const id = Number(selectedStaffId.value)
  return staffRows.value.find((s) => s.id === id) ?? null
})

const selectedRequesterPreview = computed(() => {
  const row = selectedStaffRow.value
  if (!row) {
    return null
  }
  const email = (row.work_email ?? '').trim() || '—'
  const duty = (row.duty_station_name ?? '').trim()
  const dutyPart = duty ? ` · Duty station: ${duty}` : ''
  return `${row.name} · ${email} · Staff ID ${row.id}${dutyPart}`
})

const staffRequesterReady = computed(() => {
  if (!needsDirectoryPicker.value) {
    return true
  }
  return Boolean(selectedStaffId.value) && !refErr.value
})

async function loadCats() {
  const { data } = await api.get('/api/v1/categories')
  cats.value = data.data as { id: number; name: string }[]
  if (cats.value.length && !form.category_id) {
    form.category_id = cats.value[0].id
  }
}

async function loadReferenceData() {
  refErr.value = null
  try {
    const { data } = await api.get('/api/v1/reference-data')
    directorates.value = (data.data.directorates as DirectorateRow[]).filter((d) => d.id > 0 && d.name)
    divisions.value = (data.data.divisions as DivisionRow[]).filter((d) => d.id > 0 && d.name)
  } catch {
    refErr.value =
      'Could not load the Staff directory. Check API credentials, run directory sync under Settings → Jobs, then retry.'
  }
}

async function fetchStaffList() {
  if (!needsDirectoryPicker.value) {
    return
  }
  refErr.value = null
  try {
    const params: Record<string, string | number> = {}
    if (selectedDirectorateId.value !== '') {
      params.directorate_id = Number(selectedDirectorateId.value)
    }
    if (selectedDivisionId.value !== '') {
      params.division_id = Number(selectedDivisionId.value)
    }
    if (staffSearch.value.trim()) {
      params.q = staffSearch.value.trim()
    }
    const { data } = await api.get('/api/v1/reference-data/staff', { params })
    staffRows.value = data.data.staff as StaffRow[]
  } catch {
    refErr.value = 'Could not load staff from the directory. Retry or ask an admin to sync reference data.'
    staffRows.value = []
    selectedStaffId.value = ''
  }
}

watch([selectedDirectorateId, selectedDivisionId], () => {
  if (!needsDirectoryPicker.value) {
    return
  }
  selectedStaffId.value = ''
  const dir = selectedDirectorateId.value === '' ? null : Number(selectedDirectorateId.value)
  if (dir && selectedDivisionId.value !== '') {
    const div = divisions.value.find((d) => d.id === Number(selectedDivisionId.value))
    if (div && (div.directorate_id ?? null) !== dir) {
      selectedDivisionId.value = ''
    }
  }
  void fetchStaffList()
})

watch(staffSearch, () => {
  if (!needsDirectoryPicker.value) {
    return
  }
  if (staffSearchTimer) {
    clearTimeout(staffSearchTimer)
  }
  staffSearchTimer = setTimeout(() => {
    void fetchStaffList()
  }, 350)
})

watch(needsDirectoryPicker, async (need) => {
  if (need) {
    await loadReferenceData()
    await fetchStaffList()
  } else {
    refErr.value = null
    selectedStaffId.value = ''
    staffRows.value = []
    staffSearch.value = ''
    selectedDirectorateId.value = ''
    selectedDivisionId.value = ''
  }
})

watch(forSomeoneElse, (v) => {
  if (!v) {
    selectedStaffId.value = ''
    staffSearch.value = ''
  }
})

async function retryDirectory() {
  await loadReferenceData()
  await fetchStaffList()
}

onMounted(async () => {
  await loadCats()
  if (needsDirectoryPicker.value) {
    await loadReferenceData()
    await fetchStaffList()
  }
})

function fileExtension(f: File): string {
  const n = f.name.toLowerCase()
  const dot = n.lastIndexOf('.')
  return dot >= 0 ? n.slice(dot + 1) : ''
}

function isAllowedFile(f: File): boolean {
  const ext = fileExtension(f)
  if (!ALLOWED_EXT.has(ext)) {
    return false
  }
  const t = (f.type || '').toLowerCase().trim()
  if (t === '' || t === 'application/octet-stream') {
    return true
  }
  return ALLOWED_MIME.has(t)
}

function resetFileInput(el: HTMLInputElement | null) {
  if (el) {
    el.value = ''
  }
}

function mergePendingFiles(newFiles: File[]) {
  const next = [...pendingFiles.value]
  const warnings: string[] = []
  let overflowNote = false

  for (const f of newFiles) {
    if (f.size > MAX_FILE_BYTES) {
      warnings.push(`“${f.name}” is over 10 MB.`)
      continue
    }
    if (!isAllowedFile(f)) {
      warnings.push(
        `“${f.name}” is not allowed (use JPEG, PNG, GIF, WebP, PDF, or Word .doc/.docx).`,
      )
      continue
    }
    if (next.length >= MAX_ATTACHMENTS) {
      overflowNote = true
      continue
    }
    next.push(f)
  }

  if (overflowNote) {
    warnings.push(`You can attach at most ${MAX_ATTACHMENTS} files. Remove one to add more.`)
  }

  pendingFiles.value = next
  fileHint.value = warnings.length ? [...new Set(warnings)].join(' ') : null
}

function onFileInputChange(ev: Event) {
  const input = ev.target as HTMLInputElement
  const list = input.files ? Array.from(input.files) : []
  mergePendingFiles(list)
  resetFileInput(input)
}

function onCameraChange(ev: Event) {
  const input = ev.target as HTMLInputElement
  const list = input.files ? Array.from(input.files) : []
  mergePendingFiles(list)
  resetFileInput(input)
}

function openFilePicker() {
  fileInputRef.value?.click()
}

function openCamera() {
  cameraInputRef.value?.click()
}

function removePendingFile(idx: number) {
  pendingFiles.value = pendingFiles.value.filter((_, i) => i !== idx)
  if (!pendingFiles.value.length) {
    fileHint.value = null
  }
}

function onDropzoneDragOver(ev: DragEvent) {
  ev.preventDefault()
  if (ev.dataTransfer) {
    ev.dataTransfer.dropEffect = 'copy'
  }
  isDragOver.value = true
}

function onDropzoneDragLeave(ev: DragEvent) {
  const related = ev.relatedTarget as Node | null
  if (related && (ev.currentTarget as HTMLElement).contains(related)) {
    return
  }
  isDragOver.value = false
}

function onDropzoneDrop(ev: DragEvent) {
  ev.preventDefault()
  isDragOver.value = false
  const dt = ev.dataTransfer
  if (!dt?.files?.length) {
    return
  }
  mergePendingFiles(Array.from(dt.files))
}

function staffOptionLabel(s: StaffRow): string {
  const email = (s.work_email ?? '').trim() || '—'
  const duty = (s.duty_station_name ?? '').trim()
  const dutyPart = duty ? ` · ${duty}` : ''
  return `${s.name} · ${email}${dutyPart}`
}

async function submit() {
  if (needsDirectoryPicker.value && !selectedStaffId.value) {
    err.value = 'Choose who this request is for using the staff search below.'
    return
  }
  busy.value = true
  err.value = null
  try {
    const body: Record<string, unknown> = {
      category_id: form.category_id,
      description: form.description,
    }
    if (canSetPriority.value) {
      body.priority = form.priority
    }
    if (needsDirectoryPicker.value) {
      body.requester_staff_id = Number(selectedStaffId.value)
    }
    const { data } = await api.post('/api/v1/tickets', body)
    const id = (data.data as { id: number }).id
    for (const file of pendingFiles.value) {
      const fd = new FormData()
      fd.append('file', file)
      await api.post(`/api/v1/tickets/${id}/attachments`, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }
    await router.push('/tickets')
  } catch {
    err.value = 'Could not create ticket'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div>
    <CbpPageHeading title="New request" back-to="/tickets" back-label="← Tickets">
      <template #lede>
        <template v-if="isStaff">
          Log a request for a colleague using the staff search. Name, work email, and duty station are taken from the directory when you submit.
        </template>
        <template v-else>
          By default this request is for <strong>you</strong> (from your session). Turn on “another staff member” only if you are opening the ticket on someone else’s behalf.
        </template>
      </template>
    </CbpPageHeading>
    <div class="cbp-card">
      <form class="grid" @submit.prevent="submit">
        <label>Category
          <select v-model.number="form.category_id" required>
            <option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>

        <template v-if="isEndUser">
          <label class="row-check full">
            <input v-model="forSomeoneElse" type="checkbox" />
            <span>This request is for <strong>another staff member</strong> (not me)</span>
          </label>
          <div v-if="!forSomeoneElse && selfRequesterLine" class="session-summary full" role="status">
            <span class="label">Requester</span>
            <p class="line">{{ selfRequesterLine }}</p>
            <p class="subtle">Taken from your signed-in profile. Nothing else to fill in here.</p>
          </div>
        </template>

        <template v-if="needsDirectoryPicker">
          <p v-if="refErr" class="warn full">{{ refErr }}</p>
          <div class="row-actions full">
            <button type="button" class="ghost" @click="retryDirectory">Reload directory</button>
          </div>

          <div class="requester-combo full">
            <span class="combo-label">Find requester</span>
            <input
              v-model="staffSearch"
              type="search"
              class="combo-search"
              placeholder="Type name, email, or duty station…"
              autocomplete="off"
              :disabled="!!refErr && !directorates.length"
            />
            <details v-if="directorates.length" class="filters">
              <summary>Filter by directorate / division</summary>
              <div class="filter-grid">
                <label>Directorate
                  <select v-model="selectedDirectorateId">
                    <option value="">All</option>
                    <option v-for="d in directorates" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                  </select>
                </label>
                <label>Division
                  <select v-model="selectedDivisionId">
                    <option value="">All</option>
                    <option v-for="d in filteredDivisions" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                  </select>
                </label>
              </div>
            </details>
            <select v-model="selectedStaffId" class="combo-select" required :disabled="!staffRows.length && !refErr">
              <option value="" disabled>Select from results…</option>
              <option v-for="s in staffRows" :key="s.id" :value="String(s.id)">
                {{ staffOptionLabel(s) }}
              </option>
            </select>
            <p v-if="selectedRequesterPreview" class="preview" role="status">
              <strong>Selected:</strong> {{ selectedRequesterPreview }}
            </p>
          </div>
        </template>

        <label class="full">Description
          <QuillEditor v-model:content="form.description" content-type="html" theme="snow" :options="quillOptions" class="quill" />
        </label>

        <div class="full attach-block">
          <span class="attach-label">Attachments (optional)</span>
          <p class="attach-help">
            Up to {{ MAX_ATTACHMENTS }} files · JPEG, PNG, GIF, WebP, PDF, Word · max 10 MB each
          </p>
          <input
            ref="fileInputRef"
            type="file"
            class="sr-only"
            multiple
            :accept="FILE_ACCEPT"
            @change="onFileInputChange"
          />
          <input
            ref="cameraInputRef"
            type="file"
            class="sr-only"
            accept="image/*"
            capture="environment"
            @change="onCameraChange"
          />
          <div
            class="dropzone"
            :class="{ 'dropzone--drag': isDragOver }"
            @dragover="onDropzoneDragOver"
            @dragleave="onDropzoneDragLeave"
            @drop="onDropzoneDrop"
            @click.self="openFilePicker"
          >
            <p class="dropzone-text">
              <strong>Drag and drop</strong> files here, or use the buttons below.
            </p>
            <div class="dropzone-actions" @click.stop>
              <button type="button" class="ghost" @click="openFilePicker">Choose files</button>
              <button type="button" class="ghost" @click="openCamera">Take photo</button>
            </div>
          </div>
          <ul v-if="pendingFiles.length" class="file-list">
            <li v-for="(f, idx) in pendingFiles" :key="`${f.name}-${idx}-${f.size}`" class="file-row">
              <span class="file-name" :title="f.name">{{ f.name }}</span>
              <span class="file-meta">{{ (f.size / 1024).toFixed(0) }} KB</span>
              <button type="button" class="file-remove" @click="removePendingFile(idx)">Remove</button>
            </li>
          </ul>
          <p v-if="fileHint" class="file-hint" role="status">{{ fileHint }}</p>
        </div>

        <label v-if="canSetPriority">Priority
          <select v-model="form.priority">
            <option value="low">low</option>
            <option value="medium">medium</option>
            <option value="high">high</option>
            <option value="critical">critical</option>
          </select>
        </label>
        <p v-else class="muted full">Priority defaults to <strong>medium</strong> for requesters.</p>
        <button class="primary" type="submit" :disabled="busy || !staffRequesterReady">{{ busy ? 'Submitting…' : 'Submit' }}</button>
      </form>
    </div>
    <p v-if="err" class="err">{{ err }}</p>
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  gap: 0.75rem;
}
label {
  display: flex;
  flex-direction: column;
  font-weight: 600;
  font-size: 0.85rem;
  color: #334155;
  gap: 0.35rem;
}
.full {
  grid-column: 1 / -1;
}
.row-check {
  flex-direction: row;
  align-items: flex-start;
  gap: 0.5rem;
  font-weight: 600;
  font-size: 0.88rem;
  color: #334155;
}
.row-check input {
  margin-top: 0.2rem;
}
.session-summary {
  padding: 0.65rem 0.85rem;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}
.session-summary .label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  display: block;
  margin-bottom: 0.25rem;
}
.session-summary .line {
  margin: 0;
  font-size: 0.92rem;
  color: #0f172a;
  font-weight: 600;
}
.session-summary .subtle {
  margin: 0.35rem 0 0;
  font-size: 0.8rem;
  color: #64748b;
}
.requester-combo {
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  padding: 0.65rem 0.75rem;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.combo-label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #64748b;
  font-weight: 700;
}
.combo-search {
  width: 100%;
  padding: 0.45rem 0.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.9rem;
}
.combo-select {
  width: 100%;
  padding: 0.45rem 0.5rem;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  font-size: 0.88rem;
}
.filters summary {
  cursor: pointer;
  font-size: 0.82rem;
  color: #0d7a3a;
  font-weight: 600;
}
.filter-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
@media (max-width: 640px) {
  .filter-grid {
    grid-template-columns: 1fr;
  }
}
.preview {
  margin: 0;
  font-size: 0.82rem;
  color: #334155;
  line-height: 1.45;
  padding: 0.45rem 0.5rem;
  background: #f1f5f9;
  border-radius: 8px;
}
.muted {
  font-size: 0.85rem;
  color: #64748b;
  margin: 0;
}
.warn {
  font-size: 0.85rem;
  color: #92400e;
  background: #fffbeb;
  padding: 0.5rem 0.65rem;
  border-radius: 8px;
  border: 1px solid #fcd34d;
}
.row-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.ghost {
  padding: 0.35rem 0.75rem;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #fff;
  font-weight: 600;
  cursor: pointer;
  color: #334155;
}
input,
select,
textarea {
  padding: 0.45rem 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}
.quill {
  min-height: 220px;
  background: #fff;
  border-radius: 8px;
}
.quill :deep(.ql-container) {
  min-height: 180px;
  font-size: 0.95rem;
}
.primary {
  justify-self: start;
  padding: 0.65rem 1.25rem;
  background: #119a48;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
}
.primary:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.err {
  color: #b91c1c;
  margin-top: 0.75rem;
}
.attach-block {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.attach-label {
  font-weight: 600;
  font-size: 0.85rem;
  color: #334155;
}
.attach-help {
  margin: 0;
  font-size: 0.8rem;
  color: #64748b;
  line-height: 1.4;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
.dropzone {
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  padding: 1rem 0.85rem;
  background: #f8fafc;
  cursor: pointer;
  transition:
    border-color 0.15s,
    background 0.15s;
}
.dropzone:hover,
.dropzone--drag {
  border-color: #119a48;
  background: #f0fdf4;
}
.dropzone-text {
  margin: 0 0 0.65rem;
  font-size: 0.88rem;
  color: #475569;
  text-align: center;
}
.dropzone-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: center;
}
.file-list {
  list-style: none;
  margin: 0.35rem 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.file-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  padding: 0.4rem 0.5rem;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.82rem;
}
.file-name {
  flex: 1 1 8rem;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #0f172a;
}
.file-meta {
  color: #64748b;
  font-variant-numeric: tabular-nums;
}
.file-remove {
  margin-left: auto;
  padding: 0.2rem 0.5rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #b91c1c;
  background: transparent;
  border: 1px solid #fecaca;
  border-radius: 6px;
  cursor: pointer;
}
.file-remove:hover {
  background: #fef2f2;
}
.file-hint {
  margin: 0.25rem 0 0;
  font-size: 0.8rem;
  color: #92400e;
  line-height: 1.35;
}
</style>
