<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import OrgStructureMermaidChart from '@/components/molecules/OrgStructureMermaidChart.vue'
import OrgStructureTreeNodes from '@/components/molecules/OrgStructureTreeNodes.vue'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import {
  fetchOrgStructure,
  generateOrgStructure,
  updateOrgStructureNode,
  type OrgStructureNode,
} from '@/lib/settingsApi'

const loading = ref(false)
const generating = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const tree = ref<OrgStructureNode[]>([])
const totals = ref<Record<string, number>>({ nodes: 0, approved: 0, filled: 0, vacant: 0 })
const readyMessage = ref<string | null>(null)
const expanded = ref<Record<number, boolean>>({})
const viewTab = ref<'chart' | 'tree'>('chart')
const chartDepth = ref(99)
const showEdit = ref(false)
const editing = ref<OrgStructureNode | null>(null)
const editForm = reactive({
  title: '',
  approved_slots: 1,
  sort_order: 0,
  notes: '',
})

const depthOptions = [
  { title: 'Full tree', value: 99 },
  { title: 'Depth 3', value: 3 },
  { title: 'Depth 4', value: 4 },
  { title: 'Depth 5', value: 5 },
  { title: 'Depth 6', value: 6 },
  { title: 'Depth 8', value: 8 },
]

function applyTreePayload(payload: {
  tree: OrgStructureNode[]
  meta: { ready: boolean; message?: string; totals: Record<string, number> }
}) {
  tree.value = payload.tree || []
  readyMessage.value = payload.meta?.message || null
  totals.value = payload.meta?.totals || { nodes: 0, approved: 0, filled: 0, vacant: 0 }
  const open: Record<number, boolean> = {}
  const walk = (nodes: OrgStructureNode[], depth: number) => {
    for (const n of nodes) {
      if (depth < 2) open[n.id] = true
      if (n.children?.length) walk(n.children, depth + 1)
    }
  }
  walk(tree.value, 0)
  expanded.value = open
}

async function load() {
  loading.value = true
  error.value = null
  try {
    applyTreePayload(await fetchOrgStructure())
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load organizational structure')
  } finally {
    loading.value = false
  }
}

async function onGenerate() {
  if (
    !window.confirm(
      'Regenerate structure from active staff contracts?\n\n'
        + '• Link reports-to using each contract’s first supervisor\n'
        + '• Fall back to DG / director / HOD roles when supervisor is missing\n'
        + '• Sort peers by salary grade (GAS/GSA lowest)\n\n'
        + 'This replaces the current generated tree.',
    )
  ) {
    return
  }
  generating.value = true
  error.value = null
  success.value = null
  try {
    const res = await generateOrgStructure(true)
    success.value = res.message
    applyTreePayload(res.tree)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not generate structure')
  } finally {
    generating.value = false
  }
}

function toggle(id: number) {
  expanded.value = { ...expanded.value, [id]: !expanded.value[id] }
}

function openEdit(node: OrgStructureNode) {
  editing.value = node
  editForm.title = node.title
  editForm.approved_slots = node.approved_slots
  editForm.sort_order = node.sort_order
  editForm.notes = node.notes || ''
  showEdit.value = true
}

async function saveEdit() {
  if (!editing.value) return
  saving.value = true
  error.value = null
  try {
    const res = await updateOrgStructureNode(editing.value.id, {
      title: editForm.title.trim(),
      approved_slots: Number(editForm.approved_slots),
      sort_order: Number(editForm.sort_order),
      notes: editForm.notes || null,
    })
    success.value = res.message
    applyTreePayload(res.tree)
    showEdit.value = false
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not update position')
  } finally {
    saving.value = false
  }
}

function tierLabel(tier?: string | null): string {
  const map: Record<string, string> = {
    root: 'Organization',
    dg: 'Director General',
    ddg: 'Deputy DG',
    cos: 'Chief of Staff',
    dcos: 'Deputy Chief of Staff',
    director: 'Director',
    hod: 'Division head',
    staff: 'Staff',
  }
  return map[tier || ''] || tier || 'Position'
}

onMounted(() => void load())
</script>

<template>
  <div class="org-structure-page">
    <PortalPageChrome
      title="Organizational structure"
      lede="Approved vs filled positions. Generate from staff contracts using first supervisor (reports-to), with role/division fallbacks. Browse as a Mermaid flowchart (APM workflow style) or a tree list."
    >
      <template #actions>
        <RouterLink to="/settings" style="text-decoration: none">
          <v-btn size="small" variant="outlined">Settings</v-btn>
        </RouterLink>
        <PortalBtn size="small" :loading="generating" @click="onGenerate">
          Generate from system data
        </PortalBtn>
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="readyMessage" type="info" variant="tonal" class="mb-3" density="compact">{{ readyMessage }}</v-alert>

    <div class="org-kpis mb-4">
      <div class="org-kpi">
        <div class="org-kpi__value">{{ totals.nodes || 0 }}</div>
        <div class="org-kpi__label">Nodes</div>
      </div>
      <div class="org-kpi">
        <div class="org-kpi__value">{{ totals.approved || 0 }}</div>
        <div class="org-kpi__label">Approved</div>
      </div>
      <div class="org-kpi">
        <div class="org-kpi__value">{{ totals.filled || 0 }}</div>
        <div class="org-kpi__label">Filled</div>
      </div>
      <div class="org-kpi">
        <div class="org-kpi__value">{{ totals.vacant || 0 }}</div>
        <div class="org-kpi__label">Vacant</div>
      </div>
    </div>

    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <v-card v-else variant="outlined">
      <v-tabs v-model="viewTab" density="comfortable" color="primary">
        <v-tab value="chart">Graphical chart</v-tab>
        <v-tab value="tree">Tree list</v-tab>
      </v-tabs>
      <v-divider />
      <v-card-text>
        <div v-if="!tree.length" class="text-medium-emphasis py-4">
          No structure yet. Use <strong>Generate from system data</strong> to build the hierarchy from
          first supervisors, executive roles, directors, HODs and grade order.
        </div>
        <template v-else>
          <div v-show="viewTab === 'chart'">
            <div class="d-flex flex-wrap align-center ga-2 mb-3">
              <v-select
                v-model="chartDepth"
                :items="depthOptions"
                item-title="title"
                item-value="value"
                label="Chart depth"
                density="compact"
                hide-details
                style="max-width: 11rem"
              />
              <span class="text-caption text-medium-emphasis">
                Full tree by default. Lower depth to truncate with “+ N more…”.
              </span>
            </div>
            <div class="org-mermaid-legend mb-2">
              <span class="org-leg org-leg--exec">Executive</span>
              <span class="org-leg org-leg--director">Director</span>
              <span class="org-leg org-leg--hod">HOD</span>
              <span class="org-leg org-leg--staff">Staff</span>
              <span class="org-leg org-leg--vacant">Vacant</span>
            </div>
            <OrgStructureMermaidChart :tree="tree" :max-depth="chartDepth" />
          </div>
          <ul v-show="viewTab === 'tree'" class="org-tree org-tree--root">
            <OrgStructureTreeNodes
              :nodes="tree"
              :expanded="expanded"
              @toggle="toggle"
              @edit="openEdit"
            />
          </ul>
        </template>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showEdit" max-width="560" persistent>
      <v-card>
        <v-card-title>Edit position</v-card-title>
        <v-card-text>
          <div v-if="editing" class="text-caption text-medium-emphasis mb-3">
            {{ tierLabel(editing.tier) }}
            · Approved {{ editing.approved_slots }} · Filled {{ editing.filled_slots }}
          </div>
          <v-text-field v-model="editForm.title" label="Position title" density="comfortable" class="mb-2" />
          <v-text-field
            v-model.number="editForm.approved_slots"
            type="number"
            min="0"
            label="Approved slots"
            density="comfortable"
            class="mb-2"
            hint="Establishment count for this position. Filled comes from assigned staff."
            persistent-hint
          />
          <v-text-field
            v-model.number="editForm.sort_order"
            type="number"
            label="Sort order"
            density="comfortable"
            class="mb-2"
          />
          <v-textarea v-model="editForm.notes" label="Notes" rows="2" density="comfortable" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showEdit = false">Cancel</v-btn>
          <PortalBtn :loading="saving" @click="saveEdit">Save</PortalBtn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.org-kpis {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
}
.org-kpi {
  border: 1px solid rgba(58, 71, 82, 0.14);
  border-radius: 0.55rem;
  padding: 0.85rem 1rem;
  background: linear-gradient(180deg, #f7faf8 0%, #fff 100%);
}
.org-kpi__value {
  font-size: 1.4rem;
  font-weight: 700;
  color: #3a4752;
}
.org-kpi__label {
  font-size: 0.78rem;
  color: rgba(58, 71, 82, 0.7);
}
.org-tree--root {
  list-style: none;
  margin: 0;
  padding: 0.35rem 0;
}
.org-mermaid-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}
.org-leg {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  padding: 0.15rem 0.45rem;
  border-radius: 0.3rem;
  border: 1px solid transparent;
}
.org-leg--exec {
  background: #d1e7dd;
  color: #0f5132;
  border-color: #198754;
}
.org-leg--director {
  background: #cfe2ff;
  color: #084298;
  border-color: #0d6efd;
}
.org-leg--hod {
  background: #fff3cd;
  color: #664d03;
  border-color: #ffc107;
}
.org-leg--staff {
  background: #f8f9fa;
  color: #3a4752;
  border-color: #adb5bd;
}
.org-leg--vacant {
  background: #f8d7da;
  color: #842029;
  border-color: #dc3545;
  border-style: dashed;
}
@media (max-width: 700px) {
  .org-kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
