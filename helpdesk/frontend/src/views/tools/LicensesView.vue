<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '../../components/common/CbpPageHeading.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError, notifySuccess } from '../../lib/notify'

interface LicenseExpiry {
  days_until_expiry: number | null
  expiry_status: string
  is_expiring_soon: boolean
  is_expired: boolean
}

interface License {
  id: number
  name: string
  vendor?: string
  license_key?: string
  purchase_date?: string
  duration_months?: number
  expiry_date?: string
  seats_total?: number
  seats_used?: number
  cost?: string | number
  notes?: string
  expiry?: LicenseExpiry
}

interface Summary {
  license_count: number
  expiring_soon: number
  expired: number
  total_seats: number
  seats_used: number
  annual_cost: number
}

const summary = ref<Summary | null>(null)
const licenses = ref<License[]>([])
const loading = ref(true)
const showForm = ref(false)
const editing = ref<License | null>(null)

const form = reactive({
  name: '',
  vendor: '',
  license_key: '',
  purchase_date: '',
  duration_months: 12,
  seats_total: 1,
  seats_used: 0,
  cost: 0,
  notes: '',
})

const expiringList = computed(() =>
  licenses.value.filter((l) => l.expiry?.is_expiring_soon || l.expiry?.is_expired),
)

const activeCount = computed(() =>
  licenses.value.filter((l) => !l.expiry?.is_expired && l.expiry?.expiry_status !== 'no_expiry').length,
)

function statusClass(l: License) {
  if (l.expiry?.is_expired) return 'status-expired'
  if (l.expiry?.is_expiring_soon) return 'status-warning'
  return 'status-active'
}

function statusLabel(l: License) {
  if (l.expiry?.is_expired) {
    const days = l.expiry.days_until_expiry ?? 0
    return `Expired ${Math.abs(days)}d ago`
  }
  if (l.expiry?.is_expiring_soon) {
    return `Expires in ${l.expiry.days_until_expiry ?? 0}d`
  }
  if (l.expiry?.days_until_expiry != null) {
    return `Active · ${l.expiry.days_until_expiry}d left`
  }
  return 'No expiry date'
}

const fmtMoney = (n: number | string | undefined) =>
  n != null ? '$' + (parseFloat(String(n)) || 0).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'

async function load() {
  loading.value = true
  try {
    const [sumRes, listRes] = await Promise.all([
      api.get<{ data: Summary }>('/api/v1/tools/licenses/summary'),
      api.get<{ data: License[] }>('/api/v1/tools/licenses', { params: { per_page: 100 } }),
    ])
    summary.value = sumRes.data.data
    const paginated = listRes.data as { data?: License[] }
    licenses.value = Array.isArray(paginated.data) ? paginated.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load licenses.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    name: '',
    vendor: '',
    license_key: '',
    purchase_date: '',
    duration_months: 12,
    seats_total: 1,
    seats_used: 0,
    cost: 0,
    notes: '',
  })
  showForm.value = true
}

function openEdit(row: License) {
  editing.value = row
  Object.assign(form, {
    name: row.name,
    vendor: row.vendor ?? '',
    license_key: row.license_key ?? '',
    purchase_date: row.purchase_date?.slice(0, 10) ?? '',
    duration_months: row.duration_months ?? 12,
    seats_total: row.seats_total ?? 1,
    seats_used: row.seats_used ?? 0,
    cost: Number(row.cost) || 0,
    notes: row.notes ?? '',
  })
  showForm.value = true
}

async function save() {
  try {
    if (editing.value) {
      await api.put(`/api/v1/tools/licenses/${editing.value.id}`, form)
      notifySuccess('License updated.')
    } else {
      await api.post('/api/v1/tools/licenses', form)
      notifySuccess('License created.')
    }
    showForm.value = false
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  }
}

async function remove(row: License) {
  if (!window.confirm(`Delete license "${row.name}"?`)) return
  try {
    await api.delete(`/api/v1/tools/licenses/${row.id}`)
    notifySuccess('License deleted.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Delete failed.'))
  }
}

onMounted(() => void load())
</script>

<template>
  <div>
    <CbpPageHeading title="Licenses Management" back-to="/" back-label="← Overview">
      <template #lede>
        Track software licenses with purchase date, duration, and expiry warnings before renewal is due.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <div class="page-actions">
        <UButton color="primary" @click="openCreate">+ Add license</UButton>
      </div>
      <div v-if="summary" class="kpi-grid">
        <UCard><div class="kpi-label">Total</div><div class="kpi-value">{{ summary.license_count }}</div></UCard>
        <UCard><div class="kpi-label">Active</div><div class="kpi-value text-success">{{ activeCount }}</div></UCard>
        <UCard><div class="kpi-label">Expiring soon</div><div class="kpi-value text-warning">{{ summary.expiring_soon }}</div></UCard>
        <UCard><div class="kpi-label">Expired</div><div class="kpi-value text-danger">{{ summary.expired }}</div></UCard>
      </div>

      <UCard v-if="expiringList.length" class="alert-card">
        <template #header><strong><i class="bx bx-error-circle" /> Expiry warnings</strong></template>
        <ul class="warn-list">
          <li v-for="l in expiringList" :key="l.id" :class="statusClass(l)">
            <strong>{{ l.name }}</strong> — {{ statusLabel(l) }}
            <span v-if="l.expiry_date"> ({{ l.expiry_date.slice(0, 10) }})</span>
          </li>
        </ul>
      </UCard>

      <UCard v-if="showForm" class="form-panel">
        <template #header><h3>{{ editing ? 'Edit license' : 'New license' }}</h3></template>
        <div class="hd-form hd-form--grid">
          <UFormField label="License name" required class="span-3"><UInput v-model="form.name" class="w-full" /></UFormField>
          <UFormField label="Vendor"><UInput v-model="form.vendor" class="w-full" /></UFormField>
          <UFormField label="License key"><UInput v-model="form.license_key" class="w-full" /></UFormField>
          <UFormField label="Purchase date"><UInput v-model="form.purchase_date" type="date" class="w-full" /></UFormField>
          <UFormField label="Duration (months)"><UInput v-model.number="form.duration_months" type="number" class="w-full" /></UFormField>
          <UFormField label="Seats total"><UInput v-model.number="form.seats_total" type="number" class="w-full" /></UFormField>
          <UFormField label="Seats used"><UInput v-model.number="form.seats_used" type="number" class="w-full" /></UFormField>
          <UFormField label="Cost"><UInput v-model.number="form.cost" type="number" class="w-full" /></UFormField>
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
              <th>Name</th><th>Vendor</th><th>Purchased</th><th>Duration</th>
              <th>Expiry</th><th>Status</th><th class="num">Cost</th><th>Seats</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in licenses" :key="row.id">
              <td><strong>{{ row.name }}</strong></td>
              <td>{{ row.vendor || '—' }}</td>
              <td>{{ row.purchase_date?.slice(0, 10) ?? '—' }}</td>
              <td>{{ row.duration_months ? `${row.duration_months} mo` : '—' }}</td>
              <td>{{ row.expiry_date?.slice(0, 10) ?? '—' }}</td>
              <td><span class="status-pill" :class="statusClass(row)">{{ statusLabel(row) }}</span></td>
              <td class="num">{{ fmtMoney(row.cost) }}</td>
              <td>{{ row.seats_used ?? 0 }}/{{ row.seats_total ?? 1 }}</td>
              <td class="actions">
                <UButton size="xs" variant="link" @click="openEdit(row)">Edit</UButton>
                <UButton size="xs" color="error" variant="link" @click="remove(row)">Delete</UButton>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!licenses.length" class="muted empty">No licenses yet.</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; }
.page-actions { display: flex; justify-content: flex-end; }
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; }
.kpi-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; }
.kpi-value { font-size: 1.35rem; font-weight: 800; }
.text-success { color: #047857; }
.text-warning { color: #d97706; }
.text-danger { color: #dc2626; }
.alert-card { border-left: 4px solid #f59e0b; }
.warn-list { margin: 0; padding-left: 1.25rem; }
.warn-list li { margin-bottom: 0.35rem; }
.status-pill { padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
.status-active { background: #ecfdf5; color: #047857; }
.status-warning { background: #fffbeb; color: #b45309; }
.status-expired { background: #fef2f2; color: #b91c1c; }
.form-panel { border-style: dashed; }
.form-actions { display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e2e8f0; text-align: left; }
.data-table th.num, .data-table td.num { text-align: right; }
.muted { color: #64748b; }
.empty { text-align: center; padding: 2rem; }
</style>
