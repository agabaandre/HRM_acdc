<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import type { FormError, FormSubmitEvent } from '../types/form'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { type SelectNumberItem } from '../lib/helpdeskForm'
import { notifyError, notifyWarning } from '../lib/notify'
import { hasRichTextContent, htmlContainsDataUriImages } from '../lib/richText'
import { useAuthStore } from '../stores/auth'

const CbpRichTextEditor = defineAsyncComponent(
  () => import('../components/common/CbpRichTextEditor.vue'),
)

interface StaffRow {
  id: number
  name: string
  work_email: string | null
  division_id: number | null
  directorate_id: number | null
  duty_station_name?: string | null
}

type CreateTabId = 'ticket' | 'software' | 'hosting' | 'innovations'

const router = useRouter()
const auth = useAuthStore()
const activeTab = ref<CreateTabId>('ticket')
const cats = ref<{ id: number; name: string; business_unit_id?: number }[]>([])
const businessUnits = ref<Array<{
  id: number
  name: string
  slug: string
  description?: string | null
  allows_anonymous: boolean
  categories: Array<{ id: number; name: string; ai_description?: string | null }>
}>>([])
const showCategoryField = ref(false)
const showCategoryAiDescription = ref(true)
const form = reactive({
  business_unit_id: undefined as number | undefined,
  category_id: undefined as number | undefined,
  description: '',
  is_anonymous: false,
})
const catsErr = ref<string | null>(null)
const catsLoading = ref(true)
const refErr = ref<string | null>(null)
const busy = ref(false)
const inlineImageBusy = ref(false)
const descriptionEditorRef = ref<InstanceType<typeof CbpRichTextEditor> | null>(null)
/** Stable per visit so retries / double-clicks do not create duplicate tickets. */
const ticketCreateIdempotencyKey = ref(
  typeof crypto !== 'undefined' && 'randomUUID' in crypto
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(36).slice(2)}`,
)

const staffRows = ref<StaffRow[]>([])
const selectedStaffId = ref<number | null>(null)
const staffSearch = ref('')
let staffSearchTimer: ReturnType<typeof setTimeout> | null = null

/**
 * Open ticket for another staff member (directory picker).
 * Staff/admins default to true; end users default to self.
 */
const forSomeoneElse = ref(false)

const isStaff = computed(() => auth.me?.profile?.role && auth.me.profile.role !== 'user')

const canAccessSoftwareRequests = computed(() => Boolean(auth.me))
const canAccessHosting = computed(() => Boolean(auth.me))
const canAccessInnovations = computed(() => Boolean(auth.me))

function goToSoftwareRequests(): void {
  void router.push({ path: '/tools/software-requests', query: { tab: 'new' } })
}

function goToHostingRequests(): void {
  void router.push({ path: '/tools/hosting-requests', query: { tab: 'new' } })
}

function goToInnovationRequests(): void {
  void router.push({ path: '/tools/innovation-requests', query: { tab: 'new' } })
}

const needsDirectoryPicker = computed(() => forSomeoneElse.value)

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
  return staffRows.value.find((s) => s.id === selectedStaffId.value) ?? null
})

const staffSelectItems = computed((): SelectNumberItem[] => {
  const seen = new Set<number>()
  const items: SelectNumberItem[] = []
  const push = (s: StaffRow) => {
    if (seen.has(s.id)) return
    seen.add(s.id)
    items.push({ label: staffOptionLabel(s), value: s.id })
  }
  const selected = selectedStaffRow.value
  if (selected) push(selected)
  for (const s of staffRows.value.slice(0, 40)) {
    push(s)
  }
  return items
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

const selectedBusinessUnit = computed(() =>
  businessUnits.value.find((u) => u.id === form.business_unit_id) ?? null,
)

const allowsAnonymous = computed(() => Boolean(selectedBusinessUnit.value?.allows_anonymous))

const categoriesForUnit = computed(() => selectedBusinessUnit.value?.categories ?? [])

const descriptionReady = computed(() => hasRichTextContent(form.description))

const canSubmit = computed(() => {
  if (!descriptionReady.value || catsLoading.value || businessUnits.value.length === 0 || inlineImageBusy.value) {
    return false
  }
  if (!(form.business_unit_id ?? 0)) {
    return false
  }
  if (showCategoryField.value && !(form.category_id ?? 0)) {
    return false
  }
  if (form.is_anonymous && allowsAnonymous.value) {
    return true
  }
  return staffRequesterReady.value
})

const businessUnitItems = computed((): SelectNumberItem[] =>
  businessUnits.value.map((u) => ({ label: u.name, value: u.id })),
)

function validateCreateForm(_state: typeof form): FormError[] {
  const errors: FormError[] = []
  if (!_state.business_unit_id || _state.business_unit_id < 1) {
    errors.push({ name: 'business_unit_id', message: 'Choose a support area' })
  }
  if (showCategoryField.value && (!_state.category_id || _state.category_id < 1)) {
    errors.push({ name: 'category_id', message: 'Choose a category' })
  }
  if (!hasRichTextContent(_state.description)) {
    errors.push({ name: 'description', message: 'Description is required' })
  }
  if (!(_state.is_anonymous && allowsAnonymous.value) && needsDirectoryPicker.value && !selectedStaffId.value) {
    errors.push({ name: 'requester_staff_id', message: 'Choose a requester from the directory' })
  }
  return errors
}

async function onFormSubmit(_event: FormSubmitEvent<typeof form>): Promise<void> {
  await submit()
}

async function loadCats() {
  catsErr.value = null
  catsLoading.value = true
  try {
    const { data } = await api.get<{
      data: Array<{
        id: number
        name: string
        slug: string
        description?: string | null
        allows_anonymous: boolean
        categories: Array<{ id: number; name: string }>
      }>
      meta?: {
        show_issue_category_on_request_form?: boolean
        show_category_ai_description_on_request_form?: boolean
      }
    }>('/api/v1/business-units')
    businessUnits.value = Array.isArray(data.data) ? data.data : []
    showCategoryField.value = Boolean(data.meta?.show_issue_category_on_request_form)
    showCategoryAiDescription.value = data.meta?.show_category_ai_description_on_request_form !== false
    cats.value = businessUnits.value.flatMap((u) =>
      u.categories.map((c) => ({ ...c, business_unit_id: u.id })),
    )
    if (businessUnits.value.length === 0) {
      catsErr.value =
        'No business units are available yet. An administrator must configure agents for each unit under Settings → Agents & support groups (and ensure issue categories exist).'
      notifyWarning(catsErr.value)
    }
  } catch {
    businessUnits.value = []
    cats.value = []
    catsErr.value = 'Could not load business units. Check that the Helpdesk API is running, then refresh.'
    notifyWarning(catsErr.value)
  } finally {
    catsLoading.value = false
  }
}

watch(
  () => form.business_unit_id,
  () => {
    form.category_id = undefined
    if (!allowsAnonymous.value) {
      form.is_anonymous = false
    }
  },
)

watch(
  () => form.is_anonymous,
  (anon) => {
    if (anon) {
      forSomeoneElse.value = false
      selectedStaffId.value = null
      staffSearch.value = ''
    }
  },
)

async function loadReferenceData() {
  refErr.value = null
  try {
    await api.get('/api/v1/reference-data')
  } catch {
    refErr.value =
      'Could not load the Staff directory. Check API credentials, run directory sync under Settings → Jobs, then retry.'
    notifyWarning(refErr.value)
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
    const incoming = data.data.staff as StaffRow[]
    const keepId = selectedStaffId.value
    const kept = keepId ? staffRows.value.find((s) => s.id === keepId) ?? null : null
    staffRows.value = incoming
    if (kept && !staffRows.value.some((s) => s.id === keepId)) {
      staffRows.value = [kept, ...staffRows.value]
    }
  } catch {
    refErr.value = 'Could not load staff from the directory. Retry or ask an admin to sync reference data.'
    notifyWarning(refErr.value)
    staffRows.value = []
    selectedStaffId.value = null
  }
}

watch(staffSearch, () => {
  if (!needsDirectoryPicker.value) {
    return
  }
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
    selectedStaffId.value = null
    staffRows.value = []
    staffSearch.value = ''
  }
})

watch(forSomeoneElse, (v) => {
  if (!v) {
    selectedStaffId.value = null
    staffSearch.value = ''
  }
})

onMounted(async () => {
  // Staff/agents log for a colleague by default; end users log for themselves.
  if (isStaff.value) {
    forSomeoneElse.value = true
  }
  await loadCats()
  if (needsDirectoryPicker.value) {
    await loadReferenceData()
    await fetchStaffList()
  }
})

function staffOptionLabel(s: StaffRow): string {
  const email = (s.work_email ?? '').trim() || '—'
  const duty = (s.duty_station_name ?? '').trim()
  const dutyPart = duty ? ` · ${duty}` : ''
  return `${s.name} · ${email}${dutyPart}`
}

async function submit() {
  if (busy.value) {
    return
  }
  if (inlineImageBusy.value) {
    notifyWarning('An image is still uploading. Wait a moment and try again.')
    return
  }
  if (showCategoryField.value && !(form.category_id ?? 0)) {
    notifyWarning('Choose a category before submitting.')
    return
  }
  await descriptionEditorRef.value?.ensureImagesUploaded()
  if (htmlContainsDataUriImages(form.description)) {
    notifyWarning('An image is still uploading. Wait a moment and try again.')
    return
  }
  busy.value = true
  try {
    const body: Record<string, unknown> = {
      business_unit_id: form.business_unit_id,
      description: form.description,
    }
    if (showCategoryField.value) {
      body.category_id = form.category_id
    } else if (form.category_id) {
      body.category_id = form.category_id
    }
    if (form.is_anonymous && allowsAnonymous.value) {
      body.is_anonymous = true
    } else if (needsDirectoryPicker.value) {
      body.requester_staff_id = selectedStaffId.value
    }
    await api.post('/api/v1/tickets', body, {
      headers: { 'Idempotency-Key': ticketCreateIdempotencyKey.value },
    })
    await router.push('/tickets')
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Could not create ticket'))
    busy.value = false
  }
}
</script>

<template>
  <div>
    <CbpPageHeading title="Create ticket" back-to="/tickets" back-label="← Tickets">
      <template #lede>
        Choose a help desk
      </template>
    </CbpPageHeading>

    <div class="create-tabs" role="tablist" aria-label="Request type">
      <button
        type="button"
        role="tab"
        class="create-tab"
        :class="{ active: activeTab === 'ticket' }"
        :aria-selected="activeTab === 'ticket'"
        @click="activeTab = 'ticket'"
      >
        Help Desk
      </button>
      <button
        v-if="canAccessSoftwareRequests"
        type="button"
        role="tab"
        class="create-tab"
        :class="{ active: activeTab === 'software' }"
        :aria-selected="activeTab === 'software'"
        @click="activeTab = 'software'"
      >
        Information System Request
      </button>
      <button
        v-if="canAccessHosting"
        type="button"
        role="tab"
        class="create-tab"
        :class="{ active: activeTab === 'hosting' }"
        :aria-selected="activeTab === 'hosting'"
        @click="activeTab = 'hosting'"
      >
        Hosting
      </button>
      <button
        v-if="canAccessInnovations"
        type="button"
        role="tab"
        class="create-tab"
        :class="{ active: activeTab === 'innovations' }"
        :aria-selected="activeTab === 'innovations'"
        @click="activeTab = 'innovations'"
      >
        Innovations
      </button>
    </div>

    <div v-show="activeTab === 'software'" class="cbp-card software-gateway" role="tabpanel">
      <h2 class="gateway-title">Information System Request</h2>
      <p class="gateway-copy">
        Information System requests use a dedicated form under Help Desk Modules — including drafts, status tracking, HoD approval, and reviewer workflows.
      </p>
      <UButton color="primary" @click="goToSoftwareRequests">
        Continue to Information System Request
      </UButton>
    </div>

    <div v-show="activeTab === 'hosting'" class="cbp-card software-gateway" role="tabpanel">
      <h2 class="gateway-title">Hosting request</h2>
      <p class="gateway-copy">
        Request cloud or on-premises hosting. Your Head of Division must approve before Help Desk agents can process the request.
      </p>
      <UButton color="primary" @click="goToHostingRequests">
        Continue to Hosting request
      </UButton>
    </div>

    <div v-show="activeTab === 'innovations'" class="cbp-card software-gateway" role="tabpanel">
      <h2 class="gateway-title">Innovations</h2>
      <p class="gateway-copy">
        Submit an innovation idea. No Head of Division approval is required — processors can act after you submit.
      </p>
      <UButton color="primary" @click="goToInnovationRequests">
        Continue to Innovations
      </UButton>
    </div>

    <div v-show="activeTab === 'ticket'" class="cbp-card" :class="{ 'is-submitting': busy }" role="tabpanel">
      <p v-if="isStaff" class="ticket-lede">
        By default this request is for <strong>another staff member</strong>. Search the directory to choose the requester, or turn the option off to log it for yourself.
      </p>
      <p v-else class="ticket-lede">
        By default this request is for <strong>you</strong> (from your session). Turn on “another staff member” only if you are opening the ticket on someone else’s behalf.
      </p>
      <UForm
        :state="form"
        :validate="validateCreateForm"
        class="hd-form hd-form--grid"
        :disabled="busy"
        @submit="onFormSubmit"
      >
        <UFormField label="Support Area" name="business_unit_id" required class="full">
          <USelectMenu
            v-model="form.business_unit_id"
            :items="businessUnitItems"
            searchable
            :disabled="busy || catsLoading || businessUnits.length === 0"
            :placeholder="catsLoading ? 'Loading…' : businessUnits.length === 0 ? 'No support areas available' : 'Search or select support area'"
            class="w-full"
            value-key="value"
          />
          <p v-if="selectedBusinessUnit?.description" class="field-hint">
            {{ selectedBusinessUnit.description }}
          </p>
        </UFormField>

        <UFormField
          v-if="form.business_unit_id && categoriesForUnit.length > 0"
          label="Category"
          name="category_id"
          :required="showCategoryField"
          stacked-label
          class="full"
        >
          <div
            class="category-radio-grid"
            role="radiogroup"
            aria-label="Category"
            :aria-required="showCategoryField ? 'true' : undefined"
          >
            <label
              v-for="cat in categoriesForUnit"
              :key="cat.id"
              class="category-radio"
              :class="{
                'is-selected': form.category_id === cat.id,
                'is-disabled': busy,
              }"
            >
              <input
                type="radio"
                name="category_id"
                class="category-radio-input"
                :checked="form.category_id === cat.id"
                :disabled="busy"
                @change="form.category_id = cat.id"
              />
              <span class="category-radio-body">
                <span class="category-radio-name">{{ cat.name }}</span>
                <span
                  v-if="showCategoryAiDescription && cat.ai_description"
                  class="category-radio-ai"
                  :title="cat.ai_description"
                >
                  ({{ cat.ai_description }})
                </span>
              </span>
              <span class="category-radio-check" aria-hidden="true">
                <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M5 10.5L8.5 14L15 6.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
            </label>
          </div>
          <p v-if="showCategoryField" class="field-hint">
            Required — select the category that best matches your request.
          </p>
          <p v-else class="field-hint">
            Optional — leave unselected to let AI assign a category from your description.
          </p>
        </UFormField>
        <p v-else-if="form.business_unit_id && categoriesForUnit.length === 0" class="ai-cat-hint full">
          No categories are available for this support area yet.
        </p>
        <p v-else-if="!form.business_unit_id && showCategoryField" class="ai-cat-hint full">
          Select a support area to choose a category.
        </p>

        <UFormField v-if="allowsAnonymous" name="is_anonymous" class="full">
          <UCheckbox v-model="form.is_anonymous" :disabled="busy">
            <template #label>
              Report <strong>anonymously</strong> (your identity will not be submitted)
            </template>
          </UCheckbox>
        </UFormField>

        <template v-if="!form.is_anonymous">
          <UFormField name="for_someone_else" class="full">
            <UCheckbox v-model="forSomeoneElse" :disabled="busy">
              <template #label>
                Request for another Person
              </template>
            </UCheckbox>
          </UFormField>
          <div v-if="!forSomeoneElse && selfRequesterLine" class="session-summary full" role="status">
            <span class="label">Requester</span>
            <p class="line">{{ selfRequesterLine }}</p>
            <p class="subtle">Taken from your signed-in profile. Nothing else to fill in here.</p>
          </div>
        </template>

        <template v-if="needsDirectoryPicker && !form.is_anonymous">
          <UFormField label="Find requester" name="requester_staff_id" required class="full">
            <USelectMenu
              v-model="selectedStaffId"
              v-model:search="staffSearch"
              :items="staffSelectItems"
              searchable
              no-filter
              :disabled="busy || !!refErr"
              placeholder="Search name, email, or duty station…"
              class="w-full"
              value-key="value"
            />
            <p v-if="selectedRequesterPreview" class="requester-preview" role="status">
              <strong>Selected:</strong> {{ selectedRequesterPreview }}
            </p>
            <p v-if="refErr" class="field-hint warn-inline">{{ refErr }}</p>
            <p v-else class="field-hint">
              Search by name, email, or duty station — same directory picker for all roles.
            </p>
          </UFormField>
        </template>

        <UFormField label="Description" name="description" required class="full hd-rich-field">
          <CbpRichTextEditor
            ref="descriptionEditorRef"
            v-model="form.description"
            :disabled="busy"
            show-screenshot-tip
            @uploading="inlineImageBusy = $event"
            placeholder="Describe what happened (required). Paste a screenshot of the error with ⌘V / Ctrl+V — include when it started and your device."
          />
          <template #hint>
            <span v-if="!descriptionReady" class="desc-hint muted">A description is required. A screenshot of the issue helps agents resolve it faster.</span>
          </template>
        </UFormField>

        <div class="full hd-form-actions">
          <UButton type="submit" color="primary" :loading="busy" :disabled="!canSubmit">
            Create ticket
          </UButton>
        </div>
      </UForm>
    </div>

    <Teleport to="body">
      <div
        v-if="busy"
        class="submit-overlay"
        role="alertdialog"
        aria-modal="true"
        aria-busy="true"
        aria-live="polite"
        aria-labelledby="submit-wait-title"
      >
        <div class="submit-overlay-card">
          <div class="submit-spinner" aria-hidden="true" />
          <p id="submit-wait-title" class="submit-overlay-title">Please wait</p>
          <p class="submit-overlay-text">Submitting your request…</p>
        </div>
      </div>
    </Teleport>
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
.create-tabs {
  display: flex;
  gap: 0.35rem;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 1rem;
}
.create-tab {
  border: 0;
  background: transparent;
  padding: 0.55rem 0.9rem;
  cursor: pointer;
  font-weight: 600;
  color: #64748b;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}
.create-tab.active {
  color: #0d7a3a;
  border-bottom-color: #0d7a3a;
}
.software-gateway {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 1.25rem 1.35rem;
}
.gateway-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.gateway-copy {
  margin: 0;
  max-width: 40rem;
  color: #64748b;
  font-size: 0.92rem;
  line-height: 1.45;
}
.ticket-lede {
  margin: 0 0 1rem;
  color: #64748b;
  font-size: 0.9rem;
  line-height: 1.45;
}
.session-summary {
  padding: 0.65rem 0.85rem;
  border-radius: 4px;
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
.ai-cat-hint {
  margin: -0.35rem 0 0.5rem;
  font-size: 0.84rem;
  color: #64748b;
}
.field-hint {
  margin: 0.4rem 0 0;
  font-size: 0.84rem;
  line-height: 1.4;
  color: #64748b;
}
.requester-combo {
  border: 1px solid #cbd5e1;
  border-radius: 4px;
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
  border-radius: 4px;
  font-size: 0.9rem;
}
.combo-results {
  list-style: none;
  margin: 0;
  padding: 0.25rem;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  max-height: 240px;
  overflow: auto;
  background: #fff;
}
.combo-results--searching {
  max-height: 120px;
}
.combo-result {
  padding: 0.45rem 0.5rem;
  border-radius: 4px;
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
  border-radius: 4px;
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
  border-radius: 4px;
  border: 1px solid #fcd34d;
}
.ghost {
  padding: 0.35rem 0.75rem;
  border-radius: 4px;
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
  border-radius: 4px;
}
.primary {
  justify-self: start;
  padding: 0.65rem 1.25rem;
  background: #119a48;
  color: #fff;
  border: none;
  border-radius: 4px;
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
.is-submitting {
  pointer-events: none;
  user-select: none;
}
.category-radio-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.85rem;
}
@media (max-width: 1100px) {
  .category-radio-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 800px) {
  .category-radio-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 520px) {
  .category-radio-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
.category-radio {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin: 0;
  min-height: 5.5rem;
  padding: 1.05rem 2.25rem 1.05rem 1.05rem;
  border: 1px solid #d5e0ec;
  border-radius: 12px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  cursor: pointer;
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease,
    transform 0.18s ease;
}
.category-radio:hover:not(.is-disabled) {
  border-color: #86c9a0;
  background: linear-gradient(180deg, #f7fcf9 0%, #eef8f2 100%);
  box-shadow: 0 4px 14px rgba(17, 154, 72, 0.1);
  transform: translateY(-1px);
}
.category-radio.is-selected {
  border-color: #119a48;
  background: linear-gradient(180deg, #f2fbf6 0%, #e6f7ed 100%);
  box-shadow:
    0 0 0 1px #119a48,
    0 6px 16px rgba(17, 154, 72, 0.12);
}
.category-radio.is-disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.category-radio-input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.category-radio-body {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
  flex: 1;
}
.category-radio-name {
  font-size: 0.92rem;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.35;
  letter-spacing: -0.01em;
}
.category-radio-ai {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  font-size: 0.78rem;
  font-weight: 500;
  font-style: italic;
  color: #64748b;
  line-height: 1.4;
  max-height: calc(1.4em * 2);
}
.category-radio-check {
  position: absolute;
  top: 0.7rem;
  right: 0.7rem;
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 999px;
  border: 1.5px solid #cbd5e1;
  background: #fff;
  color: transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}
.category-radio-check svg {
  width: 0.75rem;
  height: 0.75rem;
}
.category-radio.is-selected .category-radio-check {
  border-color: #119a48;
  background: #119a48;
  color: #fff;
}
.requester-preview {
  margin: 0.45rem 0 0;
  font-size: 0.82rem;
  color: #334155;
  line-height: 1.45;
  padding: 0.45rem 0.55rem;
  background: #f1f5f9;
  border-radius: 4px;
}
.warn-inline {
  color: #b45309 !important;
}
</style>

<style>
html.helpdesk-theme-dark .create-tabs {
  border-bottom-color: rgba(148, 163, 184, 0.25);
}
html.helpdesk-theme-dark .create-tab {
  color: #94a3b8;
}
html.helpdesk-theme-dark .create-tab.active {
  color: #86efac;
  border-bottom-color: #4ade80;
}
html.helpdesk-theme-dark .ticket-lede,
html.helpdesk-theme-dark .gateway-copy,
html.helpdesk-theme-dark .field-hint,
html.helpdesk-theme-dark .ai-cat-hint,
html.helpdesk-theme-dark .muted {
  color: #94a3b8;
}
html.helpdesk-theme-dark .gateway-title {
  color: #f1f5f9;
}
html.helpdesk-theme-dark .session-summary {
  background: #0f172a;
  border-color: rgba(148, 163, 184, 0.28);
}
html.helpdesk-theme-dark .session-summary .label,
html.helpdesk-theme-dark .session-summary .subtle {
  color: #94a3b8;
}
html.helpdesk-theme-dark .session-summary .line {
  color: #f1f5f9;
}
html.helpdesk-theme-dark .requester-preview {
  background: rgba(30, 41, 59, 0.9);
  color: #e2e8f0;
}
html.helpdesk-theme-dark .category-radio {
  background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
  border-color: rgba(148, 163, 184, 0.28);
  color: #e2e8f0;
  box-shadow: none;
}
html.helpdesk-theme-dark .category-radio:hover:not(.is-disabled) {
  border-color: rgba(74, 222, 128, 0.45);
  background: linear-gradient(180deg, #1f3a2d 0%, #15261d 100%);
}
html.helpdesk-theme-dark .category-radio.is-selected {
  border-color: rgba(74, 222, 128, 0.55);
  background: linear-gradient(180deg, #1f3f2d 0%, #163024 100%);
  box-shadow: 0 0 0 1px rgba(74, 222, 128, 0.35);
}
html.helpdesk-theme-dark .category-radio-name {
  color: #f1f5f9;
}
html.helpdesk-theme-dark .category-radio-ai {
  color: #94a3b8;
}
html.helpdesk-theme-dark .category-radio-check {
  border-color: rgba(148, 163, 184, 0.4);
  background: #0f172a;
}
html.helpdesk-theme-dark .category-radio.is-selected .category-radio-check {
  border-color: #4ade80;
  background: #16a34a;
  color: #fff;
}
html.helpdesk-theme-dark .submit-overlay-card {
  background: #1e293b;
  color: #e2e8f0;
}
html.helpdesk-theme-dark .submit-overlay-title {
  color: #f1f5f9;
}
html.helpdesk-theme-dark .submit-overlay-text {
  color: #94a3b8;
}
html.helpdesk-theme-dark .submit-spinner {
  border-color: rgba(148, 163, 184, 0.25);
  border-top-color: #4ade80;
}
</style>

<style>
.submit-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(15, 23, 42, 0.45);
}
.submit-overlay-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.65rem;
  min-width: min(18rem, 100%);
  padding: 1.5rem 1.75rem;
  background: #fff;
  border-radius: 4px;
  box-shadow: 0 16px 48px rgba(15, 23, 42, 0.2);
  text-align: center;
}
.submit-spinner {
  width: 2.25rem;
  height: 2.25rem;
  border: 3px solid #e2e8f0;
  border-top-color: #119a48;
  border-radius: 50%;
  animation: submit-spin 0.75s linear infinite;
}
.submit-overlay-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0f172a;
}
.submit-overlay-text {
  margin: 0;
  font-size: 0.88rem;
  color: #64748b;
}
@keyframes submit-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
