<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '@/lib/api'

const props = defineProps<{
  source: 'cost-centres' | 'approval-chains'
  title: string
}>()

const rows = ref<Array<Record<string, unknown>>>([])
const error = ref('')
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get<{ data: Array<Record<string, unknown>> }>(`/api/v1/configuration/${props.source}`)
    rows.value = data.data || []
  } catch {
    error.value = 'Could not load these records.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <h2 class="cfg-records-title">{{ title }}</h2>
    <p v-if="loading">Loading…</p>
    <p v-else-if="error">{{ error }}</p>
    <p v-else-if="rows.length === 0">No records yet.</p>
    <table v-else class="cfg-records">
      <thead>
        <tr>
          <th v-if="source === 'cost-centres'">Name</th>
          <th v-if="source === 'cost-centres'">Type</th>
          <th v-if="source === 'approval-chains'">Workflow</th>
          <th v-if="source === 'approval-chains'">Active</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="String(row.id)">
          <template v-if="source === 'cost-centres'">
            <td>{{ row.name }}</td>
            <td>{{ row.cost_type }}</td>
          </template>
          <template v-else>
            <td>{{ row.workflow_name }}</td>
            <td>{{ Number(row.is_active) === 1 ? 'Yes' : 'No' }}</td>
          </template>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.cfg-records-title {
  margin: 0 0 0.8rem;
  color: #1b2744;
  font-size: 1.15rem;
}

.cfg-records {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}

.cfg-records th,
.cfg-records td {
  padding: 0.55rem 0.7rem;
  border-bottom: 1px solid #e4e9f0;
  text-align: left;
}

.cfg-records th {
  color: #5c6b80;
  font-size: 0.78rem;
  text-transform: uppercase;
}
</style>
