<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import {
  copyGroupPermissionsToUser,
  createPermissionDefinition,
  createPermissionGroup,
  fetchGroupAssignments,
  fetchPermissionGroups,
  fetchPermissionsBootstrap,
  fetchPermissionsCatalog,
  fetchPermissionUsers,
  fetchUserAssignments,
  saveGroupAssignments,
  saveUserAssignments,
  type PermItem,
} from '@/lib/permissionsApi'

type Mode = 'group' | 'user'

const mode = ref<Mode>('group')
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const assignmentEnabled = ref(false)

const categories = ref<Record<string, PermItem[]>>({})
const groups = ref<Array<{ id: number; group_name: string; user_count: number }>>([])
const selectedGroupId = ref<number | null>(null)
const selectedUserId = ref<number | null>(null)
const selectedPermissionIds = ref<number[]>([])
const selectedUserMeta = ref<{ name: string; group_name?: string; group_permission_count: number } | null>(null)

const userSearch = ref('')
const userGroupFilter = ref<number | null>(null)
const users = ref<Array<{ user_id: number; name: string; role: number; group_name?: string; custom_permission_count: number }>>([])
const userPage = ref(1)
const userLastPage = ref(1)

const showGroupModal = ref(false)
const showPermModal = ref(false)
const newGroupName = ref('')
const newPermName = ref('')
const newPermDefinition = ref('')

const selectedSet = computed(() => new Set(selectedPermissionIds.value.map(Number)))
const groupCount = computed(() => groups.value.length)
const userCount = computed(() => users.value.length)
const selectedPermissionCount = computed(() => selectedPermissionIds.value.length)
const totalPermissionCount = computed(() =>
  Object.values(categories.value).reduce((sum, list) => sum + list.length, 0),
)

const categorySections = computed(() =>
  Object.entries(categories.value).map(([name, perms]) => ({
    name,
    perms,
    assigned: perms.filter((p) => selectedSet.value.has(Number(p.id))).length,
  })),
)

const selectedGroup = computed(() => groups.value.find((g) => g.id === selectedGroupId.value) ?? null)

function togglePermission(id: number, checked: boolean) {
  if (!assignmentEnabled.value) return
  const set = new Set(selectedPermissionIds.value)
  if (checked) set.add(id)
  else set.delete(id)
  selectedPermissionIds.value = [...set]
}

function toggleCategory(perms: PermItem[], checked: boolean) {
  if (!assignmentEnabled.value) return
  const set = new Set(selectedPermissionIds.value)
  for (const p of perms) {
    if (checked) set.add(Number(p.id))
    else set.delete(Number(p.id))
  }
  selectedPermissionIds.value = [...set]
}

function selectGroup(id: number) {
  selectedGroupId.value = id
}

function selectUser(id: number) {
  selectedUserId.value = id
  void loadAssignments()
}

let suppressGroupWatch = false

async function loadAssignments() {
  assignmentEnabled.value = false
  if (mode.value === 'group' && selectedGroupId.value) {
    selectedPermissionIds.value = await fetchGroupAssignments(selectedGroupId.value)
    selectedUserMeta.value = null
  } else if (mode.value === 'user' && selectedUserId.value) {
    const res = await fetchUserAssignments(selectedUserId.value)
    selectedPermissionIds.value = res.permission_ids
    selectedUserMeta.value = {
      name: res.user.name,
      group_name: res.user.group_name,
      group_permission_count: res.group_permission_count,
    }
  } else {
    selectedPermissionIds.value = []
  }
}

async function loadUsers() {
  const res = await fetchPermissionUsers({
    q: userSearch.value || undefined,
    group_id: userGroupFilter.value,
    page: userPage.value,
    per_page: 20,
  })
  users.value = res.data
  userLastPage.value = res.meta.last_page
}

async function bootstrap() {
  loading.value = true
  error.value = null
  try {
    // One round-trip for catalog + groups + first group assignments.
    const boot = await fetchPermissionsBootstrap(selectedGroupId.value)
    categories.value = boot.catalog.categories
    groups.value = boot.groups
    suppressGroupWatch = true
    selectedGroupId.value = boot.selected_group_id
    selectedPermissionIds.value = boot.permission_ids
    selectedUserMeta.value = null
    suppressGroupWatch = false
    if (mode.value === 'user') await loadUsers()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load permissions')
  } finally {
    loading.value = false
  }
}

async function onSave() {
  if (!assignmentEnabled.value) {
    error.value = 'Enable editing before saving.'
    return
  }
  saving.value = true
  success.value = null
  error.value = null
  try {
    if (mode.value === 'group' && selectedGroupId.value) {
      await saveGroupAssignments(selectedGroupId.value, selectedPermissionIds.value)
      success.value = 'Group permissions saved.'
    } else if (mode.value === 'user' && selectedUserId.value) {
      await saveUserAssignments(selectedUserId.value, selectedPermissionIds.value)
      success.value = 'User permissions saved.'
    }
    assignmentEnabled.value = false
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save permissions')
  } finally {
    saving.value = false
  }
}

async function onCopyGroup() {
  if (!selectedUserId.value) return
  saving.value = true
  try {
    selectedPermissionIds.value = await copyGroupPermissionsToUser(selectedUserId.value)
    success.value = 'Group permissions copied to user.'
    assignmentEnabled.value = true
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not copy group permissions')
  } finally {
    saving.value = false
  }
}

async function onCreateGroup() {
  try {
    await createPermissionGroup(newGroupName.value)
    showGroupModal.value = false
    newGroupName.value = ''
    groups.value = await fetchPermissionGroups()
    success.value = 'Group created.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not create group')
  }
}

async function onCreatePermission() {
  try {
    await createPermissionDefinition(newPermName.value, newPermDefinition.value)
    showPermModal.value = false
    newPermName.value = ''
    newPermDefinition.value = ''
    const catalog = await fetchPermissionsCatalog()
    categories.value = catalog.categories
    success.value = 'Permission created.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not create permission')
  }
}

watch(mode, async () => {
  assignmentEnabled.value = false
  if (mode.value === 'user') await loadUsers()
  await loadAssignments()
})

watch(selectedGroupId, () => {
  if (suppressGroupWatch) return
  if (mode.value === 'group') void loadAssignments()
})

watch([userSearch, userGroupFilter, userPage], () => {
  if (mode.value === 'user') void loadUsers()
})

onMounted(() => void bootstrap())
</script>

<template>
  <div class="permissions-page">
    <PortalPageChrome title="Permissions" lede="Assign group and user permissions.">
      <template #actions>
        <PortalBtn size="small" variant="outlined" color="primary" class="me-2" @click="showGroupModal = true">
          + Group
        </PortalBtn>
        <PortalBtn size="small" variant="outlined" color="primary" @click="showPermModal = true">
          + Permission
        </PortalBtn>
      </template>
      <template #tabs>
        <v-tabs v-model="mode" color="primary" align-tabs="start" density="compact">
          <v-tab value="group">By group</v-tab>
          <v-tab value="user">By user</v-tab>
        </v-tabs>
      </template>
    </PortalPageChrome>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else>
      <v-row dense>
        <v-col cols="12" lg="4">
          <v-card v-if="mode === 'group'" variant="outlined" class="permissions-panel">
            <v-card-title class="permissions-panel__title">
              <div>
                <div class="text-subtitle-1 font-weight-bold">User groups</div>
                <div class="text-caption text-medium-emphasis">
                  {{ groupCount }} {{ groupCount === 1 ? 'group' : 'groups' }}
                </div>
              </div>
              <v-chip size="small" color="primary" variant="tonal">{{ groupCount }}</v-chip>
            </v-card-title>
            <v-table density="compact" class="permissions-table permissions-table--selectable">
              <thead>
                <tr>
                  <th class="permissions-table__count">#</th>
                  <th>Group</th>
                  <th class="text-end">Users</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(g, index) in groups"
                  :key="g.id"
                  class="permissions-table__row"
                  :class="{ 'is-selected': selectedGroupId === g.id }"
                  @click="selectGroup(g.id)"
                >
                  <td class="permissions-table__count text-medium-emphasis">{{ index + 1 }}</td>
                  <td>
                    <div class="font-weight-medium">{{ g.group_name }}</div>
                  </td>
                  <td class="text-end">
                    <v-chip size="x-small" variant="tonal" color="primary">{{ g.user_count }}</v-chip>
                  </td>
                </tr>
                <tr v-if="!groups.length">
                  <td colspan="3" class="text-medium-emphasis">No groups yet.</td>
                </tr>
              </tbody>
            </v-table>
          </v-card>

          <v-card v-else variant="outlined" class="permissions-panel">
            <v-card-title class="permissions-panel__title">
              <div>
                <div class="text-subtitle-1 font-weight-bold">Users</div>
                <div class="text-caption text-medium-emphasis">
                  {{ userCount }} on this page
                </div>
              </div>
              <v-chip size="small" color="primary" variant="tonal">{{ userCount }}</v-chip>
            </v-card-title>
            <v-card-text class="pb-2">
              <v-text-field
                v-model="userSearch"
                label="Search users"
                density="compact"
                hide-details
                class="mb-2"
                clearable
              />
              <v-select
                v-model="userGroupFilter"
                :items="[{ title: 'All groups', value: null }, ...groups.map((g) => ({ title: g.group_name, value: g.id }))]"
                label="Filter by group"
                density="compact"
                hide-details
              />
            </v-card-text>
            <v-table density="compact" class="permissions-table permissions-table--selectable">
              <thead>
                <tr>
                  <th class="permissions-table__count">#</th>
                  <th>User</th>
                  <th class="text-end">Custom</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(u, index) in users"
                  :key="u.user_id"
                  class="permissions-table__row"
                  :class="{ 'is-selected': selectedUserId === u.user_id }"
                  @click="selectUser(u.user_id)"
                >
                  <td class="permissions-table__count text-medium-emphasis">{{ index + 1 }}</td>
                  <td>
                    <div class="font-weight-medium">{{ u.name }}</div>
                    <div class="text-caption text-medium-emphasis">{{ u.group_name || 'No group' }}</div>
                  </td>
                  <td class="text-end">
                    <v-chip size="x-small" variant="tonal" :color="u.custom_permission_count ? 'warning' : undefined">
                      {{ u.custom_permission_count }}
                    </v-chip>
                  </td>
                </tr>
                <tr v-if="!users.length">
                  <td colspan="3" class="text-medium-emphasis">No users found.</td>
                </tr>
              </tbody>
            </v-table>
            <v-card-actions v-if="userLastPage > 1" class="px-3">
              <v-btn size="small" variant="text" :disabled="userPage <= 1" @click="userPage--">Prev</v-btn>
              <span class="text-caption px-2">{{ userPage }} / {{ userLastPage }}</span>
              <v-btn size="small" variant="text" :disabled="userPage >= userLastPage" @click="userPage++">Next</v-btn>
            </v-card-actions>
          </v-card>
        </v-col>

        <v-col cols="12" lg="8">
          <v-card variant="outlined" class="permissions-panel">
            <v-card-title class="permissions-panel__title permissions-panel__title--wrap">
              <div>
                <div class="text-subtitle-1 font-weight-bold">
                  Permission matrix
                  <span v-if="mode === 'group' && selectedGroup" class="text-medium-emphasis font-weight-regular">
                    — {{ selectedGroup.group_name }}
                  </span>
                  <span v-else-if="mode === 'user' && selectedUserMeta" class="text-medium-emphasis font-weight-regular">
                    — {{ selectedUserMeta.name }}
                  </span>
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ selectedPermissionCount }} of {{ totalPermissionCount }} selected
                  <template v-if="mode === 'user' && selectedUserMeta">
                    · group baseline {{ selectedUserMeta.group_permission_count }}
                  </template>
                </div>
              </div>
              <div class="d-flex flex-wrap ga-2 align-center">
                <v-chip size="small" color="primary" variant="tonal">
                  {{ selectedPermissionCount }} / {{ totalPermissionCount }}
                </v-chip>
                <v-btn
                  size="small"
                  :variant="assignmentEnabled ? 'flat' : 'outlined'"
                  :color="assignmentEnabled ? 'warning' : undefined"
                  @click="assignmentEnabled = !assignmentEnabled"
                >
                  {{ assignmentEnabled ? 'Editing enabled' : 'Enable editing' }}
                </v-btn>
                <PortalBtn
                  v-if="mode === 'user' && selectedUserId"
                  size="small"
                  variant="outlined"
                  color="primary"
                  :loading="saving"
                  @click="onCopyGroup"
                >
                  Copy from group
                </PortalBtn>
                <PortalBtn size="small" :disabled="!assignmentEnabled" :loading="saving" @click="onSave">
                  Save
                </PortalBtn>
              </div>
            </v-card-title>

            <v-card-text class="pt-2">
              <div
                v-for="section in categorySections"
                :key="section.name"
                class="permissions-category"
              >
                <div class="permissions-category__header">
                  <div>
                    <h4 class="text-subtitle-2 text-primary mb-0">{{ section.name }}</h4>
                    <div class="text-caption text-medium-emphasis">
                      {{ section.assigned }} of {{ section.perms.length }} assigned
                    </div>
                  </div>
                  <label class="permissions-check permissions-check--inline">
                    <input
                      type="checkbox"
                      :checked="section.assigned === section.perms.length && section.perms.length > 0"
                      :disabled="!assignmentEnabled || !section.perms.length"
                      @change="toggleCategory(section.perms, ($event.target as HTMLInputElement).checked)"
                    />
                    <span>Select all</span>
                  </label>
                </div>

                <v-table density="compact" class="permissions-table permissions-table--matrix mb-4">
                  <thead>
                    <tr>
                      <th class="permissions-table__check">Assigned</th>
                      <th>Permission</th>
                      <th>Code</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(p, index) in section.perms" :key="p.id">
                      <td class="permissions-table__check">
                        <label class="permissions-check">
                          <input
                            type="checkbox"
                            :checked="selectedSet.has(Number(p.id))"
                            :disabled="!assignmentEnabled"
                            :aria-label="`Toggle ${p.definition}`"
                            @change="togglePermission(Number(p.id), ($event.target as HTMLInputElement).checked)"
                          />
                        </label>
                      </td>
                      <td>
                        <div class="d-flex align-center ga-2">
                          <span class="text-caption text-medium-emphasis permissions-table__count">
                            {{ index + 1 }}
                          </span>
                          <span>{{ p.definition || p.name }}</span>
                        </div>
                      </td>
                      <td>
                        <code class="permissions-code">{{ p.name }}</code>
                      </td>
                    </tr>
                    <tr v-if="!section.perms.length">
                      <td colspan="3" class="text-medium-emphasis">No permissions in this group.</td>
                    </tr>
                  </tbody>
                </v-table>
              </div>

              <div v-if="!categorySections.length" class="text-medium-emphasis">
                No permission definitions found.
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>

    <v-dialog v-model="showGroupModal" max-width="420">
      <v-card>
        <v-card-title>Create group</v-card-title>
        <v-card-text>
          <v-text-field v-model="newGroupName" label="Group name" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showGroupModal = false">Cancel</v-btn>
          <PortalBtn @click="onCreateGroup">Create</PortalBtn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="showPermModal" max-width="480">
      <v-card>
        <v-card-title>Create permission</v-card-title>
        <v-card-text>
          <v-text-field v-model="newPermName" label="Name (letters/underscores)" hint="e.g. staff_view" persistent-hint />
          <v-text-field v-model="newPermDefinition" label="Definition" class="mt-2" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showPermModal = false">Cancel</v-btn>
          <PortalBtn @click="onCreatePermission">Create</PortalBtn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.permissions-panel__title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding-block: 0.85rem;
}

.permissions-panel__title--wrap {
  flex-wrap: wrap;
}

.permissions-table {
  width: 100%;
}

.permissions-table :deep(th) {
  font-weight: 650 !important;
  color: #3a4752 !important;
  background: #f6faf7;
  white-space: nowrap;
}

.permissions-table :deep(td),
.permissions-table :deep(th) {
  border-color: #e8eef3 !important;
}

.permissions-table__count {
  width: 2.5rem;
  white-space: nowrap;
}

.permissions-table__check {
  width: 5.5rem;
}

.permissions-table--selectable :deep(tbody tr.permissions-table__row) {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.permissions-table--selectable :deep(tbody tr.permissions-table__row:hover) {
  background: rgba(13, 122, 58, 0.04);
}

.permissions-table--selectable :deep(tbody tr.permissions-table__row.is-selected) {
  background: rgba(13, 122, 58, 0.1);
}

.permissions-table--selectable :deep(tbody tr.permissions-table__row.is-selected td) {
  box-shadow: inset 3px 0 0 #0d7a3a;
}

.permissions-table--matrix :deep(tbody tr:hover) {
  background: rgba(13, 122, 58, 0.03);
}

.permissions-category {
  border: 1px solid #e8eef3;
  border-radius: 12px;
  padding: 0.75rem 0.85rem 0.25rem;
  margin-bottom: 1rem;
  background: #fff;
}

.permissions-category__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
  flex-wrap: wrap;
}

.permissions-code {
  font-size: 0.78rem;
  background: #f1f5f9;
  color: #334155;
  padding: 0.15rem 0.45rem;
  border-radius: 6px;
}

.permissions-check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  cursor: pointer;
}

.permissions-check--inline {
  gap: 0.4rem;
  font-size: 0.85rem;
  color: #3a4752;
}

.permissions-check input {
  width: 1.05rem;
  height: 1.05rem;
  accent-color: #0d7a3a;
  cursor: pointer;
}

.permissions-check input:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}
</style>
