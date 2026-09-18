<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { notifyApiError, toast } from '@/features/toast'
import {
  createCbpModule,
  fetchCbpModulesSettings,
  updateCbpModule,
  type CbpModuleAdminRow,
  type CbpModuleFormPayload,
} from '@/lib/settingsApi'

type FormState = {
  module_key: string
  system_name: string
  description: string
  base_url: string
  base_url_development: string
  base_url_production: string
  icon_class: string
  permission_code: string
  uses_staff_portal_token: boolean
  is_production: boolean
  is_enabled: boolean
  show_in_apm_menu: boolean
  alternate_base_url: string
  alternate_for_role_id: number | null
  target_resolver: string
  sort_order: number
}

const emptyCreate = (): FormState => ({
  module_key: '',
  system_name: '',
  description: '',
  base_url: '',
  base_url_development: '',
  base_url_production: '',
  icon_class: 'fa-th',
  permission_code: '',
  uses_staff_portal_token: false,
  is_production: true,
  is_enabled: true,
  show_in_apm_menu: false,
  alternate_base_url: '',
  alternate_for_role_id: null,
  target_resolver: 'codeigniter',
  sort_order: 100,
})

const loading = ref(false)
const savingId = ref<number | 'new' | null>(null)
const tableExists = ref(true)
const modules = ref<CbpModuleAdminRow[]>([])
const expanded = ref<number[]>([])
const createOpen = ref(false)
const nextSortOrder = ref(100)
const nextPermissionHint = ref(1)
const autoAssignGroupId = ref(10)
const iconOptions = ref<Record<string, string>>({})
const resolverOptions = ref<Record<string, string>>({})

const drafts = reactive<Record<number, FormState>>({})
const createForm = reactive<FormState>(emptyCreate())

const iconItems = computed(() =>
  Object.entries(iconOptions.value).map(([value, label]) => ({
    title: `${label} (${value})`,
    value,
  })),
)

const resolverItems = computed(() =>
  Object.entries(resolverOptions.value).map(([value, title]) => ({ title, value })),
)

function rowToForm(row: CbpModuleAdminRow): FormState {
  return {
    module_key: row.module_key,
    system_name: row.system_name,
    description: row.description || '',
    base_url: row.base_url || '',
    base_url_development: row.base_url_development || '',
    base_url_production: row.base_url_production || '',
    icon_class: row.icon_class || 'fa-th',
    permission_code: row.permission_code || '',
    uses_staff_portal_token: !!row.uses_staff_portal_token,
    is_production: !!row.is_production,
    is_enabled: !!row.is_enabled,
    show_in_apm_menu: !!row.show_in_apm_menu,
    alternate_base_url: row.alternate_base_url || '',
    alternate_for_role_id: row.alternate_for_role_id,
    target_resolver: row.target_resolver || 'codeigniter',
    sort_order: row.sort_order ?? 0,
  }
}

function toPayload(form: FormState, includeKey = false): CbpModuleFormPayload {
  const payload: CbpModuleFormPayload = {
    system_name: form.system_name.trim(),
    description: form.description,
    base_url: form.base_url,
    base_url_development: form.base_url_development || null,
    base_url_production: form.base_url_production || null,
    icon_class: form.icon_class,
    permission_code: form.permission_code,
    uses_staff_portal_token: form.uses_staff_portal_token,
    is_production: form.is_production,
    is_enabled: form.is_enabled,
    show_in_apm_menu: form.show_in_apm_menu,
    alternate_base_url: form.alternate_base_url || null,
    alternate_for_role_id:
      form.alternate_for_role_id != null && !Number.isNaN(Number(form.alternate_for_role_id))
        ? Number(form.alternate_for_role_id)
        : null,
    target_resolver: form.target_resolver,
    sort_order: form.sort_order,
  }
  if (includeKey) {
    payload.module_key = form.module_key.trim()
  }
  return payload
}

function showPanel(resolver: string, keys: string): boolean {
  return keys.split(/\s+/).includes(resolver)
}

async function load() {
  loading.value = true
  try {
    const res = await fetchCbpModulesSettings()
    tableExists.value = res.meta.table_exists
    modules.value = res.data
    nextSortOrder.value = res.meta.next_sort_order
    nextPermissionHint.value = res.meta.next_permission_id_hint
    autoAssignGroupId.value = res.meta.auto_assign_group_id
    iconOptions.value = res.meta.icon_options || {}
    resolverOptions.value = res.meta.resolver_options || {}
    for (const row of res.data) {
      drafts[row.id] = rowToForm(row)
    }
  } catch (e) {
    notifyApiError(e, 'Could not load CBP modules')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  Object.assign(createForm, emptyCreate(), { sort_order: nextSortOrder.value, icon_class: 'fa-th' })
  createOpen.value = true
}

async function onSave(id: number) {
  const form = drafts[id]
  if (!form) return
  savingId.value = id
  try {
    const message = await updateCbpModule(id, toPayload(form))
    toast.success(message || 'Module updated.', 'Saved')
    await load()
  } catch (e) {
    notifyApiError(e, 'Could not save module')
  } finally {
    savingId.value = null
  }
}

async function onCreate() {
  savingId.value = 'new'
  try {
    const message = await createCbpModule(toPayload(createForm, true))
    toast.success(message || 'Module created.', 'Created')
    createOpen.value = false
    await load()
  } catch (e) {
    notifyApiError(e, 'Could not create module')
  } finally {
    savingId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="cbp-modules-settings">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <CbpPageHeading
        title="CBP modules"
        subtitle="Home tiles and system links. Same fields as CI3 settings/cbp_modules."
      />
      <div class="d-flex flex-wrap ga-2">
        <RouterLink to="/settings" style="text-decoration: none">
          <v-btn variant="outlined" size="small">← Settings</v-btn>
        </RouterLink>
        <v-btn
          v-if="tableExists"
          color="primary"
          variant="flat"
          prepend-icon="mdi-plus"
          @click="openCreate"
        >
          Add module
        </v-btn>
      </div>
    </div>

    <p class="text-body-2 text-medium-emphasis mb-3">
      Home tiles and optional APM menu links. Use <strong>External system</strong> for APIs on other hosts.
      Enable <strong>Append Staff portal session token</strong> only if that system validates the same token as APM/Finance.
      <strong>Production visibility</strong>: when unchecked, only role ID 10 sees the tile (still requires the permission).
    </p>

    <v-alert v-if="!loading && !tableExists" type="warning" variant="tonal" class="mb-3">
      The <code>cbp_modules</code> table is not installed. Run
      <code>application/sql/create_cbp_modules_table.sql</code> on the Staff database.
    </v-alert>

    <div v-else-if="loading" class="text-medium-emphasis py-4">Loading…</div>

    <template v-else>
      <v-alert v-if="!modules.length" type="info" variant="tonal" class="mb-3">
        No modules yet. Use <strong>Add module</strong> or open CBP Home once to seed defaults.
      </v-alert>

      <v-expansion-panels v-model="expanded" multiple variant="accordion" class="mb-4">
        <v-expansion-panel v-for="row in modules" :key="row.id" :value="row.id">
          <v-expansion-panel-title>
            <div class="d-flex align-center ga-2 flex-wrap">
              <i
                class="fa-solid"
                :class="(row.icon_class || 'fa-th').startsWith('fa-') ? row.icon_class : `fa-${row.icon_class}`"
                aria-hidden="true"
              />
              <span class="font-weight-medium">{{ row.system_name }}</span>
              <v-chip size="x-small" variant="tonal">{{ row.module_key }}</v-chip>
              <v-chip
                size="x-small"
                :color="row.is_enabled ? 'success' : 'default'"
                variant="tonal"
              >
                {{ row.is_enabled ? 'Enabled' : 'Disabled' }}
              </v-chip>
            </div>
          </v-expansion-panel-title>
          <v-expansion-panel-text v-if="drafts[row.id]">
            <v-row dense>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="drafts[row.id].system_name"
                  label="System name *"
                  density="compact"
                  hide-details="auto"
                  class="bg-white"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="drafts[row.id].module_key"
                  label="Module key"
                  density="compact"
                  hide-details="auto"
                  disabled
                  hint="Fixed identifier (not editable)."
                  persistent-hint
                  class="bg-white"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="drafts[row.id].permission_code"
                  label="Permission code *"
                  density="compact"
                  hide-details="auto"
                  class="bg-white"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="drafts[row.id].description"
                  label="Description"
                  rows="2"
                  density="compact"
                  hide-details="auto"
                  class="bg-white"
                />
              </v-col>

              <v-col cols="12">
                <v-select
                  v-model="drafts[row.id].target_resolver"
                  :items="resolverItems"
                  label="Link target *"
                  density="compact"
                  hide-details="auto"
                  class="bg-white"
                />
              </v-col>

              <v-col
                v-if="showPanel(drafts[row.id].target_resolver, 'codeigniter staff_app_token external_microservice')"
                cols="12"
              >
                <v-card variant="outlined" class="pa-3">
                  <div class="text-caption text-medium-emphasis mb-2">Path or URL (Staff host or external fallback)</div>
                  <v-text-field
                    v-model="drafts[row.id].base_url"
                    label="base_url / main segment"
                    placeholder="dashboard, apm, or https://service.example.org"
                    density="compact"
                    hide-details="auto"
                    class="bg-white"
                  />
                </v-card>
              </v-col>

              <v-col v-if="showPanel(drafts[row.id].target_resolver, 'codeigniter')" cols="12">
                <v-card variant="outlined" class="pa-3">
                  <div class="text-caption text-medium-emphasis mb-2">Optional alternate path by role</div>
                  <v-row dense>
                    <v-col cols="12" md="6">
                      <v-text-field
                        v-model="drafts[row.id].alternate_base_url"
                        label="Alternate path"
                        placeholder="e.g. auth/profile"
                        density="compact"
                        hide-details="auto"
                        class="bg-white"
                      />
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        v-model.number="drafts[row.id].alternate_for_role_id"
                        label="Only for role ID"
                        type="number"
                        density="compact"
                        hide-details="auto"
                        clearable
                        class="bg-white"
                      />
                    </v-col>
                  </v-row>
                </v-card>
              </v-col>

              <v-col
                v-if="showPanel(drafts[row.id].target_resolver, 'finance_host external_microservice')"
                cols="12"
              >
                <v-card variant="outlined" class="pa-3">
                  <div class="text-caption text-medium-emphasis mb-2">Environment-specific URLs</div>
                  <v-row dense>
                    <v-col cols="12" md="6">
                      <v-text-field
                        v-model="drafts[row.id].base_url_development"
                        label="Development URL"
                        density="compact"
                        hide-details="auto"
                        class="bg-white"
                      />
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        v-model="drafts[row.id].base_url_production"
                        label="Production path or URL"
                        density="compact"
                        hide-details="auto"
                        class="bg-white"
                      />
                    </v-col>
                  </v-row>
                </v-card>
              </v-col>

              <v-col cols="12">
                <v-checkbox
                  v-model="drafts[row.id].uses_staff_portal_token"
                  label="Append Staff portal session token (SSO)"
                  density="compact"
                  hide-details
                />
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="drafts[row.id].icon_class"
                  :items="iconItems"
                  label="Icon (Font Awesome)"
                  density="compact"
                  hide-details="auto"
                  class="bg-white"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="drafts[row.id].sort_order"
                  label="Sort order"
                  type="number"
                  density="compact"
                  hide-details="auto"
                  class="bg-white"
                />
              </v-col>

              <v-col cols="12" class="d-flex flex-wrap ga-4">
                <v-checkbox
                  v-model="drafts[row.id].is_production"
                  label="Visible to all permitted users (unchecked = role 10 only)"
                  density="compact"
                  hide-details
                />
                <v-checkbox v-model="drafts[row.id].is_enabled" label="Enabled" density="compact" hide-details />
                <v-checkbox
                  v-model="drafts[row.id].show_in_apm_menu"
                  label="Show in APM top menu"
                  density="compact"
                  hide-details
                />
              </v-col>

              <v-col cols="12">
                <v-btn
                  color="success"
                  variant="flat"
                  prepend-icon="mdi-content-save"
                  :loading="savingId === row.id"
                  @click="onSave(row.id)"
                >
                  Save module
                </v-btn>
              </v-col>
            </v-row>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </template>

    <v-dialog v-model="createOpen" max-width="820" scrollable>
      <v-card>
        <v-card-title>Add CBP module</v-card-title>
        <v-card-subtitle>
          Unique module key (e.g. <code>learning_hub</code>) — cannot be changed later.
          A new permission will be created (next ID typically <strong>{{ nextPermissionHint }}</strong>)
          and assigned to admin group <strong>{{ autoAssignGroupId }}</strong>.
        </v-card-subtitle>
        <v-card-text>
          <v-row dense>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="createForm.module_key"
                label="Module key *"
                placeholder="e.g. learning_hub"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="createForm.system_name"
                label="System name *"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="createForm.description"
                label="Description"
                rows="2"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12">
              <v-select
                v-model="createForm.target_resolver"
                :items="resolverItems"
                label="Link target *"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col
              v-if="showPanel(createForm.target_resolver, 'codeigniter staff_app_token external_microservice')"
              cols="12"
            >
              <v-text-field
                v-model="createForm.base_url"
                label="base_url / main segment"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col v-if="showPanel(createForm.target_resolver, 'codeigniter')" cols="12" md="6">
              <v-text-field
                v-model="createForm.alternate_base_url"
                label="Alternate path"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col v-if="showPanel(createForm.target_resolver, 'codeigniter')" cols="12" md="6">
              <v-text-field
                v-model.number="createForm.alternate_for_role_id"
                label="Only for role ID"
                type="number"
                density="compact"
                hide-details="auto"
                clearable
                class="bg-white"
              />
            </v-col>
            <v-col
              v-if="showPanel(createForm.target_resolver, 'finance_host external_microservice')"
              cols="12"
              md="6"
            >
              <v-text-field
                v-model="createForm.base_url_development"
                label="Development URL"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col
              v-if="showPanel(createForm.target_resolver, 'finance_host external_microservice')"
              cols="12"
              md="6"
            >
              <v-text-field
                v-model="createForm.base_url_production"
                label="Production path or URL"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12">
              <v-checkbox
                v-model="createForm.uses_staff_portal_token"
                label="Append Staff portal session token (SSO)"
                density="compact"
                hide-details
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                v-model="createForm.icon_class"
                :items="iconItems"
                label="Icon (Font Awesome)"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="createForm.sort_order"
                label="Sort order"
                type="number"
                density="compact"
                hide-details="auto"
                class="bg-white"
              />
            </v-col>
            <v-col cols="12" class="d-flex flex-wrap ga-4">
              <v-checkbox
                v-model="createForm.is_production"
                label="Visible to all permitted users"
                density="compact"
                hide-details
              />
              <v-checkbox v-model="createForm.is_enabled" label="Enabled" density="compact" hide-details />
              <v-checkbox
                v-model="createForm.show_in_apm_menu"
                label="Show in APM top menu"
                density="compact"
                hide-details
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="createOpen = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="savingId === 'new'" @click="onCreate">
            Create module
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
