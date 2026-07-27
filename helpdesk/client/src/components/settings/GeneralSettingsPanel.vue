<script setup lang="ts">
import { computed, onMounted, ref } from "vue"
import { api } from "../../lib/api"
import { useInjectedHelpdeskAdminSettings } from "../../composables/useHelpdeskAdminSettings"
import { isCheckboxChecked, type CheckboxValue } from "../../lib/helpdeskForm"
import { notifyError, notifySuccess, notifyWarning } from "../../lib/notify"

interface DivisionRow {
  id: number
  name: string
  short_name?: string | null
  directorate_id?: number | null
}

interface CandidateRow {
  staff_id: number
  name: string
  work_email: string | null
  duty_station_name: string | null
  division_id: number
  division_name: string
  has_user: boolean
  current_role: string | null
  is_designated_agent: boolean
  last_synced_at?: string | null
}

const ctx = useInjectedHelpdeskAdminSettings()

const divisions = ref<DivisionRow[]>([])
const divisionsErr = ref<string | null>(null)
const divisionsLoading = ref(false)
const divisionSearch = ref("")

const candidates = ref<CandidateRow[]>([])
const candidatesLoading = ref(false)
const candidatesErr = ref<string | null>(null)
const candidatesMessage = ref<string | null>(null)
const candidatesLoaded = ref(false)
const candidateSearch = ref("")
const onlyMarked = ref(false)
const busyStaffId = ref<number | null>(null)
const cleanupBusy = ref(false)
const cleanupPreviewBusy = ref(false)
const cleanupCount = ref<number | null>(null)
const cleanupPreviewMsg = ref<string | null>(null)

async function previewEmailCleanup() {
  cleanupPreviewBusy.value = true
  cleanupPreviewMsg.value = null
  try {
    const { data } = await api.get<{ data: { count: number } }>('/api/v1/admin/email-ticket-cleanup', {
      params: { unassigned_only: true, source_email_only: true, open_only: true },
    })
    cleanupCount.value = Number(data.data?.count ?? 0)
    cleanupPreviewMsg.value = `${cleanupCount.value} open unassigned email ticket(s) match.`
  } catch (e: unknown) {
    cleanupCount.value = null
    notifyError((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Preview failed')
  } finally {
    cleanupPreviewBusy.value = false
  }
}

async function runEmailCleanup() {
  if (cleanupCount.value == null) {
    await previewEmailCleanup()
  }
  const n = cleanupCount.value ?? 0
  if (n < 1) {
    notifyWarning('Nothing to delete.')
    return
  }
  if (!window.confirm(`Permanently delete ${n} open unassigned email ticket(s)? This cannot be undone.`)) {
    return
  }
  cleanupBusy.value = true
  try {
    const { data } = await api.post<{ message?: string; data?: { deleted: number } }>(
      '/api/v1/admin/email-ticket-cleanup',
      {
        confirm: true,
        unassigned_only: true,
        source_email_only: true,
        open_only: true,
        limit: 2000,
      },
    )
    notifySuccess(data.message ?? `Deleted ${data.data?.deleted ?? 0} ticket(s).`)
    cleanupCount.value = 0
    cleanupPreviewMsg.value = 'Cleanup complete.'
  } catch (e: unknown) {
    notifyError((e as { response?: { data?: { message?: string } } })?.response?.data?.message ?? 'Cleanup failed')
  } finally {
    cleanupBusy.value = false
  }
}

function parseDivisionCsv(csv: string): number[] {
  return csv
    .split(",")
    .map((s) => parseInt(s.trim(), 10))
    .filter((n) => !Number.isNaN(n) && n > 0)
}

const selectedDivisionIds = computed<number[]>({
  get() {
    return parseDivisionCsv(ctx.form.default_agent_division_ids)
  },
  set(ids: number[]) {
    const uniq = [...new Set(ids.filter((n) => n > 0))].sort((a, b) => a - b)
    ctx.form.default_agent_division_ids = uniq.length ? uniq.join(",") : ""
  },
})

const divisionOptions = computed(() => {
  const selected = parseDivisionCsv(ctx.form.default_agent_division_ids)
  const byId = new Map<number, DivisionRow>()
  for (const d of divisions.value) {
    if (d.id > 0) {
      byId.set(d.id, d)
    }
  }
  for (const id of selected) {
    if (!byId.has(id)) {
      byId.set(id, {
        id,
        name: `Division ${id} (not in directory)`,
        short_name: null,
        directorate_id: null,
      })
    }
  }
  return [...byId.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const filteredDivisionOptions = computed(() => {
  const q = divisionSearch.value.trim().toLowerCase()
  if (!q) {
    return divisionOptions.value
  }
  return divisionOptions.value.filter((d) => {
    const idStr = String(d.id)
    const name = (d.name || "").toLowerCase()
    const short = (d.short_name || "").toLowerCase()
    return name.includes(q) || short.includes(q) || idStr.includes(q)
  })
})

const selectionSummaryCsv = computed(() => {
  const ids = selectedDivisionIds.value
  return ids.length ? ids.join(", ") : ""
})

function divisionLabel(d: DivisionRow): string {
  const short = d.short_name ? ` (${d.short_name})` : ""
  return `${d.name}${short}`
}

function isDivisionSelected(id: number): boolean {
  return selectedDivisionIds.value.includes(id)
}

function toggleDivision(id: number, value: CheckboxValue) {
  const checked = isCheckboxChecked(value)
  const current = [...selectedDivisionIds.value]
  if (checked) {
    if (!current.includes(id)) {
      current.push(id)
    }
  } else {
    const i = current.indexOf(id)
    if (i >= 0) {
      current.splice(i, 1)
    }
  }
  selectedDivisionIds.value = current
}

function selectAllFiltered() {
  const set = new Set(selectedDivisionIds.value)
  for (const d of filteredDivisionOptions.value) {
    if (d.id > 0) {
      set.add(d.id)
    }
  }
  selectedDivisionIds.value = [...set]
}

function clearAllSelections() {
  selectedDivisionIds.value = []
}

async function loadDivisions() {
  divisionsLoading.value = true
  divisionsErr.value = null
  try {
    const { data } = await api.get<{ data: { divisions: DivisionRow[] } }>("/api/v1/reference-data")
    const raw = data.data?.divisions ?? []
    divisions.value = raw.filter((d) => d.id > 0 && d.name?.trim())
  } catch {
    divisionsErr.value =
      "Could not load divisions from the Staff directory. Check Staff API credentials under Integrations, run reference sync, or use manual IDs in the section below."
    divisions.value = []
  } finally {
    divisionsLoading.value = false
  }
}

onMounted(() => {
  void loadDivisions()
})

async function saveGeneral() {
  await ctx.savePartial(
    {
      branding_primary_hex: ctx.form.branding_primary_hex || null,
      branding_secondary_hex: ctx.form.branding_secondary_hex || null,
      default_agent_division_ids: ctx.form.default_agent_division_ids.trim() || null,
      require_resolution_confirmation: ctx.form.require_resolution_confirmation,
      requester_unsatisfied_follow_up_enabled: ctx.form.requester_unsatisfied_follow_up_enabled,
      screen_agent_leaderboard_tickets_weight: ctx.form.screen_agent_leaderboard_tickets_weight,
      screen_agent_leaderboard_response_weight: ctx.form.screen_agent_leaderboard_response_weight,
      screen_duty_station_items_per_page: ctx.form.screen_duty_station_items_per_page,
      screen_category_items_per_page: ctx.form.screen_category_items_per_page,
      screen_list_slider_interval_seconds: ctx.form.screen_list_slider_interval_seconds,
      screen_support_group_slider_interval_seconds: ctx.form.screen_support_group_slider_interval_seconds,
      agent_monthly_report_enabled: ctx.form.agent_monthly_report_enabled,
      agent_monthly_report_email_enabled: ctx.form.agent_monthly_report_email_enabled,
      agent_monthly_report_retention_months: ctx.form.agent_monthly_report_retention_months,
      resolved_auto_close_days: ctx.form.resolved_auto_close_days,
      agent_open_ticket_reminder_enabled: ctx.form.agent_open_ticket_reminder_enabled,
      license_expiry_alert_enabled: ctx.form.license_expiry_alert_enabled,
      license_expiry_alert_interval_days: ctx.form.license_expiry_alert_interval_days,
      show_issue_category_on_request_form: ctx.form.show_issue_category_on_request_form,
      show_category_ai_description_on_request_form: ctx.form.show_category_ai_description_on_request_form,
      assign_agent_created_tickets_to_creator: ctx.form.assign_agent_created_tickets_to_creator,
      email_ticket_intake_enabled: ctx.form.email_ticket_intake_enabled,
    },
    "General settings saved.",
  )
}

const filteredCandidates = computed<CandidateRow[]>(() => {
  const q = candidateSearch.value.trim().toLowerCase()
  return candidates.value.filter((c) => {
    if (onlyMarked.value && !c.is_designated_agent) {
      return false
    }
    if (q === "") {
      return true
    }
    const hay = `${c.name} ${c.work_email ?? ""} ${c.division_name} ${c.staff_id}`.toLowerCase()
    return hay.includes(q)
  })
})

const markedCount = computed(() => candidates.value.filter((c) => c.is_designated_agent).length)

async function loadCandidates() {
  candidatesLoading.value = true
  candidatesErr.value = null
  candidatesMessage.value = null
  try {
    const { data } = await api.get<{
      data: { candidates: CandidateRow[]; division_ids: number[] }
      meta?: { message?: string }
    }>("/api/v1/admin/agents/division-candidates")
    candidates.value = Array.isArray(data.data?.candidates) ? data.data.candidates : []
    candidatesMessage.value = data.meta?.message ?? null
    candidatesLoaded.value = true
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    candidatesErr.value = msg || (e instanceof Error ? e.message : "Failed to load division staff.")
    notifyWarning(candidatesErr.value)
    candidates.value = []
  } finally {
    candidatesLoading.value = false
  }
}

async function designateAgent(c: CandidateRow) {
  if (!c.work_email) {
    notifyWarning(`${c.name} has no work email in the directory — cannot designate.`)
    return
  }
  busyStaffId.value = c.staff_id
  try {
    await api.post("/api/v1/admin/agents/designate", {
      staff_id: c.staff_id,
      work_email: c.work_email,
      name: c.name,
      division_id: c.division_id || null,
      duty_station: c.duty_station_name || null,
    })
    c.is_designated_agent = true
    c.has_user = true
    if (!c.current_role || c.current_role === 'user') {
      c.current_role = 'agent'
    }
    notifySuccess(`${c.name} marked as agent.`)
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    notifyError(msg || (e instanceof Error ? e.message : "Failed to mark as agent."))
  } finally {
    busyStaffId.value = null
  }
}

async function undesignateAgent(c: CandidateRow) {
  busyStaffId.value = c.staff_id
  try {
    await api.delete(`/api/v1/admin/agents/designate/${c.staff_id}`)
    c.is_designated_agent = false
    if (c.current_role === "agent") {
      c.current_role = "user"
    }
    notifySuccess(`${c.name} unmarked.`)
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    notifyError(msg || (e instanceof Error ? e.message : "Failed to unmark agent."))
  } finally {
    busyStaffId.value = null
  }
}

function roleLabel(c: CandidateRow): string {
  if (!c.has_user) {
    return "Not signed in yet"
  }
  switch (c.current_role) {
    case "admin":
      return "Admin"
    case "agent":
      return "Agent"
    case "supervisor":
      return "Supervisor"
    case "auditor":
      return "Auditor"
    case "user":
    default:
      return "User"
  }
}
</script>

<template>
  <section class="general-panel" aria-labelledby="general-heading">
    <header class="general-hero">
      <div>
        <h2 id="general-heading">General settings</h2>
        <p class="hero-lede">
          Branding, lobby screen recognition, requester follow-up on closed tickets, and how staff become Helpdesk agents.
        </p>
      </div>
    </header>

    <div class="settings-grid">
      <article class="settings-card settings-card--branding">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">🎨</span>
          <div>
            <h3>Branding</h3>
            <p class="card-lede">Colours used across the Service Desk portal.</p>
          </div>
        </header>
        <div class="color-grid">
          <UColorInput
            v-model="ctx.form.branding_primary_hex"
            label="Primary colour"
            placeholder="#0d7a3a"
          />
          <UColorInput
            v-model="ctx.form.branding_secondary_hex"
            label="Accent gold"
            placeholder="#c9a227"
          />
        </div>
      </article>

      <article class="settings-card settings-card--requester">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">💬</span>
          <div>
            <h3>Requester follow-up</h3>
            <p class="card-lede">
              When a requester comments on a closed ticket, they can reopen it if unsatisfied and the assigned agent is emailed.
            </p>
          </div>
        </header>
        <div class="toggle-row">
          <USwitch v-model="ctx.form.requester_unsatisfied_follow_up_enabled" />
          <span class="toggle-copy">
            <strong>Allow reopen via comment &amp; email agent</strong>
            <span class="toggle-hint">
              Enabled by default. Requesters see a “reopen this ticket” option when posting a comment on closed or resolved tickets;
              agents receive the comment in their inbox.
            </span>
          </span>
        </div>
      </article>

      <article class="settings-card">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">🗂️</span>
          <div>
            <h3>Request form categories</h3>
            <p class="card-lede">
              Control whether requesters pick a category, and whether category cards show the AI description helper text.
            </p>
          </div>
        </header>
        <div class="toggle-row">
          <USwitch v-model="ctx.form.show_issue_category_on_request_form" />
          <span class="toggle-copy">
            <strong>Show category on create ticket form</strong>
            <span class="toggle-hint">
              Off by default: requesters select a business unit only; AI assigns the category (and routes by category).
              On: requesters pick business unit, then category under it; AI categorization is skipped.
            </span>
          </span>
        </div>
        <div class="toggle-row">
          <USwitch
            v-model="ctx.form.show_category_ai_description_on_request_form"
            :disabled="!ctx.form.show_issue_category_on_request_form"
          />
          <span class="toggle-copy">
            <strong>Show AI description on category cards</strong>
            <span class="toggle-hint">
              On by default. When category selection is enabled, each card shows the category’s AI description in brackets
              (up to two lines) to help requesters pick the right option.
            </span>
          </span>
        </div>
      </article>

      <article class="settings-card">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">🧭</span>
          <div>
            <h3>Agent ticket assignment</h3>
            <p class="card-lede">
              Control how tickets are assigned when an agent creates one (for themselves or on behalf of a requester).
            </p>
          </div>
        </header>
        <div class="toggle-row">
          <USwitch v-model="ctx.form.assign_agent_created_tickets_to_creator" />
          <span class="toggle-copy">
            <strong>Assign agent-created tickets to the creating agent</strong>
            <span class="toggle-hint">
              On by default. If the creating agent is eligible for the ticket’s category under
              <strong>Settings → Agents</strong> routing, the ticket is assigned to them.
              If they are not eligible (or this is off), normal category routing applies.
            </span>
          </span>
        </div>
      </article>

      <article class="settings-card">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">✉️</span>
          <div>
            <h3>Email ticket intake</h3>
            <p class="card-lede">
              Master switch for creating tickets from Business Unit support mailboxes (Exchange). Per-unit mailbox and intake must also be enabled under Issue categories → Business units.
            </p>
          </div>
        </header>
        <div class="toggle-row">
          <USwitch v-model="ctx.form.email_ticket_intake_enabled" />
          <span class="toggle-copy">
            <strong>Allow email submission of tickets</strong>
            <span class="toggle-hint">
              Off by default. When on, the scheduler polls each unit with intake enabled (e.g. helpdesk@africacdc.org for IT &amp; MIS), creates tickets, categorizes, and assigns.
              Use <strong>Test read</strong> on a Business Unit to verify Graph access without creating tickets.
              New mail keeps only the latest message (reply threads are stripped). If category routing finds no agent, tickets are assigned to supervisors (then admins) by open workload — grant Supervisor under Agents.
            </span>
          </span>
        </div>
        <div class="cleanup-block">
          <h4 class="cleanup-title">Clean up bad email tickets</h4>
          <p class="toggle-hint">
            Deletes open, unassigned tickets created from email intake (up to 2,000 per run). Use after a bad sync imported reply threads.
            This cannot be undone.
          </p>
          <div class="cleanup-actions">
            <UButton color="neutral" variant="outline" :loading="cleanupPreviewBusy" @click="previewEmailCleanup">
              Preview count
            </UButton>
            <UButton color="error" :loading="cleanupBusy" :disabled="cleanupCount === null" @click="runEmailCleanup">
              Delete {{ cleanupCount ?? '…' }} matching tickets
            </UButton>
          </div>
          <p v-if="cleanupPreviewMsg" class="cleanup-msg">{{ cleanupPreviewMsg }}</p>
        </div>
      </article>

      <article class="settings-card settings-card--lifecycle">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">✅</span>
          <div>
            <h3>Ticket lifecycle (ITIL)</h3>
            <p class="card-lede">
              Agents <strong>resolve</strong> tickets when work is complete. Requesters may close when satisfied, or tickets auto-close after the review period.
            </p>
          </div>
        </header>
        <div class="lifecycle-grid">
          <UFormField
            label="Auto-close after (days)"
            name="resolved_auto_close_days"
            description="Days in Resolved status before automatic closure. Set 0 to disable. Requester is emailed when auto-closed."
          >
            <UInput
              v-model.number="ctx.form.resolved_auto_close_days"
              type="number"
              min="0"
              max="90"
              class="w-full"
            />
          </UFormField>
          <div class="toggle-row">
            <USwitch v-model="ctx.form.agent_open_ticket_reminder_enabled" />
            <span class="toggle-copy">
              <strong>Daily open-ticket reminders to agents</strong>
              <span class="toggle-hint">
                Each morning, agents with open / in-progress assignments receive an email summary.
              </span>
            </span>
          </div>
        </div>
      </article>

      <article class="settings-card settings-card--screen">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">📺</span>
          <div>
            <h3>Lobby screen — agent recognition</h3>
            <p class="card-lede">
              Weights for <strong>Agent of the week</strong> and <strong>Agent of the month</strong> on the
              <a href="/staff/helpdesk/screen" target="_blank" rel="noopener">TV / lobby dashboard</a>.
              Scoring blends tickets worked (first response or resolution in the period) with average first-response time.
            </p>
          </div>
        </header>
        <div class="weight-grid">
          <UFormField label="Tickets worked weight (%)" name="screen_agent_leaderboard_tickets_weight">
            <UInput
              v-model.number="ctx.form.screen_agent_leaderboard_tickets_weight"
              type="number"
              min="0"
              max="100"
              class="w-full"
            />
          </UFormField>
          <UFormField label="Avg response time weight (%)" name="screen_agent_leaderboard_response_weight">
            <UInput
              v-model.number="ctx.form.screen_agent_leaderboard_response_weight"
              type="number"
              min="0"
              max="100"
              class="w-full"
            />
          </UFormField>
        </div>
        <p class="hint-tight">
          Faster average response scores higher. Defaults: 60% volume, 40% response. Weights are normalized if they do not sum to 100.
        </p>
        <div class="weight-grid weight-grid--screen-lists">
          <UFormField
            label="Duty stations per page"
            name="screen_duty_station_items_per_page"
            description="Shown on the lobby screen before paging. Extra stations slide automatically."
          >
            <UInput
              v-model.number="ctx.form.screen_duty_station_items_per_page"
              type="number"
              min="1"
              max="20"
              class="w-full"
            />
          </UFormField>
          <UFormField
            label="Categories per page"
            name="screen_category_items_per_page"
            description="Open-by-category rows per slide on the lobby screen."
          >
            <UInput
              v-model.number="ctx.form.screen_category_items_per_page"
              type="number"
              min="1"
              max="20"
              class="w-full"
            />
          </UFormField>
          <UFormField
            label="List slider interval (seconds)"
            name="screen_list_slider_interval_seconds"
            description="How long each duty station / category page stays visible."
          >
            <UInput
              v-model.number="ctx.form.screen_list_slider_interval_seconds"
              type="number"
              min="2"
              max="60"
              class="w-full"
            />
          </UFormField>
          <UFormField
            label="Support group slider interval (seconds)"
            name="screen_support_group_slider_interval_seconds"
            description="How long each agent-of-the-week support group slide stays visible."
          >
            <UInput
              v-model.number="ctx.form.screen_support_group_slider_interval_seconds"
              type="number"
              min="2"
              max="60"
              class="w-full"
            />
          </UFormField>
        </div>
      </article>

      <article class="settings-card settings-card--monthly-reports">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">📊</span>
          <div>
            <h3>Monthly agent reports</h3>
            <p class="card-lede">
              AI-synthesized performance summaries generated at month end, emailed to agents, and stored on the server.
            </p>
          </div>
        </header>
        <div class="toggle-row">
          <USwitch v-model="ctx.form.agent_monthly_report_enabled" />
          <span class="toggle-copy">
            <strong>Enable monthly agent reports</strong>
            <span class="toggle-hint">When enabled, reports are generated on the 1st for the previous month.</span>
          </span>
        </div>
        <div class="toggle-row">
          <USwitch v-model="ctx.form.agent_monthly_report_email_enabled" />
          <span class="toggle-copy">
            <strong>Email reports to agents</strong>
            <span class="toggle-hint">Sends each agent their report after generation.</span>
          </span>
        </div>
        <UFormField label="Retention (months)" name="agent_monthly_report_retention_months" class="retention-field">
          <UInput
            v-model.number="ctx.form.agent_monthly_report_retention_months"
            type="number"
            min="1"
            max="120"
            class="w-full"
          />
        </UFormField>
        <p class="hint-tight">Archived HTML copies are purged after this period (default 12 months).</p>
      </article>

      <article class="settings-card settings-card--license-alerts">
        <header class="card-head">
          <span class="card-icon" aria-hidden="true">🔑</span>
          <div>
            <h3>License expiry alerts</h3>
            <p class="card-lede">
              Email the responsible person and all Helpdesk admins when a license is inside its per-license warning window.
            </p>
          </div>
        </header>
        <div class="toggle-row">
          <USwitch v-model="ctx.form.license_expiry_alert_enabled" />
          <span class="toggle-copy">
            <strong>Enable license expiry alerts</strong>
            <span class="toggle-hint">Runs daily; each license uses its own “warning days before” value.</span>
          </span>
        </div>
        <UFormField
          label="Reminder interval (days)"
          name="license_expiry_alert_interval_days"
          description="How often to re-send while the license remains in the warning/expired window."
        >
          <USelect
            v-model="ctx.form.license_expiry_alert_interval_days"
            :items="[
              { label: 'Every day', value: 1 },
              { label: 'Every 3 days', value: 3 },
              { label: 'Every 7 days', value: 7 },
            ]"
            class="w-full"
          />
        </UFormField>
      </article>
    </div>

    <article class="settings-card settings-card--agents">
      <header class="card-head">
        <span class="card-icon" aria-hidden="true">👥</span>
        <div>
          <h3>Agent onboarding</h3>
          <p class="card-lede">
            Staff in selected divisions become agents on SSO unless they are portal admins. Mark individuals explicitly when needed.
          </p>
        </div>
      </header>

      <div class="field-block">
        <span class="label">Default agent divisions</span>
        <p class="hint-tight">
          Staff users whose <code>division_id</code> matches any selection become Helpdesk <strong>agents</strong> on SSO,
          unless they are portal admins (see admin role mapping).
        </p>
        <p v-if="divisionsLoading" class="muted">Loading divisions…</p>
        <template v-else>
          <p v-if="divisionsErr" class="warn">{{ divisionsErr }}</p>

          <div v-if="divisionOptions.length" class="division-picker">
            <div class="picker-toolbar">
              <UFormField name="divisionSearch" class="search-wrap">
                <UInput
                  v-model="divisionSearch"
                  type="search"
                  icon="i-lucide-search"
                  placeholder="Search by name, short name, or ID…"
                  autocomplete="off"
                  aria-label="Search divisions"
                  class="w-full"
                />
              </UFormField>
              <div class="picker-actions">
                <UButton type="button" color="neutral" variant="outline" size="xs" @click="selectAllFiltered()">Select all shown</UButton>
                <UButton type="button" color="neutral" variant="outline" size="xs" @click="clearAllSelections()">Clear all</UButton>
              </div>
            </div>
            <div class="checks-scroll" role="group" aria-label="Division checkboxes">
              <div v-for="d in filteredDivisionOptions" :key="d.id" class="check-row">
                <UCheckbox
                  :model-value="isDivisionSelected(d.id)"
                  @update:model-value="(value: CheckboxValue) => toggleDivision(d.id, value)"
                >
                  <template #label>
                    <span class="check-row__name">{{ divisionLabel(d) }}</span>
                    <span class="check-row__id">ID {{ d.id }}</span>
                  </template>
                </UCheckbox>
              </div>
            </div>
            <p class="selection-summary" aria-live="polite">
              <template v-if="selectedDivisionIds.length">
                {{ selectedDivisionIds.length }} selected: {{ selectionSummaryCsv }}
              </template>
              <template v-else>None selected.</template>
            </p>
          </div>
          <p v-else class="muted empty-divisions-msg">
            No divisions were returned from the directory. You can still set defaults using manual division IDs below.
          </p>

          <details class="manual-details">
            <summary>Manual division IDs</summary>
            <p class="hint-tight manual-details-hint">
              Comma-separated IDs for edge cases or when the directory list is incomplete.
            </p>
            <UFormField label="Division IDs (comma-separated)" name="default_agent_division_ids">
              <UInput v-model="ctx.form.default_agent_division_ids" type="text" placeholder="21, 34" class="w-full" />
            </UFormField>
          </details>

          <UButton type="button" color="neutral" variant="outline" size="sm" :disabled="divisionsLoading" @click="loadDivisions()">Reload divisions</UButton>
        </template>
      </div>

      <!-- Division agents register -->
      <div class="field-block field-block--agents">
        <div class="agents-head">
          <div>
            <span class="label">Agents from selected divisions</span>
            <p class="hint-tight">
              Pull the current staff list for the divisions selected above and explicitly mark who should
              act as an agent. Marking a person locks the <strong>agent</strong> role across SSO logins, even
              if they later move to a different division. Staff directory data is read live from the Staff
              portal — nothing is duplicated locally.
            </p>
          </div>
          <UButton type="button" color="neutral" variant="outline" size="sm" :disabled="candidatesLoading" :loading="candidatesLoading" @click="loadCandidates()">
            {{ candidatesLoaded ? "Reload from directory" : "View staff in these divisions" }}
          </UButton>
        </div>

        <p v-if="candidatesErr" class="warn">{{ candidatesErr }}</p>
        <p v-else-if="candidatesMessage" class="muted">{{ candidatesMessage }}</p>

        <template v-if="candidatesLoaded && !candidatesErr && candidates.length">
          <div class="cand-toolbar">
            <UFormField name="candidateSearch" class="search-wrap">
              <UInput
                v-model="candidateSearch"
                type="search"
                icon="i-lucide-search"
                placeholder="Search by name, email, division, or staff ID…"
                autocomplete="off"
                aria-label="Search staff"
                class="w-full"
              />
            </UFormField>
            <UCheckbox v-model="onlyMarked" :label="`Only marked (${markedCount})`" class="filter-toggle" />
            <span class="muted cand-meta">{{ filteredCandidates.length }} of {{ candidates.length }} shown</span>
          </div>

          <div class="cand-table-wrap">
            <table class="cand-table">
              <thead>
                <tr>
                  <th>Staff</th>
                  <th>Division</th>
                  <th>Status</th>
                  <th>Designation</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in filteredCandidates" :key="c.staff_id" :class="{ marked: c.is_designated_agent }">
                  <td>
                    <div class="cand-name">{{ c.name }}</div>
                    <div class="cand-sub">
                      <span v-if="c.work_email">{{ c.work_email }}</span>
                      <span v-else class="missing">No work email on directory</span>
                      <span class="dot-sep">·</span>
                      <span>SID {{ c.staff_id }}</span>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-div">{{ c.division_name }}</span>
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="{
                        'badge-role-agent': c.current_role === 'agent',
                        'badge-role-admin': c.current_role === 'admin',
                        'badge-role-other': c.has_user && c.current_role !== 'agent' && c.current_role !== 'admin',
                        'badge-role-none': !c.has_user,
                      }"
                    >{{ roleLabel(c) }}</span>
                  </td>
                  <td>
                    <span v-if="c.is_designated_agent" class="badge badge-marked">Marked agent</span>
                    <span v-else class="badge badge-unmarked">—</span>
                  </td>
                  <td class="cand-actions">
                    <UButton
                      v-if="!c.is_designated_agent"
                      type="button"
                      color="primary"
                      size="xs"
                      :disabled="!c.work_email"
                      :loading="busyStaffId === c.staff_id"
                      @click="designateAgent(c)"
                    >
                      Mark as agent
                    </UButton>
                    <UButton
                      v-else
                      type="button"
                      color="error"
                      variant="soft"
                      size="xs"
                      :loading="busyStaffId === c.staff_id"
                      @click="undesignateAgent(c)"
                    >
                      Unmark
                    </UButton>
                  </td>
                </tr>
                <tr v-if="filteredCandidates.length === 0">
                  <td colspan="5" class="muted center">No staff match the current filter.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <p v-else-if="candidatesLoaded && !candidatesErr && !candidatesMessage" class="muted">
          No staff returned from the directory for the selected divisions.
        </p>
      </div>

      <div class="actions actions--sticky">
        <UButton type="button" color="primary" :loading="ctx.busy" @click="saveGeneral()">
          Save general settings
        </UButton>
      </div>
    </article>
  </section>
</template>

<style scoped>
.general-panel {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.general-hero h2 {
  font-size: 1.35rem;
  margin: 0 0 0.35rem;
  color: var(--cdc-ink, #0c1a12);
}
.hero-lede {
  margin: 0;
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.92rem;
  line-height: 1.5;
  max-width: 42rem;
}
.settings-grid {
  column-count: 3;
  column-gap: 0.75rem;
}
.settings-card {
  break-inside: avoid;
  page-break-inside: avoid;
  margin: 0 0 0.75rem;
  width: 100%;
  display: inline-block;
  vertical-align: top;
  border: 1px solid var(--hd-line);
  border-radius: 4px;
  background: #fff;
  padding: 1rem 1.1rem 1.1rem;
  box-shadow: none;
  box-sizing: border-box;
}
@media (max-width: 1100px) {
  .settings-grid { column-count: 2; }
}
@media (max-width: 720px) {
  .settings-grid { column-count: 1; }
}
.settings-card--agents {
  padding-bottom: 0.5rem;
}
.card-head {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  margin-bottom: 0.85rem;
}
.card-icon {
  font-size: 1.35rem;
  line-height: 1;
  margin-top: 0.1rem;
}
.card-head h3 {
  margin: 0 0 0.2rem;
  font-size: 1rem;
  color: var(--cdc-ink, #0c1a12);
}
.card-lede {
  margin: 0;
  font-size: 0.82rem;
  line-height: 1.45;
  color: #64748b;
}
.color-grid {
  display: grid;
  gap: 0.75rem;
}
.weight-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
}
.retention-field {
  max-width: 12rem;
  margin-top: 0.75rem;
}
.toggle-row {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  padding: 0.85rem 0.9rem;
  border-radius: 4px;
  border: 1px solid var(--hd-line);
  background: #f8fafc;
}
.lifecycle-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.toggle-copy {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.88rem;
  color: #334155;
}
.toggle-hint {
  font-size: 0.8rem;
  font-weight: 400;
  color: #64748b;
  line-height: 1.45;
}
.cleanup-block {
  margin-top: 1rem;
  padding-top: 0.85rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.cleanup-title {
  margin: 0;
  font-size: 0.92rem;
  font-weight: 700;
  color: #334155;
}
.cleanup-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}
.cleanup-msg {
  margin: 0;
  font-size: 0.82rem;
  color: #475569;
}
.field-block--agents {
  margin-top: 0.5rem;
  padding-top: 0.75rem;
  border-top: 1px solid var(--hd-line-subtle);
}
.actions--sticky {
  position: sticky;
  bottom: 0;
  margin-top: 1rem;
  padding: 0.85rem 0 0.25rem;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, #fff 35%);
}
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
  color: var(--cdc-ink, #0c1a12);
}
.panel h3 {
  font-size: 0.95rem;
  margin: 0.75rem 0 0.35rem;
  color: var(--cdc-ink, #0c1a12);
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.5;
}
.hint-tight {
  color: #64748b;
  font-size: 0.8rem;
  margin: 0 0 0.5rem;
  line-height: 1.45;
}
.field-block {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin-bottom: 0.5rem;
}
.label {
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}
.card {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  padding: 1.25rem 1.35rem;
  border-radius: 4px;
  border: 1px solid var(--cdc-line, rgba(12, 26, 18, 0.08));
  background: var(--cdc-white, #fff);
  box-shadow: var(--cdc-shadow, 0 8px 24px rgba(6, 95, 44, 0.08));
}
label {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.82rem;
  font-weight: 600;
  color: #334155;
}
.row {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}
input {
  padding: 0.45rem 0.5rem;
  border: 1px solid var(--hd-line);
  border-radius: 4px;
  font-size: 0.95rem;
}
.division-picker {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-width: 36rem;
}
.picker-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.5rem 0.75rem;
}
.search-wrap {
  flex: 1 1 12rem;
  min-width: 0;
}
.picker-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.checks-scroll {
  max-height: 14rem;
  overflow-y: auto;
  border: 1px solid var(--hd-line);
  border-radius: 4px;
  padding: 0.35rem 0.5rem;
  background: #fff;
}

html.helpdesk-theme-dark .checks-scroll,
html.helpdesk-theme-dark .settings-card {
  background: #1e293b;
}
html.helpdesk-theme-dark .toggle-row {
  background: #0f172a;
  border-color: rgba(148, 163, 184, 0.28);
}
html.helpdesk-theme-dark .toggle-copy {
  color: #e2e8f0;
}
html.helpdesk-theme-dark .toggle-hint,
html.helpdesk-theme-dark .card-lede,
html.helpdesk-theme-dark .hint,
html.helpdesk-theme-dark .hint-tight,
html.helpdesk-theme-dark .muted,
html.helpdesk-theme-dark .cleanup-msg,
html.helpdesk-theme-dark .selection-summary {
  color: #94a3b8;
}
html.helpdesk-theme-dark .card-head h3,
html.helpdesk-theme-dark .panel h2,
html.helpdesk-theme-dark .panel h3,
html.helpdesk-theme-dark .cleanup-title,
html.helpdesk-theme-dark .label,
html.helpdesk-theme-dark .manual-details summary {
  color: #f1f5f9;
}
html.helpdesk-theme-dark .cleanup-block,
html.helpdesk-theme-dark .field-block--agents {
  border-top-color: rgba(148, 163, 184, 0.22);
}
html.helpdesk-theme-dark .actions--sticky {
  background: linear-gradient(180deg, rgba(15, 23, 42, 0) 0%, #0f172a 35%);
}
html.helpdesk-theme-dark .check-row__name {
  color: #e2e8f0 !important;
}
html.helpdesk-theme-dark .check-row__id {
  color: #94a3b8 !important;
}
html.helpdesk-theme-dark .warn {
  background: rgba(251, 191, 36, 0.12);
  border-color: rgba(251, 191, 36, 0.4);
  color: #fde68a;
}
html.helpdesk-theme-dark .btn-unmark {
  background: #0f172a;
  color: #fecaca;
  border-color: rgba(248, 113, 113, 0.45);
}
html.helpdesk-theme-dark .btn-unmark:hover {
  background: rgba(127, 29, 29, 0.35);
}
.check-row {
  padding: 0.2rem 0;
}

.check-row :deep(.hd-v-checkbox) {
  width: 100%;
}

.check-row :deep(.v-selection-control) {
  align-items: flex-start;
  min-height: unset;
}

.check-row :deep(.v-label) {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.25rem 0.5rem;
  width: 100%;
  opacity: 1;
}

.check-row input[type="checkbox"] {
  margin-top: 0.2rem;
  flex-shrink: 0;
}
.check-row__name {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 0.88rem;
  line-height: 1.35;
  color: #0f172a !important;
}
.check-row__id {
  flex-shrink: 0;
  font-size: 0.78rem;
  font-weight: 600;
  color: #64748b !important;
}
.ghost--sm {
  padding: 0.28rem 0.55rem;
  font-size: 0.78rem;
}
.selection-summary {
  margin: 0;
  font-size: 0.8rem;
  color: #475569;
  line-height: 1.4;
  word-break: break-word;
}
.manual-details {
  margin-top: 0.15rem;
  max-width: 36rem;
}
.manual-details summary {
  cursor: pointer;
  font-weight: 600;
  font-size: 0.85rem;
  color: #334155;
}
.manual-details-hint {
  margin-top: 0.35rem;
}
.empty-divisions-msg {
  margin: 0;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
.muted {
  color: #64748b;
  font-size: 0.85rem;
  margin: 0;
}
.warn {
  color: #92400e;
  font-size: 0.8rem;
  margin: 0;
  padding: 0.35rem 0.5rem;
  background: #fffbeb;
  border-radius: 4px;
  border: 1px solid #fcd34d;
}
.ghost {
  align-self: flex-start;
  margin-top: 0.15rem;
  padding: 0.35rem 0.75rem;
  border-radius: 4px;
  border: 1px solid var(--hd-line);
  background: #fff;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
  color: #334155;
}
.ghost:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.actions {
  margin-top: 0.35rem;
}
.primary {
  padding: 0.55rem 1.1rem;
  border-radius: 4px;
  border: none;
  background: linear-gradient(135deg, var(--cdc-green, #0d7a3a), var(--cdc-green-deep, #065f2c));
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
code {
  font-size: 0.85em;
}

/* Division agents register */
.agents-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.85rem;
  flex-wrap: wrap;
}
.agents-head .ghost {
  align-self: flex-start;
  margin-top: 0;
}
.cand-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.6rem 0.85rem;
  margin: 0.65rem 0 0.55rem;
}
.cand-toolbar .search-wrap {
  flex: 1 1 16rem;
}
.filter-toggle {
  font-size: 0.82rem;
  font-weight: 500;
  color: #475569;
}
.cand-meta {
  margin-left: auto;
  font-size: 0.78rem;
}
.cand-table-wrap {
  border: none;
  border-radius: 12px;
  overflow: auto;
  max-height: 22rem;
  background: #fff;
  box-shadow:
    rgba(145, 158, 171, 0.12) 0 12px 24px -4px,
    rgba(145, 158, 171, 0.2) 0 0 2px 0;
}
.cand-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.cand-table th {
  position: sticky;
  top: 0;
  background: #f8fafc;
  text-align: left;
  font-size: 0.8125rem;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0.01em;
  color: #3a4752;
  padding: 0.7rem 0.9rem;
  border-bottom: 1px solid #dfe5ef;
}
.cand-table td {
  padding: 0.7rem 0.9rem;
  border-bottom: 1px solid rgba(223, 229, 239, 0.85);
  vertical-align: middle;
}
.cand-table tr.marked {
  background: linear-gradient(90deg, rgba(13, 122, 58, 0.04) 0%, transparent 100%);
}
.cand-table tr:last-child td {
  border-bottom: none;
}
.cand-name {
  font-weight: 600;
  color: #0f172a;
}
.cand-sub {
  font-size: 0.78rem;
  color: #64748b;
  margin-top: 0.15rem;
}
.cand-sub .missing {
  color: #92400e;
}
.dot-sep {
  margin: 0 0.4rem;
  color: #cbd5e1;
}
.badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  white-space: nowrap;
}
.badge-div {
  background: #eef2ff;
  color: #3730a3;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0;
}
.badge-role-agent {
  background: #dcfce7;
  color: #166534;
}
.badge-role-admin {
  background: #fef3c7;
  color: #92400e;
}
.badge-role-other {
  background: #e0e7ff;
  color: #3730a3;
}
.badge-role-none {
  background: #f1f5f9;
  color: #64748b;
}
.badge-marked {
  background: #0d7a3a;
  color: #fff;
  text-transform: uppercase;
}
.badge-unmarked {
  background: transparent;
  color: #94a3b8;
  font-weight: 500;
}
.cand-actions {
  text-align: right;
}
.btn-mark,
.btn-unmark {
  padding: 0.32rem 0.7rem;
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.8rem;
  cursor: pointer;
  border: 1px solid transparent;
  white-space: nowrap;
}
.btn-mark {
  background: #0d7a3a;
  color: #fff;
  border-color: #0d7a3a;
}
.btn-mark:hover {
  background: #065f2c;
}
.btn-unmark {
  background: #fff;
  color: #b91c1c;
  border-color: #fecaca;
}
.btn-unmark:hover {
  background: #fef2f2;
}
.btn-mark:disabled,
.btn-unmark:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.center {
  text-align: center;
}
</style>

<style>
/* Unscoped dark mode — scoped attribute selectors can miss nested toggle rows */
html.helpdesk-theme-dark .settings-grid .settings-card,
html.helpdesk-theme-dark article.settings-card {
  background: #1e293b !important;
  border-color: rgba(148, 163, 184, 0.28) !important;
  color: #e2e8f0 !important;
}

html.helpdesk-theme-dark .settings-card .toggle-row {
  background: #0f172a !important;
  border-color: rgba(148, 163, 184, 0.32) !important;
}

html.helpdesk-theme-dark .settings-card .toggle-copy,
html.helpdesk-theme-dark .settings-card .toggle-copy strong {
  color: #e2e8f0 !important;
}

html.helpdesk-theme-dark .settings-card .toggle-hint,
html.helpdesk-theme-dark .settings-card .card-lede {
  color: #94a3b8 !important;
}

html.helpdesk-theme-dark .settings-card .card-head h3,
html.helpdesk-theme-dark .settings-card .cleanup-title {
  color: #f1f5f9 !important;
}

html.helpdesk-theme-dark .settings-card .cleanup-block {
  border-top-color: rgba(148, 163, 184, 0.22) !important;
}

html.helpdesk-theme-dark .settings-card .cleanup-msg,
html.helpdesk-theme-dark .settings-card .muted,
html.helpdesk-theme-dark .settings-card .hint-tight {
  color: #94a3b8 !important;
}

html.helpdesk-theme-dark .settings-card .v-field {
  background: #0f172a !important;
  color: #f1f5f9 !important;
}

html.helpdesk-theme-dark .settings-card .v-field__input,
html.helpdesk-theme-dark .settings-card .v-select__selection-text {
  color: #f1f5f9 !important;
}

html.helpdesk-theme-dark .settings-card .hd-v-form-field__label {
  color: #e2e8f0 !important;
}

html.helpdesk-theme-dark .settings-card .checks-scroll {
  background: #0f172a !important;
  border-color: rgba(148, 163, 184, 0.28) !important;
}

html.helpdesk-theme-dark .panel > .actions--sticky {
  background: linear-gradient(180deg, rgba(15, 23, 42, 0) 0%, #0f172a 40%) !important;
}
</style>
