<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import type { DataTableHeader } from 'vuetify'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { fieldError } from '../../lib/helpdeskForm'
import { notifyError, notifySuccess } from '../../lib/notify'

interface BrandRow {
  id: number
  name: string
  slug: string
  sort_order: number
  is_active: boolean
  assets_count?: number
}

interface CategoryRow {
  id: number
  name: string
  slug: string
  icon?: string
  default_useful_life_years: number
  sort_order: number
  is_active: boolean
  assets_count?: number
}

const tab = ref<'brands' | 'categories'>('brands')
const brands = ref<BrandRow[]>([])
const categories = ref<CategoryRow[]>([])
const busyId = ref<number | null>(null)
const catBusyId = ref<number | null>(null)

const brandModalOpen = ref(false)
const brandEditingId = ref<number | null>(null)
const catModalOpen = ref(false)
const catEditingId = ref<number | null>(null)

const brandDraft = reactive({
  name: '',
  slug: '',
  sort_order: 0,
  is_active: true,
})

const catDraft = reactive({
  name: '',
  slug: '',
  icon: 'bx-package',
  default_useful_life_years: 3,
  sort_order: 0,
  is_active: true,
})

const brandHeaders: DataTableHeader[] = [
  { title: 'Name', key: 'name', sortable: false, minWidth: '180px' },
  { title: 'Assets', key: 'assets_count', sortable: false, width: '90px' },
  { title: 'Order', key: 'sort_order', sortable: false, width: '90px' },
  { title: 'Active', key: 'is_active', sortable: false, width: '90px', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, width: '180px', align: 'end' },
]

const catHeaders: DataTableHeader[] = [
  { title: 'Name', key: 'name', sortable: false, minWidth: '160px' },
  { title: 'Useful life', key: 'default_useful_life_years', sortable: false, width: '110px' },
  { title: 'Assets', key: 'assets_count', sortable: false, width: '90px' },
  { title: 'Order', key: 'sort_order', sortable: false, width: '90px' },
  { title: 'Active', key: 'is_active', sortable: false, width: '90px', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, width: '180px', align: 'end' },
]

const brandModalTitle = computed(() => (brandEditingId.value ? 'Edit brand' : 'Add brand'))
const catModalTitle = computed(() => (catEditingId.value ? 'Edit category' : 'Add category'))
const brandCountLabel = computed(() => `${brands.value.length} brand${brands.value.length === 1 ? '' : 's'}`)
const catCountLabel = computed(() => `${categories.value.length} categor${categories.value.length === 1 ? 'y' : 'ies'}`)

function validateBrand(state: typeof brandDraft): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Name is required')
  if (nameErr) errors.push(nameErr)
  return errors
}

function validateCategory(state: typeof catDraft): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Name is required')
  if (nameErr) errors.push(nameErr)
  return errors
}

function resetBrandDraft() {
  brandDraft.name = ''
  brandDraft.slug = ''
  brandDraft.sort_order = 0
  brandDraft.is_active = true
}

function openCreateBrandModal() {
  brandEditingId.value = null
  resetBrandDraft()
  brandModalOpen.value = true
}

function openEditBrandModal(row: BrandRow) {
  brandEditingId.value = row.id
  brandDraft.name = row.name
  brandDraft.slug = row.slug
  brandDraft.sort_order = row.sort_order
  brandDraft.is_active = row.is_active
  brandModalOpen.value = true
}

function closeBrandModal() {
  brandModalOpen.value = false
  brandEditingId.value = null
  resetBrandDraft()
}

function resetCatDraft() {
  catDraft.name = ''
  catDraft.slug = ''
  catDraft.icon = 'bx-package'
  catDraft.default_useful_life_years = 3
  catDraft.sort_order = 0
  catDraft.is_active = true
}

function openCreateCatModal() {
  catEditingId.value = null
  resetCatDraft()
  catModalOpen.value = true
}

function openEditCatModal(row: CategoryRow) {
  catEditingId.value = row.id
  catDraft.name = row.name
  catDraft.slug = row.slug
  catDraft.icon = row.icon || 'bx-package'
  catDraft.default_useful_life_years = row.default_useful_life_years
  catDraft.sort_order = row.sort_order
  catDraft.is_active = row.is_active
  catModalOpen.value = true
}

function closeCatModal() {
  catModalOpen.value = false
  catEditingId.value = null
  resetCatDraft()
}

async function load() {
  try {
    const [bRes, cRes] = await Promise.all([
      api.get<{ data: BrandRow[] }>('/api/v1/tools/it-assets/brands'),
      api.get<{ data: CategoryRow[] }>('/api/v1/tools/it-assets/categories'),
    ])
    brands.value = Array.isArray(bRes.data.data) ? bRes.data.data : []
    categories.value = Array.isArray(cRes.data.data) ? cRes.data.data : []
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load IT asset settings.'))
  }
}

async function saveBrandModal(_event?: FormSubmitEvent<typeof brandDraft>) {
  const payload = {
    name: brandDraft.name.trim(),
    slug: brandDraft.slug.trim() || undefined,
    sort_order: brandDraft.sort_order,
    is_active: brandDraft.is_active,
  }

  busyId.value = brandEditingId.value ?? -1
  try {
    if (brandEditingId.value) {
      await api.put(`/api/v1/tools/it-assets/brands/${brandEditingId.value}`, {
        ...payload,
        slug: brandDraft.slug.trim() || payload.name,
      })
      notifySuccess(`Updated “${payload.name}”.`)
    } else {
      await api.post('/api/v1/tools/it-assets/brands', payload)
      notifySuccess('Brand created.')
    }
    closeBrandModal()
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  } finally {
    busyId.value = null
  }
}

async function removeBrand(row: BrandRow) {
  if (!window.confirm(`Delete brand “${row.name}”?`)) return
  busyId.value = row.id
  try {
    await api.delete(`/api/v1/tools/it-assets/brands/${row.id}`)
    notifySuccess('Brand deleted.')
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Delete failed'))
  } finally {
    busyId.value = null
  }
}

async function saveCatModal(_event?: FormSubmitEvent<typeof catDraft>) {
  const payload = {
    name: catDraft.name.trim(),
    slug: catDraft.slug.trim() || undefined,
    icon: catDraft.icon.trim() || 'bx-package',
    default_useful_life_years: catDraft.default_useful_life_years,
    sort_order: catDraft.sort_order,
    is_active: catDraft.is_active,
  }

  catBusyId.value = catEditingId.value ?? -1
  try {
    if (catEditingId.value) {
      await api.put(`/api/v1/tools/it-assets/categories/${catEditingId.value}`, {
        name: payload.name,
        slug: catDraft.slug.trim() || payload.name,
        default_useful_life_years: payload.default_useful_life_years,
        sort_order: payload.sort_order,
        is_active: payload.is_active,
      })
      notifySuccess(`Updated “${payload.name}”.`)
    } else {
      await api.post('/api/v1/tools/it-assets/categories', payload)
      notifySuccess('Category created.')
    }
    closeCatModal()
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  } finally {
    catBusyId.value = null
  }
}

async function removeCategory(row: CategoryRow) {
  if (!window.confirm(`Delete category “${row.name}”?`)) return
  catBusyId.value = row.id
  try {
    await api.delete(`/api/v1/tools/it-assets/categories/${row.id}`)
    notifySuccess('Category deleted.')
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Delete failed'))
  } finally {
    catBusyId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="panel" aria-labelledby="it-assets-settings-heading">
    <h2 id="it-assets-settings-heading">IT Assets · brands &amp; categories</h2>
    <p class="hint">
      Brands appear in the IT Assets form dropdown. Categories (including SIM cards) group inventory and set default useful life for depreciation.
      {{ brandCountLabel }} · {{ catCountLabel }}.
    </p>

    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="brands">Brands</v-tab>
      <v-tab value="categories">Categories</v-tab>
    </v-tabs>

    <div v-if="tab === 'brands'">
      <div class="toolbar">
        <p class="toolbar-hint muted">
          Edit each brand in a modal. Keep the table lean for scanning.
        </p>
        <UButton color="primary" @click="openCreateBrandModal">Add brand</UButton>
      </div>

      <v-card v-if="brands.length" class="table-card" elevation="10">
        <v-data-table
          :headers="brandHeaders"
          :items="brands"
          item-value="id"
          density="comfortable"
          class="hd-data-table"
          hide-default-footer
        >
          <template #item.name="{ item }">
            <strong>{{ item.name }}</strong>
            <div class="slug">{{ item.slug }}</div>
          </template>
          <template #item.assets_count="{ item }">
            {{ item.assets_count ?? 0 }}
          </template>
          <template #item.is_active="{ item }">
            <UCheckbox :model-value="item.is_active" disabled hide-details />
          </template>
          <template #item.actions="{ item }">
            <div class="actions">
              <UButton type="button" color="neutral" variant="outlined" size="small" @click="openEditBrandModal(item)">Edit</UButton>
              <UButton type="button" color="error" variant="soft" size="small" :disabled="busyId === item.id" @click="removeBrand(item)">Delete</UButton>
            </div>
          </template>
        </v-data-table>
      </v-card>
      <p v-else class="muted">No brands yet.</p>

      <UModal
        v-model:open="brandModalOpen"
        :title="brandModalTitle"
        :ui="{ content: 'max-w-xl' }"
      >
        <template #body>
          <UForm :state="brandDraft" :validate="validateBrand" class="hd-form hd-form--grid" @submit="saveBrandModal">
            <UFormField label="Name" name="name" required class="span-2">
              <UInput v-model="brandDraft.name" maxlength="191" icon="mdi-tag-outline" />
            </UFormField>
            <UFormField label="Slug (optional)" name="slug" stacked-label description="Leave blank to auto-generate">
              <UInput v-model="brandDraft.slug" maxlength="191" placeholder="auto from name" />
            </UFormField>
            <UFormField label="Sort order" name="sort_order">
              <UInput v-model.number="brandDraft.sort_order" type="number" min="0" icon="mdi-sort-numeric-ascending" />
            </UFormField>
            <UFormField name="is_active">
              <UCheckbox v-model="brandDraft.is_active" label="Active" />
            </UFormField>
          </UForm>
        </template>
        <template #footer>
          <UButton color="neutral" variant="outline" :disabled="busyId !== null" @click="closeBrandModal">Cancel</UButton>
          <UButton
            color="primary"
            :loading="busyId !== null"
            :label="brandEditingId ? 'Save changes' : 'Create brand'"
            @click="saveBrandModal()"
          />
        </template>
      </UModal>
    </div>

    <div v-else>
      <div class="toolbar">
        <p class="toolbar-hint muted">
          Edit each category in a modal (useful life, order). Keep the table lean for scanning.
        </p>
        <UButton color="primary" @click="openCreateCatModal">Add category</UButton>
      </div>

      <v-card v-if="categories.length" class="table-card" elevation="10">
        <v-data-table
          :headers="catHeaders"
          :items="categories"
          item-value="id"
          density="comfortable"
          class="hd-data-table"
          hide-default-footer
        >
          <template #item.name="{ item }">
            <strong>{{ item.name }}</strong>
            <div class="slug">{{ item.slug }}</div>
          </template>
          <template #item.default_useful_life_years="{ item }">
            {{ item.default_useful_life_years }} yr
          </template>
          <template #item.assets_count="{ item }">
            {{ item.assets_count ?? 0 }}
          </template>
          <template #item.is_active="{ item }">
            <UCheckbox :model-value="item.is_active" disabled hide-details />
          </template>
          <template #item.actions="{ item }">
            <div class="actions">
              <UButton type="button" color="neutral" variant="outlined" size="small" @click="openEditCatModal(item)">Edit</UButton>
              <UButton type="button" color="error" variant="soft" size="small" :disabled="catBusyId === item.id" @click="removeCategory(item)">Delete</UButton>
            </div>
          </template>
        </v-data-table>
      </v-card>
      <p v-else class="muted">No categories yet.</p>

      <UModal
        v-model:open="catModalOpen"
        :title="catModalTitle"
        :ui="{ content: 'max-w-xl' }"
      >
        <template #body>
          <UForm :state="catDraft" :validate="validateCategory" class="hd-form hd-form--grid" @submit="saveCatModal">
            <UFormField label="Name" name="name" required class="span-2">
              <UInput v-model="catDraft.name" maxlength="191" placeholder="e.g. SIM cards" icon="mdi-tag-outline" />
            </UFormField>
            <UFormField label="Slug (optional)" name="slug" stacked-label description="Leave blank to auto-generate">
              <UInput v-model="catDraft.slug" maxlength="191" placeholder="auto from name" />
            </UFormField>
            <UFormField label="Default useful life (years)" name="default_useful_life_years">
              <UInput v-model.number="catDraft.default_useful_life_years" type="number" min="1" max="30" />
            </UFormField>
            <UFormField label="Sort order" name="sort_order">
              <UInput v-model.number="catDraft.sort_order" type="number" min="0" icon="mdi-sort-numeric-ascending" />
            </UFormField>
            <UFormField name="is_active">
              <UCheckbox v-model="catDraft.is_active" label="Active" />
            </UFormField>
          </UForm>
        </template>
        <template #footer>
          <UButton color="neutral" variant="outline" :disabled="catBusyId !== null" @click="closeCatModal">Cancel</UButton>
          <UButton
            color="primary"
            :loading="catBusyId !== null"
            :label="catEditingId ? 'Save changes' : 'Create category'"
            @click="saveCatModal()"
          />
        </template>
      </UModal>
    </div>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
  font-weight: 700;
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.55;
  max-width: 52rem;
}
.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}
.toolbar-hint {
  margin: 0;
  flex: 1;
  min-width: 14rem;
  font-size: 0.88rem;
  line-height: 1.45;
}
.table-card { overflow: hidden; }
.slug {
  font-size: 0.75rem;
  color: #768b9e;
  font-weight: 400;
}
.actions { display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end; }
.muted { color: #64748b; }
</style>
