<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
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

interface Asset {
  id: number
  asset_tag: string
  category_id: number
  name: string
  brand?: string
  model?: string
  serial_number?: string
  purchase_date?: string
  purchase_cost: string | number
  salvage_value?: string | number
  useful_life_years?: number
  assigned_name?: string
  status: string
  location?: string
  notes?: string
  category?: Category
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

const summary = ref<Summary | null>(null)
const categories = ref<Category[]>([])
const assets = ref<Asset[]>([])
const loading = ref(true)
const filterCategory = ref(0)
const search = ref('')
const showForm = ref(false)
const editing = ref<Asset | null>(null)

const form = reactive({
  asset_tag: '',
  category_id: 0,
  name: '',
  brand: '',
  model: '',
  serial_number: '',
  purchase_date: '',
  purchase_cost: 0,
  salvage_value: 0,
  useful_life_years: 0,
  assigned_name: '',
  status: 'deployed',
  location: '',
  notes: '',
})

const fmtMoney = (n: number | string) =>
  '$' + (parseFloat(String(n)) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const categoryItems = computed((): SelectNumberItem[] =>
  categories.value.map((c) => ({ label: c.name, value: c.id })),
)

const filterItems = computed((): SelectNumberItem[] => [
  { label: 'All categories', value: 0 },
  ...categoryItems.value,
])

async function load() {
  loading.value = true
  try {
    const [sumRes, catRes, assetRes] = await Promise.all([
      api.get<{ data: Summary }>('/api/v1/tools/it-assets/summary'),
      api.get<{ data: Category[] }>('/api/v1/tools/it-assets/categories'),
      api.get<{ data: Asset[] }>('/api/v1/tools/it-assets', {
        params: {
          category_id: filterCategory.value || undefined,
          q: search.value || undefined,
          per_page: 100,
        },
      }),
    ])
    summary.value = sumRes.data.data
    categories.value = catRes.data.data ?? []
    const paginated = assetRes.data as { data?: Asset[] }
    assets.value = Array.isArray(paginated.data) ? paginated.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load IT assets.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    asset_tag: '',
    category_id: categories.value[0]?.id ?? 0,
    name: '',
    brand: '',
    model: '',
    serial_number: '',
    purchase_date: '',
    purchase_cost: 0,
    salvage_value: 0,
    useful_life_years: 0,
    assigned_name: '',
    status: 'deployed',
    location: '',
    notes: '',
  })
  showForm.value = true
}

function openEdit(row: Asset) {
  editing.value = row
  Object.assign(form, {
    asset_tag: row.asset_tag,
    category_id: row.category_id,
    name: row.name,
    brand: row.brand ?? '',
    model: row.model ?? '',
    serial_number: row.serial_number ?? '',
    purchase_date: row.purchase_date?.slice(0, 10) ?? '',
    purchase_cost: Number(row.purchase_cost) || 0,
    salvage_value: Number(row.salvage_value) || 0,
    useful_life_years: row.useful_life_years ?? 0,
    assigned_name: row.assigned_name ?? '',
    status: row.status,
    location: row.location ?? '',
    notes: row.notes ?? '',
  })
  showForm.value = true
}

async function save() {
  try {
    const payload = { ...form, useful_life_years: form.useful_life_years || null }
    if (editing.value) {
      await api.put(`/api/v1/tools/it-assets/${editing.value.id}`, payload)
      notifySuccess('Asset updated.')
    } else {
      await api.post('/api/v1/tools/it-assets', payload)
      notifySuccess('Asset created.')
    }
    showForm.value = false
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
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

onMounted(() => void load())
</script>

<template>
  <div>
    <CbpPageHeading title="IT Assets Management" back-to="/" back-label="← Overview">
      <template #lede>
        Track laptops, phones, and equipment across categories. Current value is recalculated continuously using straight-line depreciation.
      </template>
    </CbpPageHeading>

    <section class="tools-page" aria-labelledby="it-assets-heading">
      <div class="page-actions">
        <UButton color="primary" @click="openCreate">+ Add asset</UButton>
      </div>
      <h2 id="it-assets-heading" class="sr-only">Asset tracker</h2>

      <div v-if="summary" class="kpi-grid">
        <UCard><div class="kpi-label">Assets</div><div class="kpi-value">{{ summary.asset_count }}</div></UCard>
        <UCard><div class="kpi-label">Purchase value</div><div class="kpi-value">{{ fmtMoney(summary.total_purchase_cost) }}</div></UCard>
        <UCard><div class="kpi-label">Current value</div><div class="kpi-value text-success">{{ fmtMoney(summary.total_current_value) }}</div></UCard>
        <UCard><div class="kpi-label">Depreciation</div><div class="kpi-value text-muted">{{ fmtMoney(summary.total_depreciation) }}</div></UCard>
      </div>

      <UCard v-if="summary?.by_category?.length" class="mb-3">
        <template #header><strong>By category</strong></template>
        <div class="cat-chips">
          <span v-for="c in summary.by_category" :key="c.category_name" class="cat-chip">
            {{ c.category_name }}: {{ c.count }} · {{ fmtMoney(c.current_value_total) }}
          </span>
        </div>
      </UCard>

      <div class="toolbar">
        <UFormField label="Category" class="toolbar-field">
          <USelect v-model="filterCategory" :items="filterItems" class="w-full" @update:model-value="load" />
        </UFormField>
        <UFormField label="Search" class="toolbar-field toolbar-field--grow">
          <UInput v-model="search" placeholder="Tag, serial, assignee…" class="w-full" @keyup.enter="load" />
        </UFormField>
        <UButton color="neutral" variant="outline" class="toolbar-btn" @click="load">Search</UButton>
      </div>

      <UCard v-if="showForm" class="form-panel">
        <template #header>
          <h3>{{ editing ? 'Edit asset' : 'New asset' }}</h3>
        </template>
        <div class="hd-form hd-form--grid">
          <UFormField label="Asset tag" required><UInput v-model="form.asset_tag" class="w-full" /></UFormField>
          <UFormField label="Category" required>
            <USelect v-model="form.category_id" :items="categoryItems" class="w-full" />
          </UFormField>
          <UFormField label="Name" required class="span-3"><UInput v-model="form.name" class="w-full" /></UFormField>
          <UFormField label="Brand"><UInput v-model="form.brand" class="w-full" /></UFormField>
          <UFormField label="Model"><UInput v-model="form.model" class="w-full" /></UFormField>
          <UFormField label="Serial"><UInput v-model="form.serial_number" class="w-full" /></UFormField>
          <UFormField label="Purchase date"><UInput v-model="form.purchase_date" type="date" class="w-full" /></UFormField>
          <UFormField label="Purchase cost"><UInput v-model.number="form.purchase_cost" type="number" class="w-full" /></UFormField>
          <UFormField label="Salvage value"><UInput v-model.number="form.salvage_value" type="number" class="w-full" /></UFormField>
          <UFormField label="Useful life (years)"><UInput v-model.number="form.useful_life_years" type="number" class="w-full" /></UFormField>
          <UFormField label="Assigned to"><UInput v-model="form.assigned_name" class="w-full" /></UFormField>
          <UFormField label="Status">
            <USelect
              v-model="form.status"
              :items="['in_stock', 'deployed', 'repair', 'retired'].map((v) => ({ label: v, value: v }))"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Location" class="span-3"><UInput v-model="form.location" class="w-full" /></UFormField>
          <UFormField label="Notes" class="span-3"><UTextarea v-model="form.notes" :rows="3" class="w-full" /></UFormField>
        </div>
        <div class="form-actions">
          <UButton color="neutral" variant="outline" @click="showForm = false">Cancel</UButton>
          <UButton color="primary" @click="save">Save</UButton>
        </div>
      </UCard>

      <p v-if="loading" class="muted">Loading…</p>
      <div v-else class="table-wrap cbp-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>Tag</th><th>Category</th><th>Name</th><th>Age</th>
              <th class="num">Purchase</th><th class="num">Current value</th><th>Assignee</th><th>Status</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in assets" :key="row.id">
              <td><strong>{{ row.asset_tag }}</strong></td>
              <td>{{ row.category?.name ?? '—' }}</td>
              <td class="wrap">{{ row.name }}</td>
              <td>{{ row.valuation ? `${row.valuation.age_years}y` : '—' }}</td>
              <td class="num">{{ fmtMoney(row.purchase_cost) }}</td>
              <td class="num text-success">{{ fmtMoney(row.valuation?.current_value ?? 0) }}</td>
              <td>{{ row.assigned_name || '—' }}</td>
              <td><span class="badge">{{ row.status }}</span></td>
              <td class="actions">
                <UButton size="xs" variant="link" @click="openEdit(row)">Edit</UButton>
                <UButton size="xs" color="error" variant="link" @click="remove(row)">Delete</UButton>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!assets.length" class="muted empty">No assets found.</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.page-actions { display: flex; justify-content: flex-end; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; }
.kpi-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
.kpi-value { font-size: 1.35rem; font-weight: 800; }
.text-success { color: #047857; }
.text-muted { color: #64748b; }
.cat-chips { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.cat-chip { background: #ecfdf5; color: #047857; padding: 0.35rem 0.65rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
.toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: flex-end; }
.toolbar-field { min-width: 180px; }
.toolbar-field--grow { flex: 1; min-width: 220px; }
.toolbar-btn { margin-bottom: 0.15rem; }
.form-panel { border-style: dashed; }
.form-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem; }
.table-wrap { overflow-x: auto; padding: 0; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.data-table th.num, .data-table td.num { text-align: right; }
.wrap { max-width: 220px; word-wrap: break-word; }
.badge { background: #f1f5f9; padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.75rem; }
.muted { color: #64748b; }
.empty { text-align: center; padding: 2rem; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
</style>
