<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import ProfileSignaturePad from '@/components/molecules/ProfileSignaturePad.vue'
import { resolveAvatarUrl } from '@/lib/api'
import { LEAVE_PERMS } from '@/lib/leavePermissions'
import {
  fetchPerformanceHub,
  type PerformanceSelfActions,
} from '@/lib/performanceApi'
import {
  fetchMyProfile,
  updateMyProfile,
  uploadMyPassport,
  uploadMyPhoto,
  uploadMySignatureDataUrl,
  uploadMySignatureFile,
  type MyProfilePayload,
  type NextOfKinRow,
} from '@/lib/profileApi'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

const auth = useAuthStore()
const localeStore = useLocaleStore()
const loading = ref(true)
const selfActions = ref<PerformanceSelfActions | null>(null)
const saving = ref(false)
const uploading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const payload = ref<MyProfilePayload | null>(null)
const photoInput = ref<HTMLInputElement | null>(null)
const passportInput = ref<HTMLInputElement | null>(null)

const form = reactive({
  private_email: '',
  whatsapp: '',
  tel_1: '',
  tel_2: '',
  langauge: 'en' as string | null,
  residential_address_duty_station: '',
  number_of_dependants: 0,
  next_of_kin: [
    { name: '', relationship_id: '' as number | '', phone: '', email: '' },
    { name: '', relationship_id: '' as number | '', phone: '', email: '' },
  ] as NextOfKinRow[],
})

const languageItems = computed(() => {
  const fromProfile = payload.value?.lookups.languages || []
  const fromStore = localeStore.languages
  const rows = fromProfile.length ? fromProfile : fromStore
  if (!rows.length) {
    return [
      { title: 'English', value: 'en' },
      { title: 'Français', value: 'fr' },
      { title: 'العربية', value: 'ar' },
      { title: 'Español', value: 'es' },
      { title: 'Português', value: 'pt' },
      { title: 'Kiswahili', value: 'sw' },
    ]
  }
  return rows.map((row) => ({
    title: `${row.flag ? `${row.flag} ` : ''}${row.name}`,
    value: row.code,
  }))
})

const displayName = computed(() => {
  const s = payload.value?.staff
  if (!s) return auth.me?.name || 'My profile'
  return [s.lname, s.fname, s.oname].filter(Boolean).join(' ')
})

const photoUrl = computed(() => resolveAvatarUrl(payload.value?.media.photo_url))
const signatureUrl = computed(() => resolveAvatarUrl(payload.value?.media.signature_url))
const passportUrl = computed(() => resolveAvatarUrl(payload.value?.media.passport_url))

const kinItems = computed(() =>
  (payload.value?.lookups.kin_relationship_types || []).map((k) => ({
    title: k.name,
    value: k.id,
  })),
)

const roleId = computed(() => Number(auth.me?.profile?.role_id || 0))
const hasLinkedStaff = computed(() => Number(auth.me?.profile?.staff_id || 0) > 0)
const isHr = computed(
  () =>
    !!auth.me?.profile?.is_hr ||
    !!auth.me?.profile?.is_hr_admin ||
    roleId.value === 20 ||
    roleId.value === 22,
)
const isSystemAdmin = computed(() => !!auth.me?.profile?.is_system_admin || roleId.value === 10)

const canApplyLeave = computed(
  () =>
    auth.isModuleEnabled('leave') &&
    (auth.hasPermission(LEAVE_PERMS.MAKE_REQUEST) || hasLinkedStaff.value || isHr.value || isSystemAdmin.value),
)

const canUsePerformance = computed(
  () =>
    auth.isModuleEnabled('performance') &&
    (auth.hasPermission(74) || isHr.value || isSystemAdmin.value),
)

type ProfileQuickAction = {
  key: string
  label: string
  icon: string
  to: string
  primary?: boolean
}

const quickActions = computed((): ProfileQuickAction[] => {
  const items: ProfileQuickAction[] = []
  if (canApplyLeave.value) {
    items.push({
      key: 'leave',
      label: localeStore.t('profile.apply_leave', 'Apply for leave'),
      icon: 'fa-solid fa-calendar-plus',
      to: '/leave/apply',
      primary: true,
    })
  }
  if (canUsePerformance.value) {
    const self = selfActions.value
    items.push({
      key: 'ppa',
      label: self?.ppa_exists
        ? localeStore.t('profile.open_ppa', 'Open PPA')
        : localeStore.t('profile.make_ppa', 'Make PPA'),
      icon: 'fa-solid fa-file-signature',
      to: ppaPath(self),
    })
    items.push({
      key: 'midterm',
      label: localeStore.t('profile.midterm', 'Midterm'),
      icon: 'fa-solid fa-clipboard-check',
      to: self?.midterm_url || ppaPath(self),
    })
    items.push({
      key: 'endterm',
      label: localeStore.t('profile.endterm', 'Endterm'),
      icon: 'fa-solid fa-flag-checkered',
      to: self?.endterm_url || ppaPath(self),
    })
  }
  return items
})

function ppaPath(self: PerformanceSelfActions | null): string {
  if (!self) return '/performance'
  if (self.show_create_ppa) return self.create_ppa_url || '/performance/create'
  if (self.show_current_ppa && self.current_ppa_url) return self.current_ppa_url
  return '/performance'
}

async function loadSelfActions() {
  if (!canUsePerformance.value) {
    selfActions.value = null
    return
  }
  try {
    const hub = await fetchPerformanceHub({ tab: 'dashboard' })
    selfActions.value = hub.self_actions ?? null
  } catch {
    selfActions.value = null
  }
}

function applyPayload(next: MyProfilePayload) {
  payload.value = next
  const s = next.staff
  form.private_email = s.private_email || ''
  form.whatsapp = s.whatsapp || ''
  form.tel_1 = s.tel_1 || ''
  form.tel_2 = s.tel_2 || ''
  form.langauge = s.langauge || 'en'
  form.residential_address_duty_station = s.residential_address_duty_station || ''
  form.number_of_dependants = Number(s.number_of_dependants ?? 0)
  const nok = s.next_of_kin || []
  form.next_of_kin = [0, 1].map((i) => ({
    name: nok[i]?.name || '',
    relationship_id: nok[i]?.relationship_id || '',
    phone: nok[i]?.phone || '',
    email: nok[i]?.email || '',
  }))
}

async function load() {
  loading.value = true
  error.value = null
  try {
    applyPayload(await fetchMyProfile())
    void loadSelfActions()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load your profile')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    applyPayload(
      await updateMyProfile({
        private_email: form.private_email,
        whatsapp: form.whatsapp || null,
        tel_1: form.tel_1,
        tel_2: form.tel_2 || null,
        langauge: form.langauge,
        residential_address_duty_station: form.residential_address_duty_station,
        number_of_dependants: Number(form.number_of_dependants),
        next_of_kin: form.next_of_kin.map((row) => ({
          ...row,
          relationship_id: row.relationship_id === '' ? 0 : Number(row.relationship_id),
        })),
      }),
    )
    success.value = 'Profile updated successfully.'
    await auth.fetchMe()
    if (form.langauge) {
      try {
        await localeStore.setLocale(form.langauge)
      } catch {
        /* profile language is already saved */
      }
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save profile')
  } finally {
    saving.value = false
  }
}

async function onPhoto(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploading.value = true
  error.value = null
  try {
    applyPayload(await uploadMyPhoto(file))
    success.value = 'Photo updated.'
    await auth.fetchMe()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Photo upload failed')
  } finally {
    uploading.value = false
    ;(e.target as HTMLInputElement).value = ''
  }
}

async function onPassport(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploading.value = true
  error.value = null
  try {
    applyPayload(await uploadMyPassport(file))
    success.value = 'Passport biodata updated.'
  } catch (err) {
    error.value = apiErrorMessage(err, 'Passport upload failed')
  } finally {
    uploading.value = false
    ;(e.target as HTMLInputElement).value = ''
  }
}

async function onSignatureDataUrl(dataUrl: string) {
  uploading.value = true
  error.value = null
  try {
    applyPayload(await uploadMySignatureDataUrl(dataUrl))
    success.value = 'Signature updated.'
  } catch (err) {
    error.value = apiErrorMessage(err, 'Signature save failed')
  } finally {
    uploading.value = false
  }
}

async function onSignatureFile(file: File) {
  uploading.value = true
  error.value = null
  try {
    applyPayload(await uploadMySignatureFile(file))
    success.value = 'Signature updated.'
  } catch (err) {
    error.value = apiErrorMessage(err, 'Signature upload failed')
  } finally {
    uploading.value = false
  }
}

function kinLabel(id: number | string | ''): string {
  if (id === '' || id == null) return '—'
  const found = payload.value?.lookups.kin_relationship_types.find((k) => k.id === Number(id))
  return found?.name || '—'
}

onMounted(load)
</script>

<template>
  <div>
    <CbpPageHeading
      :title="localeStore.t('profile.title', 'My profile')"
      :subtitle="displayName"
    />

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">
      {{ error }}
    </v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-4" closable @click:close="success = null">
      {{ success }}
    </v-alert>

    <v-card v-if="!loading && payload && quickActions.length" variant="outlined" class="mb-4">
      <v-card-text class="py-3">
        <div class="text-subtitle-2 mb-2">{{ localeStore.t('profile.quick_actions', 'Quick actions') }}</div>
        <div class="d-flex flex-wrap ga-2">
          <v-btn
            v-for="action in quickActions"
            :key="action.key"
            class="profile-quick-btn"
            :to="action.to"
            :color="action.primary ? 'primary' : undefined"
            :variant="action.primary ? 'flat' : 'tonal'"
            size="small"
          >
            <i :class="[action.icon, 'me-2']" aria-hidden="true" />
            {{ action.label }}
          </v-btn>
        </div>
      </v-card-text>
    </v-card>

    <div v-if="loading" class="text-medium-emphasis py-8">{{ localeStore.t('profile.loading', 'Loading your profile…') }}</div>

    <v-row v-else-if="payload" density="compact">
      <v-col cols="12" md="5">
        <v-card variant="outlined" class="mb-4">
          <v-card-text>
            <div class="d-flex align-center ga-4 mb-4">
              <v-avatar size="108" rounded="lg" color="primary" class="profile-avatar">
                <v-img v-if="photoUrl" :src="photoUrl" alt="" cover />
                <span v-else class="text-h6">{{ displayName.slice(0, 2).toUpperCase() }}</span>
              </v-avatar>
              <div>
                <div class="text-h6">{{ displayName }}</div>
                <div class="text-medium-emphasis">{{ payload.contract?.job_name || '—' }}</div>
                <div class="d-flex flex-wrap ga-1 mt-1">
                  <v-chip v-if="payload.contract?.contract_type" size="x-small" variant="tonal">
                    {{ payload.contract.contract_type }}
                  </v-chip>
                  <v-chip v-if="payload.contract?.grade" size="x-small" variant="tonal">
                    {{ payload.contract.grade }}
                  </v-chip>
                </div>
              </div>
            </div>

            <div class="profile-summary-grid">
              <div>
                <span>SAPNO</span>
                <strong>{{ payload.staff.SAPNO || '—' }}</strong>
              </div>
              <div>
                <span>Gender</span>
                <strong>{{ payload.staff.gender || '—' }}</strong>
              </div>
              <div>
                <span>Date of birth</span>
                <strong>{{ payload.staff.date_of_birth || '—' }}</strong>
              </div>
              <div>
                <span>Nationality</span>
                <strong>{{ payload.staff.nationality || '—' }}</strong>
              </div>
              <div>
                <span>Work email</span>
                <strong>{{ payload.staff.work_email || '—' }}</strong>
              </div>
              <div>
                <span>Private email</span>
                <strong>{{ payload.staff.private_email || '—' }}</strong>
              </div>
              <div>
                <span>Phone</span>
                <strong>{{ payload.staff.tel_1 || '—' }} {{ payload.staff.tel_2 || '' }}</strong>
              </div>
              <div>
                <span>WhatsApp</span>
                <strong>{{ payload.staff.whatsapp || '—' }}</strong>
              </div>
              <div>
                <span>Address</span>
                <strong>{{ payload.staff.residential_address_duty_station || '—' }}</strong>
              </div>
              <div>
                <span>Dependants</span>
                <strong>{{ payload.staff.number_of_dependants ?? '—' }}</strong>
              </div>
            </div>

            <v-divider class="my-4" />
            <div class="text-subtitle-2 mb-2">Next of kin</div>
            <div v-for="(row, idx) in payload.staff.next_of_kin" :key="idx" class="mb-2 text-body-2">
              <strong>{{ idx === 0 ? 'Primary' : 'Secondary' }}:</strong>
              {{ row.name || '—' }}
              <span class="text-medium-emphasis">({{ kinLabel(row.relationship_id) }})</span>
              · {{ row.phone || '—' }} · {{ row.email || '—' }}
            </div>

            <v-divider class="my-4" />
            <div class="text-subtitle-2 mb-2">Employment (managed by HR)</div>
            <div class="profile-summary-grid">
              <div>
                <span>Division</span>
                <strong>{{ payload.contract?.division_name || '—' }}</strong>
              </div>
              <div>
                <span>Duty station</span>
                <strong>{{ payload.contract?.duty_station_name || '—' }}</strong>
              </div>
              <div>
                <span>Job</span>
                <strong>{{ payload.contract?.job_name || '—' }}</strong>
              </div>
              <div>
                <span>Acting</span>
                <strong>{{ payload.contract?.job_acting || '—' }}</strong>
              </div>
              <div>
                <span>Funder</span>
                <strong>{{ payload.contract?.funder || '—' }}</strong>
              </div>
              <div>
                <span>Status</span>
                <strong>{{ payload.contract?.status_label || '—' }}</strong>
              </div>
              <div>
                <span>Start</span>
                <strong>{{ payload.contract?.start_date || '—' }}</strong>
              </div>
              <div>
                <span>End</span>
                <strong>{{ payload.contract?.end_date || '—' }}</strong>
              </div>
              <div>
                <span>Supervisor</span>
                <strong>{{ payload.supervisors.first?.name || '—' }}</strong>
              </div>
              <div>
                <span>2nd supervisor</span>
                <strong>{{ payload.supervisors.second?.name || '—' }}</strong>
              </div>
            </div>

            <v-divider class="my-4" />
            <div class="text-subtitle-2 mb-2">Documents</div>
            <div class="mb-2">
              <div class="text-caption text-medium-emphasis">Signature</div>
              <v-img v-if="signatureUrl" :src="signatureUrl" max-height="80" max-width="240" contain />
              <span v-else class="text-medium-emphasis">Not uploaded</span>
            </div>
            <div>
              <div class="text-caption text-medium-emphasis">Passport biodata</div>
              <a v-if="passportUrl" :href="passportUrl" target="_blank" rel="noopener">
                {{ payload.media.passport_is_pdf ? 'View PDF' : 'View image' }}
              </a>
              <span v-else class="text-medium-emphasis">Not uploaded</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="7">
        <v-card variant="outlined">
          <v-card-title class="text-subtitle-1">Edit my details</v-card-title>
          <v-card-text>
            <v-form @submit.prevent="save">
              <div class="text-subtitle-2 mb-2">Contact & language</div>
              <v-row density="compact">
                <v-col cols="12" sm="6">
                  <v-text-field v-model="form.private_email" label="Private email" type="email" placeholder=" " />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="form.whatsapp" label="WhatsApp" placeholder=" " />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="form.tel_1" label="Telephone 1" placeholder=" " />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="form.tel_2" label="Telephone 2" placeholder=" " />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.langauge"
                    :items="languageItems"
                    :label="localeStore.t('chrome.language', 'Language')"
                    placeholder=" "
                  />
                </v-col>
              </v-row>

              <div class="text-subtitle-2 mt-4 mb-2">Photo / passport / signature</div>
              <div class="d-flex flex-wrap ga-2 mb-3">
                <input
                  ref="photoInput"
                  type="file"
                  accept="image/png,image/jpeg,image/jpg,image/gif,image/webp"
                  class="d-none"
                  @change="onPhoto"
                />
                <input
                  ref="passportInput"
                  type="file"
                  accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,application/pdf"
                  class="d-none"
                  @change="onPassport"
                />
                <v-btn size="small" variant="tonal" :loading="uploading" @click="photoInput?.click()">
                  {{ localeStore.t('profile.upload_photo', 'Upload photo') }}
                </v-btn>
                <v-btn size="small" variant="tonal" :loading="uploading" @click="passportInput?.click()">
                  {{ localeStore.t('profile.upload_passport', 'Upload passport biodata') }}
                </v-btn>
              </div>
              <ProfileSignaturePad @save-data-url="onSignatureDataUrl" @save-file="onSignatureFile" />

              <div class="text-subtitle-2 mt-6 mb-2">Address & dependants</div>
              <v-row density="compact">
                <v-col cols="12">
                  <v-textarea
                    v-model="form.residential_address_duty_station"
                    label="Residential address (at duty station)"
                    rows="2"
                    placeholder=" "
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model.number="form.number_of_dependants"
                    label="Number of dependants"
                    type="number"
                    min="0"
                    placeholder=" "
                  />
                </v-col>
              </v-row>

              <div class="text-subtitle-2 mt-4 mb-2">Next of kin</div>
              <div v-for="(row, idx) in form.next_of_kin" :key="idx" class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ idx === 0 ? 'Primary (required)' : 'Secondary (optional)' }}
                </div>
                <v-row density="compact">
                  <v-col cols="12" sm="6">
                    <v-text-field v-model="row.name" label="Full name" placeholder=" " />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="row.relationship_id"
                      :items="kinItems"
                      label="Relationship"
                      clearable
                      placeholder=" "
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field v-model="row.phone" label="Phone" placeholder=" " />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field v-model="row.email" label="Email" type="email" placeholder=" " />
                  </v-col>
                </v-row>
              </div>

              <div class="d-flex flex-wrap ga-2 mt-2">
                <v-btn type="submit" color="primary" :loading="saving">{{ localeStore.t('profile.save_changes', 'Save changes') }}</v-btn>
                <v-btn
                  v-if="auth.passwordLoginAvailable"
                  variant="outlined"
                  :to="{ name: 'profile-password' }"
                >
                  Change password
                </v-btn>
              </div>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<style scoped>
.profile-avatar :deep(.v-img__img) {
  object-fit: cover;
  object-position: center 26%;
}
.profile-quick-btn {
  text-transform: none;
  font-weight: 600;
  letter-spacing: 0.01em;
}
.profile-summary-grid {
  display: grid;
  gap: 0.7rem 1.25rem;
  grid-template-columns: 1fr;
  font-size: 0.9rem;
  color: #3a4752;
}
.profile-summary-grid > div span {
  display: block;
  color: #768b9e;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 0.15rem;
}
.profile-summary-grid > div strong {
  display: block;
  color: #3a4752;
  font-weight: 600;
  line-height: 1.35;
  word-break: break-word;
}
@media (min-width: 600px) {
  .profile-summary-grid {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
