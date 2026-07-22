<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import CbpPageHeading from '../../components/common/CbpPageHeading.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'
import type { SelectNumberItem } from '../../lib/helpdeskForm'

interface Category {
  id: number
  name: string
  slug: string
  icon?: string
  default_useful_life_years: number
  assets_count?: number
}

interface Brand {
  id: number
  name: string
  slug: string
  is_active?: boolean
}

interface Asset {
  id: number
  asset_tag: string
  category_id: number
  brand_id?: number | null
  name: string
  brand?: string
  model?: string
  serial_number?: string
  purchase_date?: string
  purchase_cost: string | number
  salvage_value?: string | number
  useful_life_years?: number
  assigned_staff_id?: number | null
  assigned_name?: string
  status: string
  location?: string
  notes?: string
  category?: Category
  brand_relation?: Brand | null
  valuation?: {
    age_years: number
    age_months: number
    current_value: number
    depreciation_per_year: number
  }
}

interface Summary {
  asset_count: number
  total_purchase_cost: number
  total_current_value: number
  total_depreciation: number
  by_category: { category_name: string; count: number; current_value_total: number }[]
}

type TabId = 'inventory' | 'form'

const summary = ref<Summary | null>(null)
const categories = ref<Category[]>([])
const brands = ref<Brand[]>([])
const assets = ref<Asset[]>([])
const loading = ref(false)
const busy = ref(false)
const filterCategory = ref(0)
const filterBrand = ref(0)
const filterStatus = ref('')
const search = ref('')
const activeTab = ref<TabId>('inventory')
const editing = ref<Asset | null>(null)

const form = reactive({
  asset_tag: '',
  category_id: 0,
  brand_id: 0 as number,
  name: '',
  model: '',
  serial_number: '',
  purchase_date: '',
  purchase_cost: 0,
  salvage_value: 0,
  useful_life_years: 0,
  assigned_staff_id: null as number | null,
  assigned_name: '',
  status: 'deployed',
  location: '',
  notes: '',
})

const statusItems = [
  { label: 'In stock', value: 'in_stock' },
  { label: 'Deployed', value: 'deployed' },
  { label: 'Repair', value: 'repair' },
  { label: 'Retired', value: 'retired' },
]

const filterStatusItems = [
  { label: 'All statuses', value: '' },
  ...statusItems,
]

const fmtMoney = (n: number | string) =>
  '$' + (parseFloat(String(n)) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const categoryItems = computed((): SelectNumberItem[] =>
  categories.value.map((c) => ({ label: c.name, value: c.id })),
)

const brandItems = computed((): SelectNumberItem[] =>
  brands.value.filter((b) => b.is_active !== false).map((b) => ({ label: b.name, value: b.id })),
)

const filterCategoryItems = computed((): SelectNumberItem[] => [
  { label: 'All categories', value: 0 },
  ...categoryItems.value,
])

const filterBrandItems = computed((): SelectNumberItem[] => [
  { label: 'All brands', value: 0 },
  ...brandItems.value,
])

const brandFormItems = computed(() => [
  { label: 'No brand', value: 0 },
  ...brandItems.value,
])

const formTitle = computed(() => (editing.value ? 'Edit asset' : 'Add asset'))

const assigneeInitialLabel = computed(() => {
  if (!editing.value?.assigned_staff_id) return null
  return editing.value.assigned_name || `Staff #${editing.value.assigned_staff_id}`
})

function statusLabel(status: string) {
  return statusItems.find((s) => s.value === status)?.label ?? status
}

function statusBadgeClass(status: string) {
  if (status === 'deployed') return 'badge badge-ok'
  if (status === 'repair') return 'badge badge-warn'
  if (status === 'retired') return 'badge badge-muted'
  return 'badge'
}

function brandLabel(row: Asset) {
  return row.brand_relation?.name ?? row.brand ?? '—'
}

async function loadMeta() {
  const [catRes, brandRes] = await Promise.all([
    api.get<{ data: Category[] }>('/api/v1/tools/it-assets/categories'),
    api.get<{ data: Brand[] }>('/api/v1/tools/it-assets/brands'),
  ])
  categories.value = catRes.data.data ?? []
  brands.value = brandRes.data.data ?? []
}

async function load() {
  loading.value = true
  try {
    const [sumRes, assetRes] = await Promise.all([
      api.get<{ data: Summary }>('/api/v1/tools/it-assets/summary'),
      api.get<{ data: Asset[] }>('/api/v1/tools/it-assets', {
        params: {
          category_id: filterCategory.value || undefined,
          brand_id: filterBrand.value || undefined,
          status: filterStatus.value || undefined,
          q: search.value || undefined,
          per_page: 100,
        },
      }),
    ])
    summary.value = sumRes.data.data
    const paginated = assetRes.data as { data?: Asset[] }
    assets.value = Array.isArray(paginated.data) ? paginated.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load IT assets.'))
  } finally {
    loading.value = false
  }
}

function resetForm() {
  editing.value = null
  Object.assign(form, {
    asset_tag: '',
    category_id: categories.value[0]?.id ?? 0,
    brand_id: 0,
    name: '',
    model: '',
    serial_number: '',
    purchase_date: '',
    purchase_cost: 0,
    salvage_value: 0,
    useful_life_years: 0,
    assigned_staff_id: null,
    assigned_name: '',
    status: 'deployed',
    location: '',
    notes: '',
  })
}

function openCreate() {
  resetForm()
  activeTab.value = 'form'
}

function openEdit(row: Asset) {
  editing.value = row
  Object.assign(form, {
    asset_tag: row.asset_tag,
    category_id: row.category_id,
    brand_id: row.brand_id ?? 0,
    name: row.name,
    model: row.model ?? '',
    serial_number: row.serial_number ?? '',
    purchase_date: row.purchase_date?.slice(0, 10) ?? '',
    purchase_cost: Number(row.purchase_cost) || 0,
    salvage_value: Number(row.salvage_value) || 0,
    useful_life_years: row.useful_life_years ?? 0,
    assigned_staff_id: row.assigned_staff_id ?? null,
    assigned_name: row.assigned_name ?? '',
    status: row.status,
    location: row.location ?? '',
    notes: row.notes ?? '',
  })
  activeTab.value = 'form'
}

function openFormTab() {
  if (activeTab.value === 'form') return
  if (editing.value) {
    activeTab.value = 'form'
    return
  }
  openCreate()
}

function cancelForm() {
  resetForm()
  activeTab.value = 'inventory'
}

async function save() {
  busy.value = true
  try {
    const payload = {
      ...form,
      brand_id: form.brand_id || null,
      assigned_staff_id: form.assigned_staff_id || null,
      useful_life_years: form.useful_life_years || null,
    }
    if (editing.value) {
      await api.put(`/api/v1/tools/it-assets/${editing.value.id}`, payload)
      notifySuccess('Asset updated.')
    } else {
      await api.post('/api/v1/tools/it-assets', payload)
      notifySuccess('Asset created.')
    }
    resetForm()
    activeTab.value = 'inventory'
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  } finally {
    busy.value = false
  }
}

async function remove(row: Asset) {
  if (!window.confirm(`Delete asset ${row.asset_tag}?`)) return
  try {
    await api.delete(`/api/v1/tools/it-assets/${row.id}`)
    notifySuccess('Asset deleted.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Delete failed.'))
  }
}

watch(activeTab, (tab) => {
  if (tab === 'form' && !editing.value && !form.category_id && categories.value[0]) {
    form.category_id = categories.value[0].id
  }
})

onMounted(async () => {
  try {
    await loadMeta()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load brands/categories.'))
  }
  await load()
})
</script>

<template>
  <div>
    <CbpPageHeading title="IT Assets" back-to="/" back-label="← Overview">
      <template #lede>
        Track hardware inventory and straight-line depreciation. Assign staff from the directory; manage brands and categories under Settings → IT Assets.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <div class="tabs" role="tablist" aria-label="IT assets sections">
        <button
          type="button"
          role="tab"
          class="tab"
          :class="{ active: activeTab === 'inventory' }"
          :aria-selected="activeTab === 'inventory'"
          @click="activeTab = 'inventory'"
        >
          Inventory
        </button>
        <button
          type="button"
          role="tab"
          class="tab"
          :class="{ active: activeTab === 'form' }"
          :aria-selected="activeTab === 'form'"
          @click="openFormTab"
        >
          {{ editing ? 'Edit asset' : 'Add asset' }}
        </button>
      </div>

      <div v-show="activeTab === 'inventory'" class="tab-panel" role="tabpanel">
        <div v-if="summary" class="kpi-grid">
          <div class="kpi-card">
            <div class="kpi-label">Assets</div>
            <div class="kpi-value">{{ summary.asset_count }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Purchase value</div>
            <div class="kpi-value">{{ fmtMoney(summary.total_purchase_cost) }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Current value</div>
            <div class="kpi-value text-success">{{ fmtMoney(summary.total_current_value) }}</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-label">Depreciation</div>
            <div class="kpi-value text-muted">{{ fmtMoney(summary.total_depreciation) }}</div>
          </div>
        </div>

        <div v-if="summary?.by_category?.length" class="cat-strip cbp-card">
          <span class="cat-strip-label">By category</span>
          <div class="cat-chips">
            <span v-for="c in summary.by_category" :key="c.category_name" class="cat-chip">
              {{ c.category_name }}
              <strong>{{ c.count }}</strong>
              <em>{{ fmtMoney(c.current_value_total) }}</em>
            </span>
          </div>
        </div>

        <div class="filters">
          <UFormField label="Category" class="filter-sm">
            <USelect v-model="filterCategory" :items="filterCategoryItems" class="w-full" @update:model-value="load" />
          </UFormField>
          <UFormField label="Brand" class="filter-sm">
            <USelect v-model="filterBrand" :items="filterBrandItems" class="w-full" @update:model-value="load" />
          </UFormField>
          <UFormField label="Status" class="filter-sm">
            <USelect v-model="filterStatus" :items="filterStatusItems" class="w-full" @update:model-value="load" />
          </UFormField>
          <UFormField label="Search" class="filter-grow">
            <UInput
              v-model="search"
              type="search"
              icon="i-lucide-search"
              placeholder="Tag, serial, name, brand, assignee…"
              class="w-full"
              @keyup.enter="load"
            />
          </UFormField>
          <UButton color="neutral" variant="outline" class="filter-btn" @click="load">Search</UButton>
          <UButton color="primary" class="filter-btn" @click="openCreate">+ Add asset</UButton>
        </div>

        <div class="table-wrap cbp-card">
          <p v-if="loading" class="muted empty">Loading inventory…</p>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th>Tag</th>
                <th>Category</th>
                <th>Brand</th>
                <th>Name</th>
                <th>Serial</th>
                <th>Age</th>
                <th class="num">Purchase</th>
                <th class="num">Current</th>
                <th>Assignee</th>
                <th>Status</th>
                <th class="actions-col"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in assets" :key="row.id">
                <td><strong>{{ row.asset_tag }}</strong></td>
                <td>{{ row.category?.name ?? '—' }}</td>
                <td>{{ brandLabel(row) }}</td>
                <td class="wrap">{{ row.name }}</td>
                <td class="mono">{{ row.serial_number || '—' }}</td>
                <td>{{ row.valuation ? `${row.valuation.age_years}y` : '—' }}</td>
                <td class="num">{{ fmtMoney(row.purchase_cost) }}</td>
                <td class="num text-success">{{ fmtMoney(row.valuation?.current_value ?? 0) }}</td>
                <td>{{ row.assigned_name || '—' }}</td>
                <td><span :class="statusBadgeClass(row.status)">{{ statusLabel(row.status) }}</span></td>
                <td class="actions">
                  <UButton size="xs" variant="link" @click="openEdit(row)">Edit</UButton>
                  <UButton size="xs" color="error" variant="link" @click="remove(row)">Delete</UButton>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-if="!loading && !assets.length" class="muted empty">No assets found. Add one from the form tab.</p>
        </div>
      </div>

      <div v-show="activeTab === 'form'" class="tab-panel" role="tabpanel">
        <UCard class="form-panel">
          <template #header>
            <div class="form-header">
              <h3>{{ formTitle }}</h3>
              <p class="form-intro">Useful life defaults from the category when left at zero. Assignee comes from the Staff directory.</p>
            </div>
          </template>
          <div class="hd-form hd-form--grid">
            <UFormField label="Asset tag" required><UInput v-model="form.asset_tag" class="w-full" /></UFormField>
            <UFormField label="Category" required>
              <USelect v-model="form.category_id" :items="categoryItems" class="w-full" />
            </UFormField>
            <UFormField label="Brand">
              <USelect v-model="form.brand_id" :items="brandFormItems" class="w-full" />
            </UFormField>
            <UFormField label="Name" required class="span-3"><UInput v-model="form.name" class="w-full" /></UFormField>
            <UFormField label="Model"><UInput v-model="form.model" class="w-full" /></UFormField>
            <UFormField label="Serial number"><UInput v-model="form.serial_number" class="w-full" /></UFormField>
            <UFormField label="Purchase date"><UDateInput v-model="form.purchase_date" class="w-full" /></UFormField>
            <UFormField label="Purchase cost"><UInput v-model.number="form.purchase_cost" type="number" class="w-full" /></UFormField>
            <UFormField label="Salvage value"><UInput v-model.number="form.salvage_value" type="number" class="w-full" /></UFormField>
            <UFormField label="Useful life (years)"><UInput v-model.number="form.useful_life_years" type="number" class="w-full" /></UFormField>
            <UFormField label="Assigned to" class="span-2">
              <UStaffDirectoryPicker
                v-model="form.assigned_staff_id"
                :initial-label="assigneeInitialLabel"
              />
            </UFormField>
            <UFormField label="Status">
              <USelect v-model="form.status" :items="statusItems" class="w-full" />
            </UFormField>
            <UFormField label="Location" class="span-3"><UInput v-model="form.location" class="w-full" /></UFormField>
            <UFormField label="Notes" class="span-3"><UTextarea v-model="form.notes" :rows="3" class="w-full" /></UFormField>
          </div>
          <div class="form-actions">
            <UButton color="neutral" variant="outline" :disabled="busy" @click="cancelForm">Cancel</UButton>
            <UButton color="primary" :loading="busy" @click="save">Save asset</UButton>
          </div>
        </UCard>
      </div>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.tabs { display: flex; gap: 0.35rem; border-bottom: 1px solid #e2e8f0; }
.tab {
  border: 0; background: transparent; padding: 0.55rem 0.9rem; cursor: pointer;
  font-weight: 600; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.tab.active { color: #0d7a3a; border-bottom-color: #0d7a3a; }
.tab-panel { display: flex; flex-direction: column; gap: 1rem; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; }
.kpi-card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
  padding: 0.9rem 1rem; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.kpi-label { font-size: 0.72rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; }
.kpi-value { margin-top: 0.25rem; font-size: 1.35rem; font-weight: 800; color: #0f172a; }
.text-success { color: #047857; }
.text-muted { color: #64748b; }
.cat-strip { padding: 0.85rem 1rem; }
.cat-strip-label {
  display: block; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.04em; color: #64748b; margin-bottom: 0.5rem;
}
.cat-chips { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.cat-chip {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: #f0fdf4; color: #166534; padding: 0.35rem 0.7rem; border-radius: 8px;
  font-size: 0.8rem; font-weight: 600; border: 1px solid #bbf7d0;
}
.cat-chip em { font-style: normal; color: #64748b; font-weight: 500; }
.filters { display: flex; flex-wrap: wrap; gap: 0.65rem; align-items: flex-end; }
.filter-grow { flex: 1; min-width: 14rem; }
.filter-sm { min-width: 10rem; }
.filter-btn { margin-bottom: 0.15rem; }
.form-panel { border-style: solid; }
.form-header h3 { margin: 0; font-size: 1rem; }
.form-intro { margin: 0.25rem 0 0; color: #64748b; font-size: 0.85rem; }
.form-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.85rem; flex-wrap: wrap; }
.table-wrap { overflow-x: auto; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.65rem 0.85rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.data-table thead th {
  background: #f8fafc; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.03em;
  color: #64748b; font-weight: 700;
}
.data-table tbody tr:hover { background: #f8fafc; }
.data-table th.num, .data-table td.num { text-align: right; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.8rem; }
.wrap { max-width: 220px; word-wrap: break-word; }
.actions { white-space: nowrap; }
.actions-col { width: 1%; }
.badge {
  display: inline-block; padding: 0.15rem 0.5rem; border-radius: 6px;
  font-size: 0.72rem; font-weight: 700; background: #f1f5f9; color: #475569;
}
.badge-ok { background: #dcfce7; color: #166534; }
.badge-warn { background: #fef3c7; color: #92400e; }
.badge-muted { background: #e2e8f0; color: #475569; }
.muted { color: #64748b; }
.empty { text-align: center; padding: 2rem; margin: 0; }
</style>
