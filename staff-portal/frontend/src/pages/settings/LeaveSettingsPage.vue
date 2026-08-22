<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import LeaveHolidaysTab from '@/components/leave/LeaveHolidaysTab.vue'
import { useAuthStore } from '@/stores/auth'
import { LEAVE_PERMS } from '@/lib/leavePermissions'
import {
  fetchLeavePolicy,
  fetchSettingsLeaveTypes,
  saveLeavePolicy,
  saveLeaveType,
  type LeaveTypeDto,
} from '@/lib/leaveApi'

const auth = useAuthStore()
const router = useRouter()

const tab = ref<'policy' | 'types' | 'holidays'>('policy')
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const policy = reactive<Record<string, unknown>>({})
const types = ref<LeaveTypeDto[]>([])

const editingTypeId = ref<number | null>(null)
const typeForm = reactive({
  leave_name: '',
  code: '',
  leave_days: 0,
  is_accrued: false,
  accrual_rate: 2.33,
  requires_hr_approval: false,
  requires_medical_certificate: false,
  deduct_compensatory_first: false,
  policy_notes: '',
})

const canManageSettings = computed(
  () =>
    !!auth.me?.profile?.is_hr ||
    auth.me?.profile?.role_id === 20 ||
    auth.hasPermission(LEAVE_PERMS.MANAGE_SETTINGS) ||
    auth.hasPermission(15),
)
const canManageHolidays = computed(
  () =>
    canManageSettings.value ||
    auth.hasPermission(LEAVE_PERMS.MANAGE_HOLIDAYS),
)

function resetTypeForm() {
  editingTypeId.value = null
  typeForm.leave_name = ''
  typeForm.code = ''
  typeForm.leave_days = 0
  typeForm.is_accrued = false
  typeForm.accrual_rate = 2.33
  typeForm.requires_hr_approval = false
  typeForm.requires_medical_certificate = false
  typeForm.deduct_compensatory_first = false
  typeForm.policy_notes = ''
}

function editType(t: LeaveTypeDto) {
  editingTypeId.value = t.leave_id
  typeForm.leave_name = t.leave_name
  typeForm.code = t.code ?? ''
  typeForm.leave_days = t.leave_days
  typeForm.is_accrued = t.is_accrued
  typeForm.accrual_rate = t.accrual_rate
  typeForm.requires_hr_approval = t.requires_hr_approval
  typeForm.requires_medical_certificate = t.requires_medical_certificate
  typeForm.deduct_compensatory_first = t.deduct_compensatory_first
  typeForm.policy_notes = t.policy_notes ?? ''
}

function onHolidayStatus(payload: { success?: string | null; error?: string | null }) {
  if (payload.success !== undefined) success.value = payload.success ?? null
  if (payload.error !== undefined) error.value = payload.error ?? null
}

async function load() {
  if (!canManageSettings.value && !canManageHolidays.value) {
    error.value = 'Leave settings require the Manage Leave Settings or Manage Leave Holidays permission (or HR).'
    return
  }
  if (!canManageSettings.value) {
    tab.value = 'holidays'
    return
  }
  loading.value = true
  error.value = null
  try {
    const [p, t] = await Promise.all([fetchLeavePolicy(), fetchSettingsLeaveTypes()])
    Object.keys(policy).forEach((k) => delete policy[k])
    Object.assign(policy, p)
    types.value = t
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load leave settings')
  } finally {
    loading.value = false
  }
}

async function onSavePolicy() {
  saving.value = true
  success.value = null
  error.value = null
  try {
    await saveLeavePolicy({ ...policy })
    success.value = 'Leave policy and accumulation rules saved.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save policy')
  } finally {
    saving.value = false
  }
}

async function onSaveType() {
  saving.value = true
  success.value = null
  error.value = null
  try {
    await saveLeaveType({ ...typeForm }, editingTypeId.value)
    success.value = editingTypeId.value ? 'Leave type updated.' : 'Leave type created.'
    resetTypeForm()
    types.value = await fetchSettingsLeaveTypes()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save leave type')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!auth.me) {
    try {
      await auth.fetchMe()
    } catch {
      /* handled by router */
    }
  }
  if (!canManageSettings.value && !canManageHolidays.value) {
    await router.replace('/leave')
    return
  }
  void load()
})
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-3">
      <CbpPageHeading title="Leave configuration" subtitle="Policy rules, leave types, and public holidays." />
      <RouterLink to="/leave" style="text-decoration:none">
        <PortalBtn variant="outlined" color="primary" size="small">Back to Leave</PortalBtn>
      </RouterLink>
    </div>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else>
      <v-tabs v-model="tab" color="primary" class="mb-4">
        <v-tab v-if="canManageSettings" value="policy">Accumulation &amp; policy rules</v-tab>
        <v-tab v-if="canManageSettings" value="types">Leave types</v-tab>
        <v-tab v-if="canManageHolidays" value="holidays">Holidays</v-tab>
      </v-tabs>

      <v-card v-if="tab === 'policy'" variant="outlined">
        <v-card-text>
          <h3 class="text-h6 text-primary mb-3">Annual leave accumulation</h3>
          <v-row>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="policy.annual_accrual_per_month" type="number" step="0.01" label="Accrual per completed month (days)" hint="Default: 2.33" persistent-hint />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="policy.annual_min_days_per_year" type="number" label="Minimum days required per year" />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="policy.annual_max_carry_forward" type="number" label="Maximum carry-forward (days)" />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="policy.annual_max_per_calendar_year" type="number" label="Maximum per calendar year" />
            </v-col>
            <v-col cols="12" md="4" class="d-flex align-center">
              <v-checkbox v-model="policy.annual_prorate_mid_year_join" label="Prorate for staff joining after January" hide-details />
            </v-col>
            <v-col cols="12" md="4" class="d-flex align-center">
              <v-checkbox v-model="policy.annual_forfeit_unused_minimum" label="Forfeit if minimum annual days not taken" hide-details />
            </v-col>
          </v-row>

          <h3 class="text-h6 text-primary mb-3 mt-4">Compensatory leave</h3>
          <v-row>
            <v-col cols="12" md="4" class="d-flex align-center">
              <v-checkbox v-model="policy.deduct_compensatory_first" label="Deduct compensatory balance first" hide-details />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="policy.compensatory_expiry_months" type="number" label="Other compensatory expiry (months)" hint="Travel/overtime credits. Default: 3." persistent-hint />
            </v-col>
            <v-col cols="12" md="4">
              <v-text-field v-model.number="policy.holiday_compensatory_max_days_per_year" type="number" label="Holiday compensatory cap (days / year)" hint="Unused holiday compensatory forfeits on 31 Dec of the year earned. Default: 15." persistent-hint />
            </v-col>
          </v-row>

          <h3 class="text-h6 text-primary mb-3 mt-4">Weekday public holidays in a leave request</h3>
          <v-row>
            <v-col cols="12" md="8">
              <v-select
                v-model="policy.weekday_holiday_in_request"
                :items="[
                  { title: 'A — Do not count weekday holidays as leave days (default)', value: 'skip_all' },
                  { title: 'B — Count weekday holidays as leave days', value: 'count_all' },
                  { title: 'C — Skip weekday holidays only for annual leave', value: 'skip_annual_only' },
                ]"
                label="How weekday holidays affect requested days"
              />
            </v-col>
          </v-row>

          <h3 class="text-h6 text-primary mb-3 mt-4">Sick leave</h3>
          <v-row>
            <v-col cols="12" md="3">
              <v-text-field v-model.number="policy.sick_full_pay_months" type="number" label="Full pay (months / year)" />
            </v-col>
            <v-col cols="12" md="3">
              <v-text-field v-model.number="policy.sick_half_pay_months" type="number" label="Half pay (months / year)" />
            </v-col>
            <v-col cols="12" md="3">
              <v-text-field v-model.number="policy.sick_medical_report_after_days" type="number" label="Medical report after (days)" />
            </v-col>
            <v-col cols="12" md="3" class="d-flex align-center">
              <v-checkbox v-model="policy.sick_medical_certificate_required" label="Medical certificate required" hide-details />
            </v-col>
          </v-row>

          <h3 class="text-h6 text-primary mb-3 mt-4">Leave applications</h3>
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="policy.application_min_notice_days"
                type="number"
                min="0"
                label="Minimum notice (calendar days)"
                hint="Staff must apply this many days before leave starts. Past dates are never allowed. Default: 7."
                persistent-hint
              />
            </v-col>
          </v-row>

          <h3 class="text-h6 text-primary mb-3 mt-4">Maternity &amp; paternity</h3>
          <v-row>
            <v-col cols="12" md="3">
              <v-text-field v-model.number="policy.maternity_calendar_days" type="number" label="Maternity (calendar days)" />
            </v-col>
            <v-col cols="12" md="3">
              <v-text-field v-model.number="policy.maternity_max_instances" type="number" label="Maternity max instances" />
            </v-col>
            <v-col cols="12" md="3">
              <v-text-field v-model.number="policy.paternity_working_days" type="number" label="Paternity (working days)" />
            </v-col>
            <v-col cols="12" md="3">
              <v-text-field v-model.number="policy.paternity_max_periods" type="number" label="Paternity max periods" />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <PortalBtn :loading="saving" @click="onSavePolicy">Save policy rules</PortalBtn>
        </v-card-actions>
      </v-card>

      <v-row v-else-if="tab === 'types'">
        <v-col cols="12" lg="5">
          <v-card variant="outlined">
            <v-card-title>{{ editingTypeId ? 'Edit leave type' : 'Add leave type' }}</v-card-title>
            <v-card-text>
              <v-text-field v-model="typeForm.leave_name" label="Name" required />
              <v-text-field v-model="typeForm.code" label="Code" placeholder="ANNUAL, SICK, …" />
              <v-row dense>
                <v-col cols="6">
                  <v-text-field v-model.number="typeForm.leave_days" type="number" label="Entitlement days" />
                </v-col>
                <v-col cols="6">
                  <v-text-field v-model.number="typeForm.accrual_rate" type="number" step="0.01" label="Accrual rate / month" />
                </v-col>
              </v-row>
              <v-checkbox v-model="typeForm.is_accrued" label="Accrues monthly" hide-details />
              <v-checkbox v-model="typeForm.requires_hr_approval" label="Requires Head of HR approval" hide-details />
              <v-checkbox v-model="typeForm.requires_medical_certificate" label="Requires medical certificate" hide-details />
              <v-checkbox v-model="typeForm.deduct_compensatory_first" label="Deduct compensatory first" hide-details />
              <v-textarea v-model="typeForm.policy_notes" label="Policy notes" rows="2" class="mt-2" />
            </v-card-text>
            <v-card-actions>
              <PortalBtn size="small" :loading="saving" @click="onSaveType">Save type</PortalBtn>
              <v-btn v-if="editingTypeId" variant="text" size="small" @click="resetTypeForm">Cancel</v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
        <v-col cols="12" lg="7">
          <v-card variant="outlined">
            <v-card-title>Leave types</v-card-title>
            <v-table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Code</th>
                  <th>Days</th>
                  <th>Accrued</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in types" :key="t.leave_id">
                  <td>{{ t.leave_name }}</td>
                  <td><code>{{ t.code }}</code></td>
                  <td>{{ t.leave_days }}</td>
                  <td>{{ t.is_accrued ? `${t.accrual_rate}/mo` : '—' }}</td>
                  <td>
                    <PortalBtn size="x-small" variant="outlined" color="primary" @click="editType(t)">
                      Edit
                    </PortalBtn>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card>
        </v-col>
      </v-row>

      <LeaveHolidaysTab
        v-else-if="tab === 'holidays'"
        @status="onHolidayStatus"
      />
    </template>
  </div>
</template>
