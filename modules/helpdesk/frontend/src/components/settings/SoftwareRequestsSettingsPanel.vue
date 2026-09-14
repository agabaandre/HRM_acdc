<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { notifyError } from '../../lib/notify'
import { useInjectedHelpdeskAdminSettings } from '../../composables/useHelpdeskAdminSettings'

interface SupportGroupRow {
  id: number
  name: string
  slug: string
  is_active: boolean
  members_count?: number
}

interface AgentRow {
  id: number
  name: string
  email: string
  role?: string | null
}

const ctx = useInjectedHelpdeskAdminSettings()
const groups = ref<SupportGroupRow[]>([])
const agents = ref<AgentRow[]>([])
const loading = ref(true)
const selectedGroupIds = ref<number[]>([])
const selectedBoardIds = ref<number[]>([])

const groupItems = computed(() =>
  groups.value.map((g) => ({
    label: `${g.name}${g.is_active ? '' : ' (inactive)'}`,
    value: g.id,
  })),
)

const agentItems = computed(() =>
  agents.value.map((a) => ({
    label: `${a.name} (${a.email})`,
    value: a.id,
  })),
)

function parseIds(raw: string | null | undefined): number[] {
  if (!raw?.trim()) return []
  return raw
    .split(',')
    .map((p) => Number(p.trim()))
    .filter((n) => Number.isFinite(n) && n > 0)
}

async function loadOptions() {
  loading.value = true
  try {
    const [gRes, aRes] = await Promise.all([
      api.get<{ data: SupportGroupRow[] }>('/api/v1/admin/support-groups'),
      api.get<{ data: AgentRow[] }>('/api/v1/admin/agents'),
    ])
    groups.value = Array.isArray(gRes.data.data) ? gRes.data.data : []
    agents.value = Array.isArray(aRes.data.data) ? aRes.data.data : []
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Failed to load software request settings options.'))
  } finally {
    loading.value = false
  }
}

async function save() {
  await ctx.savePartial(
    {
      software_request_notify_group_ids: selectedGroupIds.value.join(',') || null,
      software_request_review_board_user_ids: selectedBoardIds.value.join(',') || null,
    },
    'Software request settings saved.',
  )
  selectedGroupIds.value = parseIds(ctx.form.software_request_notify_group_ids)
  selectedBoardIds.value = parseIds(ctx.form.software_request_review_board_user_ids)
}

onMounted(async () => {
  await ctx.load()
  await loadOptions()
  selectedGroupIds.value = parseIds(ctx.form.software_request_notify_group_ids)
  selectedBoardIds.value = parseIds(ctx.form.software_request_review_board_user_ids)
})
</script>

<template>
  <section class="sw-settings">
    <p class="lede">
      Configure which support groups are emailed when a software request is submitted, and which agents sit on the review board that can approve requests.
    </p>

    <p v-if="loading" class="muted">Loading options…</p>

    <div v-else class="cards">
      <article class="card">
        <header class="card-head">
          <h3>Notification groups</h3>
          <p class="hint">
            Members of selected support groups receive an email on each new submitted request.
            If none are selected, the system defaults to the <code>software-development</code> group when present.
          </p>
        </header>
        <UFormField label="Support groups to notify" name="notify_groups">
          <USelect
            v-model="selectedGroupIds"
            multiple
            :items="groupItems"
            placeholder="Select support groups…"
            class="w-full"
          />
        </UFormField>
      </article>

      <article class="card">
        <header class="card-head">
          <h3>Review board</h3>
          <p class="hint">
            Agents on the review board can approve or reject software requests. Helpdesk admins and agents with
            <strong>Manage software requests</strong> always retain access. If the board is empty, any agent with the
            approve permission can decide.
          </p>
        </header>
        <UFormField label="Review board agents" name="review_board">
          <USelect
            v-model="selectedBoardIds"
            multiple
            :items="agentItems"
            placeholder="Select agents…"
            class="w-full"
          />
        </UFormField>
      </article>
    </div>

    <div class="actions">
      <UButton type="button" color="primary" :loading="ctx.busy" @click="save">Save software request settings</UButton>
    </div>
  </section>
</template>

<style scoped>
.sw-settings { display: flex; flex-direction: column; gap: 1rem; }
.lede { margin: 0; color: #475569; line-height: 1.5; max-width: 48rem; }
.muted { color: #64748b; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); gap: 1rem; align-items: start; }
.card {
  background: #fff; border: none; border-radius: 12px; padding: 1.1rem 1.15rem;
  box-shadow: rgba(145, 158, 171, 0.12) 0 12px 24px -4px, rgba(145, 158, 171, 0.2) 0 0 2px 0;
  display: flex; flex-direction: column; gap: 0.75rem;
}
.card-head h3 { margin: 0 0 0.35rem; font-size: 1rem; }
.hint { margin: 0; color: #64748b; font-size: 0.85rem; line-height: 1.45; }
.actions { display: flex; justify-content: flex-end; }
</style>
