<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

const SECTIONS = [
  { key: 'extramural', label: 'Extramural' },
  { key: 'indirect_cost', label: 'Indirect Cost' },
  { key: 'administrative_cost', label: 'Administrative Cost' },
  { key: 'investments', label: 'Investments & Bank' },
  { key: 'afef', label: 'AfEF' },
] as const

type Row = { sort_order: number; data: Record<string, unknown> }

const tab = ref<(typeof SECTIONS)[number]['key']>('extramural')
const rows = ref<Row[]>([])
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)

async function loadSection() {
  loading.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.get(`/api/v1/portfolio/sections/${tab.value}`)
    rows.value = (data.data.rows || []).map((r: Row, i: number) => ({
      sort_order: r.sort_order ?? i,
      data: { ...(r.data || {}) },
    }))
  } catch (e) {
    error.value = apiErrorMessage(e, 'Failed to load section.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  message.value = null
  try {
    await api.put(`/api/v1/portfolio/sections/${tab.value}`, {
      rows: rows.value.map((r, i) => ({ sort_order: i, data: r.data })),
    })
    message.value = 'Saved.'
    await loadSection()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Save failed.')
  } finally {
    saving.value = false
  }
}

function addRow() {
  rows.value.push({ sort_order: rows.value.length, data: { label: '', amount: 0 } })
}

function removeRow(idx: number) {
  rows.value.splice(idx, 1)
}

function dataKeys(row: Row): string[] {
  return Object.keys(row.data || {})
}

watch(tab, loadSection)
onMounted(loadSection)
</script>

<template>
  <div class="fin-entry">
    <header class="rr-page__header">
      <div>
        <h1 class="rr-page__title">Portfolio data entry</h1>
        <p class="rr-page__sub">Edit non-SAP portfolio sections (demo-seeded).</p>
      </div>
      <div class="fin-entry__actions">
        <button type="button" class="btn" :disabled="loading || saving" @click="addRow">Add row</button>
        <button type="button" class="btn btn-primary" :disabled="loading || saving" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </div>
    </header>

    <div class="fin-tabs">
      <button
        v-for="s in SECTIONS"
        :key="s.key"
        type="button"
        class="fin-tabs__btn"
        :class="{ 'is-active': tab === s.key }"
        @click="tab = s.key"
      >
        {{ s.label }}
      </button>
    </div>

    <div v-if="loading" class="rr-muted">Loading…</div>
    <div v-else-if="error" class="rr-error">{{ error }}</div>
    <div v-else class="fin-table-wrap">
      <p v-if="message" class="rr-ok">{{ message }}</p>
      <table class="fin-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Fields (JSON-shaped)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in rows" :key="idx">
            <td>{{ idx + 1 }}</td>
            <td>
              <div class="fin-fields">
                <label v-for="key in dataKeys(row)" :key="key" class="fin-field">
                  <span>{{ key }}</span>
                  <input v-model="row.data[key]" type="text" />
                </label>
              </div>
            </td>
            <td>
              <button type="button" class="btn btn-danger" @click="removeRow(idx)">Remove</button>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="3" class="rr-muted">No rows. Add a row or re-seed demo data.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.fin-entry__actions {
  display: flex;
  gap: 0.5rem;
}
.fin-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin: 1rem 0;
}
.fin-tabs__btn {
  border: 1px solid rgba(0, 0, 0, 0.12);
  background: #fff;
  border-radius: 999px;
  padding: 0.4rem 0.85rem;
  cursor: pointer;
}
.fin-tabs__btn.is-active {
  background: #0b3b5c;
  color: #fff;
  border-color: #0b3b5c;
}
.fin-table {
  width: 100%;
  border-collapse: collapse;
}
.fin-table th,
.fin-table td {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  padding: 0.65rem;
  vertical-align: top;
  text-align: left;
}
.fin-fields {
  display: grid;
  gap: 0.4rem;
}
.fin-field {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 0.5rem;
  align-items: center;
  font-size: 0.85rem;
}
.fin-field input {
  border: 1px solid rgba(0, 0, 0, 0.15);
  border-radius: 6px;
  padding: 0.35rem 0.5rem;
}
.btn {
  border: 1px solid rgba(0, 0, 0, 0.15);
  background: #fff;
  border-radius: 8px;
  padding: 0.45rem 0.8rem;
  cursor: pointer;
}
.btn-primary {
  background: #0b3b5c;
  color: #fff;
  border-color: #0b3b5c;
}
.btn-danger {
  color: #b42318;
}
.rr-ok {
  color: #027a48;
}
.rr-error {
  color: #b42318;
}
</style>
