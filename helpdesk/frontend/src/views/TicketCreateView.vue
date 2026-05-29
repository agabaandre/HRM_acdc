<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import CbpRichTextEditor from '../components/common/CbpRichTextEditor.vue'
import { api } from '../lib/api'
import { hasRichTextContent } from '../lib/richText'
import { useAuthStore } from '../stores/auth'

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
const err = ref<string | null>(null)
const catsErr = ref<string | null>(null)
const catsLoading = ref(true)
const refErr = ref<string | null>(null)
const busy = ref(false)

const staffRows = ref<StaffRow[]>([])
const selectedStaffId = ref('')
const staffSearch = ref('')
let staffSearchTimer: ReturnType<typeof setTimeout> | null = null

/** End user: open ticket for another staff member (loads directory picker). */
const forSomeoneElse = ref(false)

const canSetPriority = computed(
  () => auth.me?.profile?.role && auth.me.profile.role !== 'user',
)

const isStaff = computed(() => auth.me?.profile?.role && auth.me.profile.role !== 'user')

const isEndUser = computed(() => auth.me?.profile?.role === 'user')

const needsDirectoryPicker = computed(() => isStaff.value || (isEndUser.value && forSomeoneElse.value))

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

const filteredStaffRows = computed(() => {
  const q = staffSearch.value.trim().toLowerCase()
  if (!q) {
    return staffRows.value.slice(0, 20)
  }
  return staffRows.value
    .filter((s) => {
      const duty = (s.duty_station_name ?? '').toLowerCase()
      return s.name.toLowerCase().includes(q)
        || (s.work_email ?? '').toLowerCase().includes(q)
        || duty.includes(q)
        || String(s.id).includes(q)
    })
    .slice(0, 25)
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

const descriptionReady = computed(() => hasRichTextContent(form.description))

const canSubmit = computed(
  () =>
    staffRequesterReady.value
    && descriptionReady.value
    && !catsLoading.value
    && cats.value.length > 0,
)

async function loadCats() {
  catsErr.value = null
  catsLoading.value = true
  try {
    const { data } = await api.get<{ data: { id: number; name: string }[] }>('/api/v1/categories')
    cats.value = Array.isArray(data.data) ? data.data : []
    if (cats.value.length && !form.category_id) {
      form.category_id = cats.value[0].id
    }
    if (cats.value.length === 0) {
      catsErr.value =
        'No issue categories are configured yet. An administrator can add them under Settings → Issue categories, or run the database seeder on the API server.'
    }
  } catch {
    cats.value = []
    catsErr.value = 'Could not load issue categories. Check that the Helpdesk API is running, then refresh.'
  } finally {
    catsLoading.value = false
  }
}

async function loadReferenceData() {
  refErr.value = null
  try {
    await api.get('/api/v1/reference-data')
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
    if (staffSearch.value.trim()) {
      params.q = staffSearch.value.trim()
    }
    const { data } = await api.get('/api/v1/reference-data/staff', { params })
    staffRows.value = data.data.staff as StaffRow[]
    if (selectedStaffId.value && !staffRows.value.some((s) => String(s.id) === selectedStaffId.value)) {
      selectedStaffId.value = ''
    }
  } catch {
    refErr.value = 'Could not load staff from the directory. Retry or ask an admin to sync reference data.'
    staffRows.value = []
    selectedStaffId.value = ''
  }
}

watch(staffSearch, () => {
  if (!needsDirectoryPicker.value) {
    return
  }
  selectedStaffId.value = ''
  if (staffSearchTimer) clearTimeout(staffSearchTimer)
  staffSearchTimer = setTimeout(() => {
    void fetchStaffList()
  }, 250)
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
    if (isStaff.value && auth.me?.profile?.staff_id) {
      const myId = auth.me.profile.staff_id
      selectedStaffId.value = String(myId)
      if (!staffRows.value.some((s) => s.id === myId)) {
        staffRows.value.unshift({
          id: myId,
          name: auth.me.name,
          work_email: auth.me.email ?? null,
          division_id: auth.me.profile.division_id ?? null,
          directorate_id: auth.me.profile.directorate_id ?? null,
          duty_station_name: auth.me.profile.duty_station ?? null,
        })
      }
    }
  }
})

function staffOptionLabel(s: StaffRow): string {
  const email = (s.work_email ?? '').trim() || '—'
  const duty = (s.duty_station_name ?? '').trim()
  const dutyPart = duty ? ` · ${duty}` : ''
  return `${s.name} · ${email}${dutyPart}`
}

function pickRequester(s: StaffRow) {
  selectedStaffId.value = String(s.id)
  staffSearch.value = s.name
}

async function submit() {
  if (needsDirectoryPicker.value && !selectedStaffId.value) {
    err.value = 'Choose who this request is for using the staff search below.'
    return
  }
  if (!hasRichTextContent(form.description)) {
    err.value = 'Please enter a description of the issue. You can add text, lists, links, and images in the editor.'
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
    await api.post('/api/v1/tickets', body)
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
        <label class="full">Category
          <select
            v-model.number="form.category_id"
            required
            :disabled="catsLoading || cats.length === 0"
          >
            <option v-if="catsLoading" :value="0" disabled>Loading categories…</option>
            <option v-else-if="cats.length === 0" :value="0" disabled>No categories available</option>
            <option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <p v-if="catsErr" class="warn full" role="alert">{{ catsErr }}</p>

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
              :disabled="!!refErr"
            />
            <ul v-if="filteredStaffRows.length" class="combo-results" role="listbox" aria-label="Requester results">
              <li
                v-for="s in filteredStaffRows"
                :key="s.id"
                class="combo-result"
                :class="{ selected: selectedStaffId === String(s.id) }"
                @click="pickRequester(s)"
              >
                <span class="combo-result-name">{{ s.name }}</span>
                <span class="combo-result-meta">{{ staffOptionLabel(s) }}</span>
              </li>
            </ul>
            <p v-else class="combo-empty">No staff found. Try another name/email.</p>
            <p v-if="selectedRequesterPreview" class="preview" role="status">
              <strong>Selected:</strong> {{ selectedRequesterPreview }}
            </p>
          </div>
        </template>

        <label class="full desc-label">
          <span>Description <span class="req" aria-hidden="true">*</span></span>
          <CbpRichTextEditor
            v-model="form.description"
            placeholder="Describe the issue (required). Add text, screenshots, and other details in the editor…"
          />
          <span v-if="!descriptionReady" class="desc-hint muted">A description is required before you can submit.</span>
        </label>

        <label v-if="canSetPriority">Priority
          <select v-model="form.priority">
            <option value="low">low</option>
            <option value="medium">medium</option>
            <option value="high">high</option>
            <option value="critical">critical</option>
          </select>
        </label>
        <p v-else class="muted full">Priority defaults to <strong>medium</strong> for requesters.</p>
        <button
          class="primary"
          type="submit"
          :disabled="busy || !canSubmit"
        >
          {{ busy ? 'Submitting…' : 'Submit' }}
        </button>
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
.combo-results {
  list-style: none;
  margin: 0;
  padding: 0.25rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  max-height: 240px;
  overflow: auto;
  background: #fff;
}
.combo-result {
  padding: 0.45rem 0.5rem;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.combo-result:hover {
  background: #f8fafc;
}
.combo-result.selected {
  background: #e8f5ee;
}
.combo-result-name {
  font-size: 0.88rem;
  font-weight: 700;
  color: #0f172a;
}
.combo-result-meta {
  font-size: 0.78rem;
  color: #64748b;
}
.combo-empty {
  margin: 0;
  font-size: 0.82rem;
  color: #64748b;
  padding: 0.2rem 0.1rem;
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
.desc-label .req {
  color: #b91c1c;
}
.desc-hint {
  font-weight: 500;
  font-size: 0.8rem;
  margin-top: 0.15rem;
}
</style>
