<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import FinSkeleton from '@/components/finance/FinSkeleton.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

type MonthOpt = { value: number; title: string }

const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const startMonth = ref(1)
const currentFyYear = ref<number | null>(null)
const months = ref<MonthOpt[]>([])
const defaultStartMonth = ref(1)

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/settings/financial-year')
    const d = data.data
    startMonth.value = Number(d.start_month || 1)
    currentFyYear.value = Number(d.current_fy_year || new Date().getFullYear())
    defaultStartMonth.value = Number(d.default_start_month || 1)
    months.value = Array.isArray(d.months) ? d.months : []
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load financial year settings.')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put('/api/v1/settings/financial-year', {
      start_month: startMonth.value,
    })
    message.value = data.message || 'Saved.'
    currentFyYear.value = Number(data.data?.current_fy_year || currentFyYear.value)
    startMonth.value = Number(data.data?.start_month || startMonth.value)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save financial year settings.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="fy">
    <header class="fy__header">
      <div>
        <h1>Financial year</h1>
        <p class="fy__sub">
          Africa CDC financial year starts at the beginning of the calendar year by default
          (1 January). Adjust the start month if the FY calendar changes.
        </p>
      </div>
      <RouterLink class="fy__link" :to="{ name: 'budget-execution' }">Budget execution</RouterLink>
    </header>

    <FinSkeleton v-if="loading" variant="form" :rows="3" />
    <template v-else>
      <p v-if="error" class="fy__error">{{ error }}</p>
      <p v-if="message" class="fy__ok">{{ message }}</p>

      <section class="fy__card">
        <label class="fy__field">
          <span>Financial year start month</span>
          <select v-model.number="startMonth" class="fy__select">
            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.title }}</option>
          </select>
        </label>
        <p class="fy__hint">
          Default: {{ months.find((m) => m.value === defaultStartMonth)?.title || 'January' }}.
          Current FY label: <strong>FY {{ currentFyYear }}</strong>.
          Month and quarter trend charts on Budget execution and Performance use this setting.
        </p>
        <button type="button" class="fy__save" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </section>
    </template>
  </div>
</template>

<style scoped>
.fy { width: 100%; max-width: 720px; padding-bottom: 2rem; }
.fy__header {
  display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem;
}
.fy__header h1 { margin: 0; font-size: 1.45rem; font-weight: 700; color: #0b3b5c; }
.fy__sub { margin: 0.4rem 0 0; color: #64748b; line-height: 1.45; }
.fy__link { color: #0b3b5c; font-weight: 650; text-decoration: none; white-space: nowrap; }
.fy__card {
  background: #fff; border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 2px;
  padding: 1.1rem 1.2rem; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.fy__field { display: grid; gap: 0.4rem; margin-bottom: 0.85rem; }
.fy__field span { font-size: 0.85rem; font-weight: 600; color: #334155; }
.fy__select {
  max-width: 280px; border: 1px solid rgba(15, 23, 42, 0.15); border-radius: 2px;
  padding: 0.5rem 0.65rem; background: #fff;
}
.fy__hint { margin: 0 0 1rem; color: #64748b; font-size: 0.88rem; line-height: 1.45; }
.fy__save {
  border: none; background: #0b3b5c; color: #fff; font-weight: 700; border-radius: 2px;
  padding: 0.5rem 1.1rem; cursor: pointer;
}
.fy__save:disabled { opacity: 0.65; cursor: wait; }
.fy__error { color: #b42318; }
.fy__ok { color: #166534; }
</style>
