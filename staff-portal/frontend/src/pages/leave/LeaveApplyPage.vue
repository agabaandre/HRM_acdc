<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import { useAuthStore } from '@/stores/auth'
import {
  fetchActiveLeaveTypes,
  fetchBalanceForType,
  fetchLeaveApplyRules,
  fetchLeaveRequest,
  fetchSupportingOfficers,
  fetchWorkingDays,
  resubmitLeaveRequest,
  submitLeaveRequest,
  type LeaveApplyRules,
  type LeaveBalanceDto,
  type LeaveSupportingOfficer,
  type LeaveTypeDto,
} from '@/lib/leaveApi'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const revisingId = ref<number | null>(null)
const existingDocument = ref(false)

const types = ref<LeaveTypeDto[]>([])
const officers = ref<LeaveSupportingOfficer[]>([])
const applyRules = ref<LeaveApplyRules>({ min_notice_days: 7, earliest_start_date: '' })
const leaveId = ref<number | null>(null)
const startDate = ref('')
const endDate = ref('')
const requestedDays = ref(0)
const emailLeave = ref(auth.me?.email ?? '')
const mobileLeave = ref('')
const supportingStaff = ref<number | null>(null)
const divisionHead = ref<number | null>(null)
const documentFile = ref<File | null>(null)
const balance = ref<LeaveBalanceDto | null>(null)
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const fileDragOver = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)

/** Flat options so Vuetify autocomplete title/search always use strings. */
const officerOptions = computed(() =>
  officers.value.map((o) => {
    const name = String(o.name || `Staff #${o.staff_id}`)
    const email = String(o.work_email || '')
    const sap = String(o.sap_number || '')
    return {
      value: Number(o.staff_id),
      title: name,
      subtitle: email,
      searchText: [name, email, sap].filter(Boolean).join(' ').toLowerCase(),
    }
  }),
)

const hodOptions = computed(() => {
  const rows = [...officerOptions.value]
  const hod = applyRules.value.default_hod
  if (hod && !rows.some((row) => row.value === Number(hod.staff_id))) {
    rows.unshift({
      value: Number(hod.staff_id),
      title: String(hod.name || `Staff #${hod.staff_id}`),
      subtitle: '',
      searchText: String(hod.name || '').toLowerCase(),
    })
  }
  return rows
})

const workflowEnabled = computed(() => Boolean(applyRules.value.workflow_enabled))
const workflowPreview = computed(() => {
  const preview = applyRules.value.workflow_preview || []
  if (!preview.length) return []
  const selectedHod = hodOptions.value.find((row) => row.value === Number(divisionHead.value))
  return preview.map((step, index) => {
    if (index === 0 || step.role === 'hod') {
      return {
        ...step,
        staff_name: selectedHod?.title || step.staff_name,
        staff_id: selectedHod?.value ?? step.staff_id,
      }
    }
    return step
  })
})

const selectedType = computed(() => types.value.find((t) => t.leave_id === leaveId.value) ?? null)
const documentRequired = computed(() => Boolean(selectedType.value?.requires_medical_certificate))
const documentAccept = '.pdf,.doc,.docx,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg'
const minStartDate = computed(() => applyRules.value.earliest_start_date || todayIso())
const minEndDate = computed(() => {
  if (startDate.value && startDate.value > minStartDate.value) return startDate.value
  return minStartDate.value
})
const dateHint = computed(() => {
  const days = applyRules.value.min_notice_days
  if (days > 0) {
    return `Earliest start is ${minStartDate.value} (${days}-day notice). Past dates are not allowed.`
  }
  return 'Past dates are not allowed.'
})
const documentHint = computed(() =>
  documentRequired.value
    ? 'Medical certificate required for this leave type. PDF or image, max 2MB.'
    : 'Optional supporting document. PDF, Word, or image — max 2MB.',
)

const documentMeta = computed(() => {
  const file = documentFile.value
  if (!file) return null
  const kb = file.size / 1024
  const sizeLabel = kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(kb))} KB`
  const ext = file.name.includes('.') ? file.name.split('.').pop()?.toUpperCase() : 'FILE'
  return { name: file.name, sizeLabel, ext: ext || 'FILE' }
})

function filterOfficers(
  _value: string,
  query: string,
  item?: { raw?: { searchText?: string; title?: string; subtitle?: string } },
): boolean {
  const q = query.trim().toLowerCase()
  if (!q) return true
  const hay = String(
    item?.raw?.searchText || [item?.raw?.title, item?.raw?.subtitle].filter(Boolean).join(' ') || _value || '',
  ).toLowerCase()
  return hay.includes(q)
}

function todayIso(): string {
  const d = new Date()
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

async function loadFormMeta() {
  loading.value = true
  error.value = null
  try {
    const [leaveTypes, supportingOfficers, rules] = await Promise.all([
      fetchActiveLeaveTypes(),
      fetchSupportingOfficers(),
      fetchLeaveApplyRules().catch(() => ({
        min_notice_days: 7,
        earliest_start_date: todayIso(),
      })),
    ])
    types.value = leaveTypes
    officers.value = supportingOfficers
    applyRules.value = rules
    const requestId = Number(route.query.request_id || 0)
    if (requestId > 0) {
      const existing = await fetchLeaveRequest(requestId)
      if (existing.overall_status !== 'Returned') {
        error.value = 'Only returned leave requests can be revised.'
      } else {
        revisingId.value = existing.request_id
        leaveId.value = existing.leave_id
        startDate.value = existing.start_date || ''
        endDate.value = existing.end_date || ''
        requestedDays.value = existing.requested_days
        emailLeave.value = existing.email_leave || emailLeave.value
        mobileLeave.value = existing.mobile_leave || ''
        supportingStaff.value = existing.supporting_staff ? Number(existing.supporting_staff) : null
        divisionHead.value = existing.division_head ? Number(existing.division_head) : null
        existingDocument.value = Boolean(existing.supporting_documentation)
      }
    } else if (rules.default_hod?.staff_id) {
      divisionHead.value = Number(rules.default_hod.staff_id)
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load leave form')
  } finally {
    loading.value = false
  }
}

async function refreshDays() {
  if (!startDate.value || !endDate.value) return
  try {
    requestedDays.value = await fetchWorkingDays(startDate.value, endDate.value, leaveId.value)
  } catch {
    /* ignore until both dates valid */
  }
}

async function refreshBalance() {
  if (!leaveId.value) {
    balance.value = null
    return
  }
  try {
    balance.value = await fetchBalanceForType(leaveId.value)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load balance')
  }
}

watch([startDate, endDate, leaveId], () => void refreshDays())
watch(leaveId, () => void refreshBalance())

function openFilePicker() {
  fileInputRef.value?.click()
}

function onFileInputChange(e: Event) {
  const input = e.target as HTMLInputElement
  applySelectedFile(input.files?.[0] ?? null)
}

function onFileDrop(e: DragEvent) {
  e.preventDefault()
  fileDragOver.value = false
  applySelectedFile(e.dataTransfer?.files?.[0] ?? null)
}

function applySelectedFile(file: File | null) {
  error.value = null
  if (!file) {
    documentFile.value = null
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    error.value = 'Supporting file must be 2MB or smaller.'
    documentFile.value = null
    if (fileInputRef.value) fileInputRef.value.value = ''
    return
  }
  documentFile.value = file
}

function clearDocument() {
  documentFile.value = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

async function onSubmit() {
  error.value = null
  if (!leaveId.value) {
    error.value = 'Select a leave type.'
    return
  }
  if (!startDate.value || !endDate.value) {
    error.value = 'Select start and end dates.'
    return
  }
  if (startDate.value < minStartDate.value) {
    error.value =
      applyRules.value.min_notice_days > 0
        ? `Leave must start at least ${applyRules.value.min_notice_days} days from today. The earliest start date is ${minStartDate.value}.`
        : 'Leave start date cannot be in the past.'
    return
  }
  if (!requestedDays.value || requestedDays.value < 1) {
    error.value = 'Working days requested must be at least 1.'
    return
  }
  if (!emailLeave.value.trim()) {
    error.value = 'Email while on leave is required.'
    return
  }
  if (!mobileLeave.value.trim()) {
    error.value = 'Phone number on leave is required.'
    return
  }
  const officerId = Number(supportingStaff.value)
  if (!officerId) {
    error.value = 'Select a supporting officer / OIC.'
    return
  }
  if (workflowEnabled.value && !Number(divisionHead.value)) {
    error.value = 'Select a Head of Division.'
    return
  }
  if (documentRequired.value && !documentFile.value && !existingDocument.value) {
    error.value = 'A medical certificate is required for this leave type.'
    return
  }

  const form = new FormData()
  form.append('leave_id', String(leaveId.value))
  form.append('start_date', startDate.value)
  form.append('end_date', endDate.value)
  form.append('requested_days', String(requestedDays.value))
  form.append('email_leave', emailLeave.value.trim())
  form.append('mobile_leave', mobileLeave.value.trim())
  form.append('supporting_staff', String(officerId))
  if (workflowEnabled.value && divisionHead.value) {
    form.append('division_head', String(divisionHead.value))
  }
  if (documentFile.value) {
    form.append('document', documentFile.value)
  }

  submitting.value = true
  try {
    if (revisingId.value) {
      await resubmitLeaveRequest(revisingId.value, form)
    } else {
      await submitLeaveRequest(form)
    }
    await router.push({ path: '/leave', query: { view: 'requests' } })
  } catch (e) {
    error.value = apiErrorMessage(e, revisingId.value ? 'Could not resubmit leave request' : 'Could not submit leave request')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  emailLeave.value = auth.me?.email ?? ''
  void loadFormMeta()
})
</script>

<template>
  <div class="leave-apply">
    <div class="d-flex justify-space-between align-center mb-3">
      <CbpPageHeading
        :title="revisingId ? 'Revise leave request' : 'Apply for leave'"
        :subtitle="revisingId ? 'Update the returned request and resubmit it for approval.' : 'Submit a leave request for approval.'"
      />
      <RouterLink to="/leave" style="text-decoration: none">
        <v-btn variant="outlined" size="small">Back</v-btn>
      </RouterLink>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <form v-else class="leave-apply__form" @submit.prevent="onSubmit">
      <v-card variant="outlined" class="leave-apply__section mb-4">
        <v-card-title class="text-h6 leave-apply__section-title">1. Leave details</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="7">
              <v-select
                v-model="leaveId"
                :items="types"
                item-title="leave_name"
                item-value="leave_id"
                label="Leave type"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" md="5">
              <v-alert v-if="balance" type="info" variant="tonal" density="compact" class="h-100">
                <strong>{{ balance.available }}</strong> days available
                <div class="text-caption">Used {{ balance.used }} · Pending {{ balance.pending }}</div>
              </v-alert>
              <div v-else class="text-caption text-medium-emphasis pt-2">
                Select a leave type to see your balance.
              </div>
            </v-col>
            <v-col cols="12" sm="4">
              <UDateInput
                v-model="startDate"
                label="Start date"
                placeholder="Select start date"
                :min="minStartDate"
                :max="endDate || undefined"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <UDateInput
                v-model="endDate"
                label="End date"
                placeholder="Select end date"
                :min="minEndDate"
              />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field
                v-model.number="requestedDays"
                type="number"
                min="1"
                label="Working days requested"
                density="comfortable"
                hide-details="auto"
                hint="Calculated from the date range; you can adjust if needed."
                persistent-hint
              />
            </v-col>
            <v-col cols="12">
              <div class="text-caption text-medium-emphasis">{{ dateHint }}</div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <v-card variant="outlined" class="leave-apply__section mb-4">
        <v-card-title class="text-h6 leave-apply__section-title">2. Contact while away</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="emailLeave"
                type="email"
                label="Email while on leave"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="mobileLeave"
                type="tel"
                label="Phone number on leave"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <v-card variant="outlined" class="leave-apply__section mb-4">
        <v-card-title class="text-h6 leave-apply__section-title">3. Coverage &amp; documents</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="6">
              <v-autocomplete
                v-model="supportingStaff"
                :items="officerOptions"
                item-title="title"
                item-value="value"
                :custom-filter="filterOfficers"
                label="Supporting officer / OIC"
                placeholder="Type a name or email to search"
                density="comfortable"
                clearable
                auto-select-first
                hide-details="auto"
                :no-data-text="officerOptions.length ? 'No matching officer' : 'No active officers available'"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item
                    v-bind="itemProps"
                    :title="String(item.raw.title)"
                    :subtitle="item.raw.subtitle || undefined"
                  />
                </template>
                <template #selection="{ item }">
                  <span class="leave-apply__selection">{{ String(item.raw.title || item.title || '') }}</span>
                </template>
              </v-autocomplete>
              <div class="text-caption text-medium-emphasis mt-1">
                Person who covers your duties while you are on leave.
              </div>
            </v-col>
            <v-col v-if="workflowEnabled" cols="12" md="6">
              <v-autocomplete
                v-model="divisionHead"
                :items="hodOptions"
                item-title="title"
                item-value="value"
                :custom-filter="filterOfficers"
                label="Head of Division"
                placeholder="Type a name or email to search"
                density="comfortable"
                clearable
                auto-select-first
                hide-details="auto"
                :no-data-text="hodOptions.length ? 'No matching officer' : 'No active officers available'"
              >
                <template #item="{ props: itemProps, item }">
                  <v-list-item
                    v-bind="itemProps"
                    :title="String(item.raw.title)"
                    :subtitle="item.raw.subtitle || undefined"
                  />
                </template>
                <template #selection="{ item }">
                  <span class="leave-apply__selection">{{ String(item.raw.title || item.title || '') }}</span>
                </template>
              </v-autocomplete>
              <div class="text-caption text-medium-emphasis mt-1">
                Defaults to your division head. Change this if an acting HOD should approve.
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="leave-upload__label">
                Supporting document
                <v-chip v-if="documentRequired" size="x-small" color="warning" variant="tonal" class="ms-1">
                  Required
                </v-chip>
              </div>
              <input
                ref="fileInputRef"
                type="file"
                class="leave-upload__native"
                :accept="documentAccept"
                @change="onFileInputChange"
              />
              <div
                class="leave-upload"
                :class="{
                  'leave-upload--active': fileDragOver,
                  'leave-upload--filled': Boolean(documentFile),
                  'leave-upload--required': documentRequired && !documentFile,
                }"
                role="button"
                tabindex="0"
                @click="openFilePicker"
                @keydown.enter.prevent="openFilePicker"
                @keydown.space.prevent="openFilePicker"
                @dragenter.prevent="fileDragOver = true"
                @dragover.prevent="fileDragOver = true"
                @dragleave.prevent="fileDragOver = false"
                @drop="onFileDrop"
              >
                <template v-if="documentMeta">
                  <div class="leave-upload__file" @click.stop>
                    <div class="leave-upload__icon leave-upload__icon--file" aria-hidden="true">
                      {{ documentMeta.ext.slice(0, 4) }}
                    </div>
                    <div class="leave-upload__file-meta">
                      <div class="leave-upload__file-name">{{ documentMeta.name }}</div>
                      <div class="leave-upload__file-size">{{ documentMeta.sizeLabel }} · ready to attach</div>
                    </div>
                    <div class="leave-upload__file-actions">
                      <v-btn size="small" variant="text" color="primary" @click.stop="openFilePicker">Replace</v-btn>
                      <v-btn size="small" variant="text" color="error" @click.stop="clearDocument">Remove</v-btn>
                    </div>
                  </div>
                </template>
                <template v-else>
                  <div class="leave-upload__icon" aria-hidden="true">
                    <i class="fa-solid fa-cloud-arrow-up" />
                  </div>
                  <div class="leave-upload__title">Drop file here or click to browse</div>
                  <div class="leave-upload__hint">{{ documentHint }}</div>
                </template>
              </div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <v-card v-if="workflowEnabled" variant="outlined" class="leave-apply__section mb-4">
        <v-card-title class="text-h6 leave-apply__section-title">4. Approval workflow</v-card-title>
        <v-card-text>
          <p class="text-body-2 text-medium-emphasis mb-3">
            Your request will move through these approvers in order. The Head of Division is first.
          </p>
          <ol class="leave-apply__preview">
            <li v-for="(step, index) in workflowPreview" :key="`${step.role}-${index}`">
              <strong>{{ step.label }}</strong>
              <span class="text-medium-emphasis"> — {{ step.staff_name || 'Not assigned' }}</span>
            </li>
          </ol>
        </v-card-text>
      </v-card>

      <div class="leave-apply__actions">
        <PortalBtn type="submit" :loading="submitting">
          {{ revisingId ? 'Resubmit request' : 'Submit request' }}
        </PortalBtn>
      </div>
    </form>
  </div>
</template>

<style scoped>
.leave-apply__section-title {
  font-weight: 500;
  color: #3a4752;
  padding-top: 1.15rem;
  padding-bottom: 0.85rem;
}
.leave-apply__section :deep(.v-card-text) {
  padding-top: 0.85rem;
  padding-bottom: 1.25rem;
}
.leave-apply__actions {
  display: flex;
  justify-content: flex-end;
  padding: 0.25rem 0 0.5rem;
}
.leave-apply__selection {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.leave-apply__preview {
  margin: 0;
  padding-left: 1.2rem;
}
.leave-apply__preview li + li {
  margin-top: 0.35rem;
}
.leave-upload__label {
  display: flex;
  align-items: center;
  margin-bottom: 0.4rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #3a4752;
}
.leave-upload__native {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}
.leave-upload {
  position: relative;
  min-height: 8.5rem;
  padding: 1.1rem 1.15rem;
  border: 1.5px dashed rgba(17, 154, 72, 0.35);
  border-radius: 0.75rem;
  background:
    linear-gradient(180deg, rgba(247, 250, 248, 0.95) 0%, #ffffff 70%),
    radial-gradient(circle at top right, rgba(17, 154, 72, 0.08), transparent 45%);
  cursor: pointer;
  transition:
    border-color 0.18s ease,
    box-shadow 0.18s ease,
    transform 0.18s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 0.35rem;
}
.leave-upload:hover,
.leave-upload:focus-visible {
  border-color: rgba(17, 154, 72, 0.65);
  box-shadow: 0 0 0 3px rgba(17, 154, 72, 0.1);
  outline: none;
}
.leave-upload--active {
  border-color: #119a48;
  box-shadow: 0 0 0 4px rgba(17, 154, 72, 0.14);
  transform: translateY(-1px);
}
.leave-upload--filled {
  align-items: stretch;
  text-align: left;
  border-style: solid;
  border-color: rgba(58, 71, 82, 0.18);
  background: #fff;
}
.leave-upload--required:not(.leave-upload--filled) {
  border-color: rgba(245, 158, 11, 0.55);
  background:
    linear-gradient(180deg, rgba(255, 251, 235, 0.9) 0%, #ffffff 75%),
    radial-gradient(circle at top right, rgba(245, 158, 11, 0.1), transparent 45%);
}
.leave-upload__icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 999px;
  display: grid;
  place-items: center;
  color: #119a48;
  background: rgba(17, 154, 72, 0.1);
  font-size: 1.15rem;
  margin-bottom: 0.15rem;
}
.leave-upload__icon--file {
  border-radius: 0.55rem;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #065f2c;
  background: #dcfce7;
  flex-shrink: 0;
}
.leave-upload__title {
  font-size: 0.95rem;
  font-weight: 650;
  color: #3a4752;
}
.leave-upload__hint {
  max-width: 22rem;
  font-size: 0.78rem;
  line-height: 1.35;
  color: rgba(58, 71, 82, 0.72);
}
.leave-upload__file {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  width: 100%;
}
.leave-upload__file-meta {
  min-width: 0;
  flex: 1;
}
.leave-upload__file-name {
  font-size: 0.9rem;
  font-weight: 650;
  color: #3a4752;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.leave-upload__file-size {
  font-size: 0.75rem;
  color: rgba(58, 71, 82, 0.68);
}
.leave-upload__file-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.15rem;
}
@media (max-width: 600px) {
  .leave-upload__file {
    flex-wrap: wrap;
  }
  .leave-upload__file-actions {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
