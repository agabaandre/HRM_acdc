<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'

interface AuditRow {
  id: number
  action: string
  user_id: number | null
  staff_id: number | null
  correlation_id: string | null
  created_at: string
  new_values?: Record<string, unknown> | null
}

const rows = ref<AuditRow[]>([])
const meta = ref<{ total: number; current_page: number } | null>(null)
const err = ref<string | null>(null)
const isoJson = ref(false)

async function load() {
  err.value = null
  try {
    const [auditRes, settingsRes] = await Promise.all([
      api.get<{ data: AuditRow[]; meta: { total: number; current_page: number } }>('/api/v1/admin/audit-logs', { params: { per_page: 25 } }),
      api.get<{ data: { iso_json_log_in_stack?: boolean } }>('/api/v1/admin/settings'),
    ])
    const a = auditRes.data
    rows.value = Array.isArray(a.data) ? a.data : []
    meta.value = a.meta ?? null
    isoJson.value = Boolean(settingsRes.data.data?.iso_json_log_in_stack)
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to load audit data.')
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="wrap">
    <section class="card prose">
      <h3>ISO-aligned audit trail</h3>
      <p>
        Security events are written to the <code>helpdesk_audit_logs</code> table (append-only rows: actor, action, timestamps in UTC inside JSON payloads,
        optional <code>correlation_id</code> per HTTP request). This supports evidence collection for controls aligned with
        <a href="https://www.iso.org/standard/54534.html" rel="noopener noreferrer" target="_blank">ISO/IEC 27001:2022</a>
        (information security management) and audit practices described in
        <a href="https://www.iso.org/standard/67397.html" rel="noopener noreferrer" target="_blank">ISO/IEC 27014:2020</a> (governance of information security).
      </p>
      <p>
        Application messages can also be written as <strong>JSON Lines</strong> to <code>storage/logs/helpdesk-iso.jsonl</code> when the
        <code>iso_json</code> channel is included in <code>LOG_STACK</code> (same pattern as structured operational logging in peer CBP apps such as APM). See
        <a href="https://datatracker.ietf.org/doc/html/rfc8259" rel="noopener noreferrer" target="_blank">RFC 8259</a> (JSON) and use UTC
        <a href="https://www.w3.org/TR/NOTE-datetime" rel="noopener noreferrer" target="_blank">ISO 8601</a> timestamps in downstream SIEM parsers.
      </p>
      <p class="pill" :class="{ on: isoJson }">
        JSONLines channel in <code>LOG_STACK</code>:
        <strong>{{ isoJson ? 'enabled' : 'not enabled' }}</strong>
        — set e.g. <code>LOG_STACK=single,iso_json</code> in <code>.env</code> (see <code>config/logging.php</code>).
      </p>
    </section>

    <section class="card">
      <h3>Recent audit events</h3>
      <p v-if="err" class="err">{{ err }}</p>
      <p v-else-if="meta" class="meta">Showing {{ rows.length }} of {{ meta.total }} (page {{ meta.current_page }})</p>
      <div v-if="rows.length" class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>ID</th>
              <th>When (UTC)</th>
              <th>Action</th>
              <th>Staff</th>
              <th>Correlation</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id">
              <td>{{ r.id }}</td>
              <td>{{ r.created_at }}</td>
              <td>{{ r.action }}</td>
              <td>{{ r.staff_id ?? '—' }}</td>
              <td class="mono">{{ r.correlation_id ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else-if="!err" class="muted">No audit rows yet.</p>
    </section>
  </div>
</template>

<style scoped>
.wrap {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1rem 1.1rem;
  background: #fafafa;
}
.card h3 {
  margin: 0 0 0.5rem;
  font-size: 0.98rem;
  color: #1e293b;
}
.prose p {
  font-size: 0.84rem;
  color: #475569;
  line-height: 1.55;
  margin: 0 0 0.65rem;
}
.prose a {
  color: #119a48;
  font-weight: 600;
}
.pill {
  font-size: 0.82rem;
  padding: 0.45rem 0.65rem;
  border-radius: 8px;
  background: #fef3c7;
  color: #92400e;
}
.pill.on {
  background: #dcfce7;
  color: #166534;
}
.meta {
  font-size: 0.8rem;
  color: #64748b;
}
.table-wrap {
  overflow-x: auto;
}
.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}
.tbl th,
.tbl td {
  text-align: left;
  padding: 0.4rem 0.35rem;
  border-bottom: 1px solid #e2e8f0;
}
.mono {
  font-size: 0.72rem;
  max-width: 12rem;
  overflow: hidden;
  text-overflow: ellipsis;
}
.err {
  color: #b91c1c;
  font-weight: 600;
}
.muted {
  color: #64748b;
}
</style>
