<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'

interface Cat {
  id: number
  name: string
}

interface AgentRow {
  id: number
  name: string
  email: string
  staff_id: number | null
  categories: Cat[]
}

const cats = ref<Cat[]>([])
const agents = ref<AgentRow[]>([])
const selection = ref<Record<number, number[]>>({})
const err = ref<string | null>(null)
const ok = ref<string | null>(null)
const catsErr = ref<string | null>(null)

async function loadCats() {
  catsErr.value = null
  const { data } = await api.get<{ data: Cat[] }>('/api/v1/categories')
  cats.value = Array.isArray(data.data) ? data.data : []
}

async function loadAgents() {
  const { data } = await api.get<{ data: AgentRow[] }>('/api/v1/admin/agents')
  const list = Array.isArray(data.data) ? data.data : []
  agents.value = list
  const map: Record<number, number[]> = {}
  for (const a of list) {
    map[a.id] = (a.categories ?? []).map((c) => c.id)
  }
  selection.value = map
}

async function loadAll() {
  err.value = null
  ok.value = null
  catsErr.value = null
  try {
    await loadAgents()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to load agents.')
    agents.value = []
    selection.value = {}
  }
  try {
    await loadCats()
  } catch (e: unknown) {
    catsErr.value = apiErrorMessage(e, 'Failed to load categories.')
    cats.value = []
  }
}

async function saveAgent(userId: number) {
  ok.value = null
  err.value = null
  try {
    await api.put(`/api/v1/admin/agents/${userId}`, {
      category_ids: (selection.value[userId] ?? []).map((id) => Number(id)),
    })
    ok.value = `Saved category routing for agent #${userId}`
    await loadAgents()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Save failed')
  }
}

onMounted(() => {
  void loadAll()
})
</script>

<template>
  <section class="panel" aria-labelledby="agents-heading">
    <h2 id="agents-heading">Agents &amp; category routing</h2>
    <p class="lede">
      Pick which issue categories each agent may be assigned to. An agent with <strong>no</strong> categories selected receives
      <strong>all</strong> categories for automatic assignment. Staff in configured divisions become agents on first SSO.
    </p>
    <p v-if="err" class="err">{{ err }}</p>
    <p v-if="catsErr" class="warn">{{ catsErr }}</p>
    <p v-if="ok" class="ok">{{ ok }}</p>
    <table v-if="agents.length" class="tbl">
      <thead>
        <tr>
          <th>Agent</th>
          <th>Staff ID</th>
          <th>Categories</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="a in agents" :key="a.id">
          <td>{{ a.name }}<br /><span class="muted">{{ a.email }}</span></td>
          <td>{{ a.staff_id ?? '—' }}</td>
          <td>
            <select v-model="selection[a.id]" multiple class="multi">
              <option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </td>
          <td>
            <button type="button" class="btn" @click="saveAgent(a.id)">Save</button>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else-if="!err" class="muted">No agents yet — sign in once as division staff or promote users to agent.</p>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
}
.lede {
  color: #475569;
  line-height: 1.5;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}
.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
  background: var(--cdc-white, #fff);
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--cdc-line, rgba(12, 26, 18, 0.08));
}
.tbl th,
.tbl td {
  text-align: left;
  padding: 0.6rem 0.4rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: top;
}
.multi {
  min-width: 220px;
  min-height: 120px;
}
.btn {
  padding: 0.4rem 0.75rem;
  border-radius: 8px;
  border: none;
  background: #119a48;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.muted {
  color: #64748b;
  font-size: 0.8rem;
}
.err {
  color: #b91c1c;
}
.warn {
  color: #a16207;
  font-weight: 600;
}
.ok {
  color: #166534;
  font-weight: 600;
}
</style>
