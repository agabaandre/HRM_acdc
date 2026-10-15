<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import FinSkeleton from '@/components/finance/FinSkeleton.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { FINANCE_PERMS } from '@/lib/financePermissions'
import { useAuthStore } from '@/stores/auth'

type AfefCategory = {
  key: string
  label: string
  sort_order: number
  is_active: boolean
}

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const canEditApi = ref(false)
const categories = ref<AfefCategory[]>([])
const newLabel = ref('')

const canEdit = computed(
  () =>
    canEditApi.value ||
    auth.hasPermission(FINANCE_PERMS.MANAGE_FUNDING_PORTFOLIO) ||
    auth.hasPermission(FINANCE_PERMS.SETTINGS),
)

const activeCount = computed(() => categories.value.filter((c) => c.is_active).length)

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/settings/afef-categories')
    categories.value = (data.data.categories || []).map((c: AfefCategory) => ({
      ...c,
      is_active: !!c.is_active,
    }))
    canEditApi.value = !!data.data.can_edit
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load AfEF categories.')
  } finally {
    loading.value = false
  }
}

function addRow() {
  const label = newLabel.value.trim()
  if (!label || !canEdit.value) return
  const key = label
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_|_$/g, '')
  if (!key) return
  if (categories.value.some((c) => c.key === key)) {
    error.value = `Category key “${key}” already exists.`
    return
  }
  const maxSort = categories.value.reduce((m, c) => Math.max(m, Number(c.sort_order || 0)), 0)
  categories.value.push({
    key,
    label,
    sort_order: maxSort + 10,
    is_active: true,
  })
  newLabel.value = ''
  error.value = null
}

function removeRow(idx: number) {
  if (!canEdit.value) return
  categories.value.splice(idx, 1)
}

function move(idx: number, dir: -1 | 1) {
  const next = idx + dir
  if (next < 0 || next >= categories.value.length) return
  const copy = [...categories.value]
  const [row] = copy.splice(idx, 1)
  copy.splice(next, 0, row)
  categories.value = copy.map((c, i) => ({ ...c, sort_order: (i + 1) * 10 }))
}

async function save() {
  if (!canEdit.value) return
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put('/api/v1/settings/afef-categories', {
      categories: categories.value.map((c, i) => ({
        key: c.key,
        label: c.label,
        sort_order: Number(c.sort_order) || (i + 1) * 10,
        is_active: !!c.is_active,
      })),
    })
    message.value = data.message || 'AfEF categories saved.'
    categories.value = (data.data.categories || categories.value).map((c: AfefCategory) => ({
      ...c,
      is_active: !!c.is_active,
    }))
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save AfEF categories.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="afef-cat">
    <header class="afef-cat__header">
      <div class="afef-cat__title-block">
        <div class="afef-cat__title-row">
          <h1>AfEF categories</h1>
          <DivisionContextSwitcher />
        </div>
        <p class="afef-cat__sub">
          Standard categories for Africa Epidemics Fund lines on Portfolio data entry
          (opening, income, expense, CPHIA, balance, …).
        </p>
      </div>
      <div class="afef-cat__actions">
        <RouterLink class="afef-cat__link" :to="{ name: 'portfolio-entry' }">Portfolio entry</RouterLink>
        <button
          v-if="canEdit"
          type="button"
          class="btn btn-primary"
          :disabled="saving || loading || !categories.length"
          @click="save"
        >
          {{ saving ? 'Saving…' : 'Save categories' }}
        </button>
      </div>
    </header>

    <p v-if="error" class="afef-cat__error">{{ error }}</p>
    <p v-if="message" class="afef-cat__ok">{{ message }}</p>
    <FinSkeleton v-if="loading" variant="table" :rows="6" />

    <template v-else>
      <p class="afef-cat__status">
        {{ activeCount }} active · {{ categories.length }} total
        <span v-if="!canEdit"> · view only</span>
      </p>

      <section v-if="canEdit" class="afef-cat__card">
        <div class="afef-cat__add">
          <input
            v-model="newLabel"
            type="text"
            placeholder="New category label (e.g. Contingency)"
            @keyup.enter="addRow"
          />
          <button type="button" class="btn btn-primary" :disabled="!newLabel.trim()" @click="addRow">
            Add category
          </button>
        </div>
      </section>

      <section class="afef-cat__card">
        <div class="afef-cat__table-wrap">
          <table class="afef-cat__table">
            <thead>
              <tr>
                <th style="width: 4rem">Order</th>
                <th style="width: 10rem">Key</th>
                <th>Label</th>
                <th style="width: 6rem">Active</th>
                <th v-if="canEdit" style="width: 9rem" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in categories" :key="row.key + '-' + idx">
                <td><code>{{ row.sort_order }}</code></td>
                <td>
                  <input v-if="canEdit" v-model="row.key" type="text" class="afef-cat__key" />
                  <code v-else>{{ row.key }}</code>
                </td>
                <td>
                  <input v-if="canEdit" v-model="row.label" type="text" />
                  <span v-else>{{ row.label }}</span>
                </td>
                <td>
                  <label class="afef-cat__check">
                    <input v-model="row.is_active" type="checkbox" :disabled="!canEdit" />
                    <span>{{ row.is_active ? 'Yes' : 'No' }}</span>
                  </label>
                </td>
                <td v-if="canEdit" class="afef-cat__row-actions">
                  <button type="button" class="btn" title="Move up" :disabled="idx === 0" @click="move(idx, -1)">
                    <i class="fa-solid fa-arrow-up" aria-hidden="true" />
                  </button>
                  <button
                    type="button"
                    class="btn"
                    title="Move down"
                    :disabled="idx === categories.length - 1"
                    @click="move(idx, 1)"
                  >
                    <i class="fa-solid fa-arrow-down" aria-hidden="true" />
                  </button>
                  <button type="button" class="btn" title="Remove" @click="removeRow(idx)">
                    <i class="fa-solid fa-trash" aria-hidden="true" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="!categories.length" class="afef-cat__muted">No categories yet. Add one above.</p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.afef-cat { width: 100%; max-width: none; margin: 0; padding-bottom: 2.5rem; }
.afef-cat__header {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem;
}
.afef-cat__title-block { min-width: 0; flex: 1; }
.afef-cat__title-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem;
}
.afef-cat__header h1 { margin: 0; font-size: 1.45rem; }
.afef-cat__sub { color: #64748b; margin: 0.4rem 0 0; max-width: 52rem; line-height: 1.45; }
.afef-cat__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
.afef-cat__link { color: #0b3b5c; font-weight: 600; text-decoration: none; }
.afef-cat__status { color: #64748b; margin: 0 0 0.85rem; font-size: 0.92rem; }
.afef-cat__card {
  background: #fff; border: 1px solid rgba(11,59,92,0.12);
  border-radius: 14px; padding: 1rem 1.1rem; margin-bottom: 0.9rem;
}
.afef-cat__muted { color: #64748b; margin: 0.5rem 0 0; }
.afef-cat__error { color: #b42318; }
.afef-cat__ok { color: #027a48; }
.afef-cat__add {
  display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;
}
.afef-cat__add input,
.afef-cat__table input {
  width: 100%; border: 1px solid rgba(0,0,0,0.16); border-radius: 8px; padding: 0.4rem 0.5rem; font: inherit;
}
.afef-cat__add input { flex: 1; min-width: 220px; }
.afef-cat__key { font-family: ui-monospace, monospace; text-transform: lowercase; }
.afef-cat__table-wrap { overflow-x: auto; }
.afef-cat__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.afef-cat__table th, .afef-cat__table td {
  text-align: left; padding: 0.5rem 0.45rem; border-bottom: 1px solid rgba(0,0,0,0.06); vertical-align: middle;
}
.afef-cat__check { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; }
.afef-cat__row-actions { display: flex; gap: 0.35rem; }
.btn {
  border: 1px solid rgba(0,0,0,0.15); background: #fff; border-radius: 8px;
  padding: 0.45rem 0.8rem; cursor: pointer; font-weight: 600; white-space: nowrap;
}
.btn-primary { background: #0b3b5c; color: #fff; border-color: #0b3b5c; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
@media (max-width: 720px) {
  .afef-cat__header { flex-direction: column; }
}
</style>
