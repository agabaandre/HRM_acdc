<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalTableToolbar from '@/components/molecules/PortalTableToolbar.vue'
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import { resolveAvatarUrl } from '@/lib/api'
import {
  blockAuthUser,
  bulkCreateAuthUsers,
  fetchAuthUserGroups,
  fetchAuthUsers,
  impersonateAuthUser,
  resetAuthUserPassword,
  setAuthUserAllowEmailLogin,
  unblockAuthUser,
  updateAuthUser,
  type AuthUserGroup,
  type AuthUserRow,
} from '@/lib/authAdminApi'
import { useAuthStore } from '@/stores/auth'
import {
  downloadClientCsv,
  fetchAllPages,
  openClientPdfTable,
} from '@/lib/clientTableExport'
import { personAvatarName, toAbsoluteMediaUrl } from '@/lib/personAvatar'

const auth = useAuthStore()
const router = useRouter()

const initialLoading = ref(true)
const refreshing = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const rows = ref<AuthUserRow[]>([])
const groups = ref<AuthUserGroup[]>([])
const q = ref('')
const groupId = ref<number | ''>('')
const statusFilter = ref<number | ''>('')
const page = ref(1)
const perPage = ref(25)
const lastPage = ref(1)
const total = ref(0)
const actionLoadingId = ref<number | null>(null)
const bulkLoading = ref(false)

const showEdit = ref(false)
const editSaving = ref(false)
const editError = ref<string | null>(null)
const editing = ref<AuthUserRow | null>(null)
const editForm = reactive({
  name: '',
  role: null as number | null,
  status: 1,
  allow_email_login: 0,
})

let searchTimer: number | undefined
let loadSeq = 0
let suppressPageWatch = false

const statusOptions = [
  { title: 'All statuses', value: '' as const },
  { title: 'Active', value: 1 },
  { title: 'Inactive', value: 0 },
]

const exporting = ref(false)
const showTableSpinner = computed(() => initialLoading.value || refreshing.value)

const groupFilterOptions = computed(() => [
  { title: 'All groups', value: '' as const },
  ...groups.value.map((g) => ({ title: g.group_name, value: g.id })),
])

async function load() {
  const seq = ++loadSeq
  if (rows.value.length) refreshing.value = true
  else initialLoading.value = true
  error.value = null
  try {
    const res = await fetchAuthUsers({
      q: q.value.trim() || undefined,
      group_id: groupId.value === '' ? undefined : groupId.value,
      status: statusFilter.value === '' ? undefined : statusFilter.value,
      page: page.value,
      per_page: perPage.value,
    })
    if (seq !== loadSeq) return
    rows.value = res.data
    suppressPageWatch = true
    page.value = res.meta.current_page
    suppressPageWatch = false
    lastPage.value = res.meta.last_page
    total.value = res.meta.total
  } catch (e) {
    if (seq !== loadSeq) return
    error.value = apiErrorMessage(e, 'Could not load users')
  } finally {
    if (seq === loadSeq) {
      initialLoading.value = false
      refreshing.value = false
    }
  }
}

function resetToFirstPageAndLoad() {
  if (page.value !== 1) {
    suppressPageWatch = true
    page.value = 1
    suppressPageWatch = false
  }
  void load()
}

async function loadGroups() {
  try {
    groups.value = await fetchAuthUserGroups()
  } catch {
    groups.value = []
  }
}

function photoUrl(row: AuthUserRow): string | null {
  return toAbsoluteMediaUrl(resolveAvatarUrl(String(row.photo_url || '')))
}

function avatarName(row: AuthUserRow): string {
  return personAvatarName({
    fname: row.fname,
    oname: row.oname,
    lname: row.lname,
    name: row.name || row.staff_name,
  })
}

function openEdit(row: AuthUserRow) {
  editing.value = row
  editForm.name = String(row.name || '')
  editForm.role = row.role != null ? Number(row.role) : null
  editForm.status = Number(row.status ?? 1)
  editForm.allow_email_login = Number(row.allow_email_login ?? 0)
  editError.value = null
  showEdit.value = true
}

async function saveEdit() {
  if (!editing.value?.user_id || editForm.role == null) {
    editError.value = 'Name and group are required.'
    return
  }
  editSaving.value = true
  editError.value = null
  try {
    await updateAuthUser(editing.value.user_id, {
      name: editForm.name.trim(),
      role: editForm.role,
      status: editForm.status,
      allow_email_login: editForm.allow_email_login,
    })
    success.value = 'User updated.'
    showEdit.value = false
    await load()
  } catch (e) {
    editError.value = apiErrorMessage(e, 'Could not update user')
  } finally {
    editSaving.value = false
  }
}

async function runAction(row: AuthUserRow, action: 'block' | 'unblock' | 'reset' | 'email-on' | 'email-off') {
  const id = row.user_id
  const labels: Record<typeof action, string> = {
    block: `Block ${row.name}?`,
    unblock: `Activate ${row.name}?`,
    reset: `Reset password for ${row.name} to the system default?`,
    'email-on': `Allow email/password sign-in for ${row.name}?`,
    'email-off': `Revoke email/password sign-in for ${row.name}?`,
  }
  if (!window.confirm(labels[action])) return

  actionLoadingId.value = id
  error.value = null
  success.value = null
  try {
    if (action === 'block') success.value = await blockAuthUser(id)
    if (action === 'unblock') success.value = await unblockAuthUser(id)
    if (action === 'reset') success.value = await resetAuthUserPassword(id)
    if (action === 'email-on') success.value = await setAuthUserAllowEmailLogin(id, true)
    if (action === 'email-off') success.value = await setAuthUserAllowEmailLogin(id, false)
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Action failed')
  } finally {
    actionLoadingId.value = null
  }
}

async function onBulkCreate() {
  if (!window.confirm('Create portal accounts for active staff who do not yet have one?')) return
  bulkLoading.value = true
  error.value = null
  success.value = null
  try {
    const res = await bulkCreateAuthUsers()
    success.value = res.message
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Bulk create failed')
  } finally {
    bulkLoading.value = false
  }
}

function canImpersonate(row: AuthUserRow): boolean {
  if (auth.isImpersonating) return false
  if (!auth.hasPermission(17)) return false
  if (Number(row.status) !== 1) return false
  if (Number(row.user_id) === Number(auth.me?.id)) return false
  return true
}

async function onImpersonate(row: AuthUserRow) {
  if (!canImpersonate(row)) return
  if (!window.confirm(`Impersonate ${row.name}? You will act as this user for up to 5 minutes.`)) {
    return
  }
  actionLoadingId.value = row.user_id
  error.value = null
  success.value = null
  try {
    const payload = await impersonateAuthUser(row.user_id)
    auth.applyImpersonationPayload(payload)
    success.value = payload.message
    await router.push('/')
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not impersonate user')
  } finally {
    actionLoadingId.value = null
  }
}

function exportRowCells(row: AuthUserRow): (string | number)[] {
  return [
    row.user_id,
    row.name || '',
    row.auth_staff_id || '',
    row.sap_number || '',
    row.work_email || '',
    row.group_name || '',
    row.status_label || (Number(row.status) === 1 ? 'Active' : 'Inactive'),
    Number(row.allow_email_login) === 1 ? 'on' : 'off',
  ]
}

const exportHeaders = [
  'User ID',
  'Name',
  'Staff ID',
  'SAP',
  'Email',
  'Group',
  'Status',
  'Email login',
]

async function loadExportRows(): Promise<AuthUserRow[]> {
  return fetchAllPages((p, size) =>
    fetchAuthUsers({
      q: q.value || undefined,
      group_id: groupId.value === '' ? undefined : groupId.value,
      status: statusFilter.value === '' ? undefined : statusFilter.value,
      page: p,
      per_page: size,
    }),
  )
}

async function onExportCsv() {
  exporting.value = true
  error.value = null
  try {
    const all = await loadExportRows()
    downloadClientCsv(
      'portal-users.csv',
      exportHeaders,
      all.map(exportRowCells),
    )
  } catch (e) {
    error.value = apiErrorMessage(e, 'CSV export failed')
  } finally {
    exporting.value = false
  }
}

async function onExportPdf() {
  exporting.value = true
  error.value = null
  try {
    const all = await loadExportRows()
    openClientPdfTable(
      'Portal users',
      exportHeaders,
      all.map(exportRowCells),
    )
  } catch (e) {
    error.value = apiErrorMessage(e, 'PDF export failed')
  } finally {
    exporting.value = false
  }
}

function onPerPage(v: number) {
  perPage.value = v
  resetToFirstPageAndLoad()
}

watch(q, () => {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    resetToFirstPageAndLoad()
  }, 180)
})

watch([groupId, statusFilter], () => {
  resetToFirstPageAndLoad()
})

watch(page, () => {
  if (suppressPageWatch) return
  void load()
})

onMounted(() => {
  void Promise.all([loadGroups(), load()])
})
</script>

<template>
  <div>
    <PortalPageChrome title="Users" lede="Manage portal accounts.">
      <template #actions>
        <PortalBtn size="small" variant="outlined" :loading="bulkLoading" @click="onBulkCreate">
          Bulk create accounts
        </PortalBtn>
        <RouterLink to="/auth/audit-logs" style="text-decoration: none">
          <v-btn size="small" variant="outlined">Audit logs</v-btn>
        </RouterLink>
        <RouterLink to="/permissions" style="text-decoration: none">
          <v-btn size="small" variant="outlined">Permissions</v-btn>
        </RouterLink>
      </template>
    </PortalPageChrome>

    <div class="portal-staff-filters">
      <v-row dense>
        <v-col cols="12" md="5">
          <v-text-field
            v-model="q"
            label="Search name, email, staff ID, SAP"
            density="compact"
            clearable
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="4">
          <v-select
            v-model="groupId"
            :items="groupFilterOptions"
            item-title="title"
            item-value="value"
            label="Group"
            density="compact"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="statusFilter"
            :items="statusOptions"
            item-title="title"
            item-value="value"
            label="Status"
            density="compact"
            hide-details
          />
        </v-col>
      </v-row>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>

    <v-card class="portal-data-table-card mb-3 users-admin-table" variant="outlined">
      <div class="px-3 pt-1">
        <PortalTableToolbar
          placement="header"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          total-label="Total users"
          :exporting="exporting"
          @update:page="(v: number) => (page = v)"
          @update:per-page="onPerPage"
          @export-csv="onExportCsv"
          @export-pdf="onExportPdf"
        />
      </div>
      <div class="users-admin-table__body" :class="{ 'is-refreshing': refreshing && rows.length > 0 }">
        <div v-if="initialLoading && !rows.length" class="text-medium-emphasis px-4 py-3">Loading…</div>
        <div
          v-if="refreshing && rows.length > 0"
          class="users-admin-table__overlay"
          aria-live="polite"
        >
          Updating…
        </div>
      <v-table v-show="!(initialLoading && !rows.length)" density="compact">
        <thead>
          <tr>
            <th style="width: 3rem">#</th>
            <th>ID</th>
            <th>User</th>
            <th>Contact</th>
            <th>Role / status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="row.user_id">
            <td>
              <span class="portal-dt-row-num">{{ (page - 1) * perPage + index + 1 }}</span>
            </td>
            <td class="text-no-wrap">
              <v-chip size="x-small" variant="tonal">#{{ row.user_id }}</v-chip>
            </td>
            <td>
              <div class="d-flex align-center ga-2">
                <CbpAvatar size="sm" :name="avatarName(row)" :image-url="photoUrl(row)" />
                <div>
                  <div class="font-weight-medium">{{ row.name || '—' }}</div>
                  <div class="text-caption text-medium-emphasis">
                    <RouterLink v-if="row.auth_staff_id" :to="`/staff/${row.auth_staff_id}`">
                      Staff {{ row.auth_staff_id }}
                    </RouterLink>
                    <span v-if="row.sap_number"> · SAP {{ row.sap_number }}</span>
                  </div>
                </div>
              </div>
            </td>
            <td>
              <div>{{ row.work_email || '—' }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ [row.tel_1, row.tel_2].filter(Boolean).join(' · ') || '—' }}
              </div>
            </td>
            <td>
              <div>{{ row.group_name || '—' }}</div>
              <div class="d-flex flex-wrap ga-1 mt-1">
                <v-chip
                  size="x-small"
                  variant="tonal"
                  :color="Number(row.status) === 1 ? 'success' : 'error'"
                >
                  {{ row.status_label || (Number(row.status) === 1 ? 'Active' : 'Inactive') }}
                </v-chip>
                <v-chip
                  size="x-small"
                  variant="tonal"
                  :color="Number(row.allow_email_login) === 1 ? 'primary' : 'default'"
                >
                  Email {{ Number(row.allow_email_login) === 1 ? 'on' : 'off' }}
                </v-chip>
              </div>
            </td>
            <td class="text-end">
              <div class="d-inline-flex flex-wrap justify-end ga-1">
                <v-btn
                  v-if="canImpersonate(row)"
                  size="x-small"
                  variant="flat"
                  color="warning"
                  :loading="actionLoadingId === row.user_id"
                  @click="onImpersonate(row)"
                >
                  Impersonate
                </v-btn>
                <v-btn
                  size="x-small"
                  variant="outlined"
                  :loading="actionLoadingId === row.user_id"
                  @click="openEdit(row)"
                >
                  Edit
                </v-btn>
                <v-btn
                  v-if="Number(row.status) === 1"
                  size="x-small"
                  variant="text"
                  color="error"
                  :loading="actionLoadingId === row.user_id"
                  @click="runAction(row, 'block')"
                >
                  Block
                </v-btn>
                <v-btn
                  v-else
                  size="x-small"
                  variant="text"
                  color="success"
                  :loading="actionLoadingId === row.user_id"
                  @click="runAction(row, 'unblock')"
                >
                  Activate
                </v-btn>
                <v-btn
                  size="x-small"
                  variant="text"
                  :loading="actionLoadingId === row.user_id"
                  @click="runAction(row, 'reset')"
                >
                  Reset PW
                </v-btn>
                <v-btn
                  size="x-small"
                  variant="text"
                  :loading="actionLoadingId === row.user_id"
                  @click="runAction(row, Number(row.allow_email_login) === 1 ? 'email-off' : 'email-on')"
                >
                  {{ Number(row.allow_email_login) === 1 ? 'Revoke email' : 'Allow email' }}
                </v-btn>
              </div>
            </td>
          </tr>
          <tr v-if="!showTableSpinner && !rows.length">
            <td colspan="6" class="text-medium-emphasis text-center py-6">No users found.</td>
          </tr>
        </tbody>
      </v-table>
      </div>
      <div class="px-3 pb-1">
        <PortalTableToolbar
          placement="footer"
          :page="page"
          :last-page="lastPage"
          :total="total"
          :per-page="perPage"
          :show-csv="false"
          :show-pdf="false"
          :show-per-page="false"
          @update:page="(v: number) => (page = v)"
        />
      </div>
    </v-card>

    <v-dialog v-model="showEdit" max-width="560" persistent>
      <v-card>
        <v-card-title>Edit user</v-card-title>
        <v-card-text>
          <v-alert v-if="editError" type="error" variant="tonal" class="mb-3" density="compact">
            {{ editError }}
          </v-alert>
          <div v-if="editing" class="text-caption text-medium-emphasis mb-3">
            ID #{{ editing.user_id }}
            <span v-if="editing.auth_staff_id"> · Staff {{ editing.auth_staff_id }}</span>
            <span v-if="editing.work_email"> · {{ editing.work_email }}</span>
          </div>
          <v-text-field v-model="editForm.name" label="Display name" density="comfortable" class="mb-2" />
          <v-select
            v-model="editForm.role"
            :items="groups"
            item-title="group_name"
            item-value="id"
            label="User group"
            density="comfortable"
            class="mb-2"
          />
          <v-select
            v-model="editForm.status"
            :items="[
              { title: 'Active', value: 1 },
              { title: 'Inactive', value: 0 },
            ]"
            item-title="title"
            item-value="value"
            label="Status"
            density="comfortable"
            class="mb-2"
          />
          <v-switch
            v-model="editForm.allow_email_login"
            :true-value="1"
            :false-value="0"
            label="Allow email/password sign-in"
            color="primary"
            hide-details
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showEdit = false">Cancel</v-btn>
          <PortalBtn :loading="editSaving" @click="saveEdit">Save</PortalBtn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.users-admin-table :deep(th) {
  white-space: nowrap;
}

.users-admin-table__body {
  position: relative;
  min-height: 4rem;
}

.users-admin-table__body.is-refreshing :deep(table) {
  opacity: 0.55;
  transition: opacity 0.15s ease;
}

.users-admin-table__overlay {
  position: absolute;
  top: 0.65rem;
  right: 0.85rem;
  z-index: 2;
  font-size: 0.8rem;
  color: #3a4752;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid #dfe5ef;
  border-radius: 999px;
  padding: 0.2rem 0.65rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
</style>
