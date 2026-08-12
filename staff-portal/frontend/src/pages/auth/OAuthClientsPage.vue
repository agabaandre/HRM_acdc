<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { createOAuthClient, fetchOAuthClients, revokeOAuthClient, type OAuthClientRow } from '@/lib/authAdminApi'

interface OAuthClientForm {
  name: string
  redirectUris: string
  public: boolean
}

function defaultForm(): OAuthClientForm {
  return {
    name: '',
    redirectUris: '',
    public: true,
  }
}

const loading = ref(false)
const saving = ref(false)
const revokingId = ref<string | null>(null)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const rows = ref<OAuthClientRow[]>([])
const showCreateDialog = ref(false)
const form = ref<OAuthClientForm>(defaultForm())
const createdClient = ref<OAuthClientRow | null>(null)

const clientCount = computed(() => rows.value.length)

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await fetchOAuthClients()
    rows.value = res.data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load OAuth clients')
  } finally {
    loading.value = false
  }
}

function openCreateDialog() {
  error.value = null
  showCreateDialog.value = true
}

function closeCreateDialog() {
  showCreateDialog.value = false
  form.value = defaultForm()
}

function formatTimestamp(value: string | null | undefined): string {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return parsed.toLocaleString()
}

async function submitCreate() {
  saving.value = true
  error.value = null
  success.value = null

  try {
    const res = await createOAuthClient({
      name: form.value.name.trim(),
      redirect_uris: form.value.redirectUris,
      public: form.value.public,
    })

    createdClient.value = res.data
    success.value = res.data.public
      ? 'OAuth client created.'
      : 'OAuth client created. Copy the secret now because it will not be shown again.'

    closeCreateDialog()
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not create OAuth client')
  } finally {
    saving.value = false
  }
}

async function revokeClient(row: OAuthClientRow) {
  if (!window.confirm(`Revoke OAuth client "${row.name}"? Existing tokens for this client will stop working.`)) return

  revokingId.value = row.id
  error.value = null
  success.value = null

  try {
    const res = await revokeOAuthClient(row.id)
    success.value = res.message || 'OAuth client revoked.'
    if (createdClient.value?.id === row.id) {
      createdClient.value = null
    }
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not revoke OAuth client')
  } finally {
    revokingId.value = null
  }
}

onMounted(() => void load())
</script>

<template>
  <div>
    <PortalPageChrome title="OAuth clients" lede="Passport clients for trusted internal apps and integrations.">
      <template #actions>
        <v-btn color="primary" @click="openCreateDialog">New client</v-btn>
        <RouterLink to="/auth/users" style="text-decoration:none">
          <v-btn size="small" variant="outlined">Users</v-btn>
        </RouterLink>
        <RouterLink to="/auth/audit-logs" style="text-decoration:none">
          <v-btn size="small" variant="outlined">Audit logs</v-btn>
        </RouterLink>
      </template>
    </PortalPageChrome>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <v-card
      v-if="createdClient && createdClient.plain_secret"
      color="warning"
      variant="tonal"
      class="mb-4"
    >
      <v-card-title>Copy this secret now</v-card-title>
      <v-card-text>
        <div class="mb-2">
          The secret for <strong>{{ createdClient.name }}</strong> will not be shown again after this page is refreshed.
        </div>
        <pre class="oauth-secret">{{ createdClient.plain_secret }}</pre>
      </v-card-text>
    </v-card>

    <v-row class="mb-1">
      <v-col cols="12" md="4">
        <v-card variant="outlined">
          <v-card-text>
            <div class="text-caption text-medium-emphasis">Active clients</div>
            <div class="text-h5">{{ clientCount }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="8">
        <v-card variant="outlined">
          <v-card-text class="text-body-2">
            Use public clients for PKCE-based browser or mobile apps. Use confidential clients when the integrator can keep a
            client secret safely on the server side.
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card variant="outlined">
      <v-card-text>
        <div v-if="loading" class="text-medium-emphasis mb-3">Loading…</div>

        <v-table density="compact">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Redirect URIs</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row.name }}</td>
              <td>
                <v-chip size="small" :color="row.public ? 'info' : 'success'" variant="tonal">
                  {{ row.public ? 'Public' : 'Confidential' }}
                </v-chip>
              </td>
              <td>
                <div v-for="uri in row.redirect_uris" :key="uri" class="text-body-2">
                  {{ uri }}
                </div>
                <div v-if="!row.redirect_uris.length" class="text-medium-emphasis">—</div>
              </td>
              <td class="text-no-wrap">{{ formatTimestamp(row.created_at) }}</td>
              <td class="text-no-wrap">
                <v-btn
                  size="x-small"
                  variant="tonal"
                  color="error"
                  :loading="revokingId === row.id"
                  @click="revokeClient(row)"
                >
                  Revoke
                </v-btn>
              </td>
            </tr>
            <tr v-if="!loading && !rows.length">
              <td colspan="5" class="text-medium-emphasis">No OAuth clients.</td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showCreateDialog" max-width="760">
      <v-card>
        <v-card-title>Create OAuth client</v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="form.name"
                label="Client name"
                density="compact"
                placeholder="Helpdesk Web"
              />
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="form.redirectUris"
                label="Redirect URIs"
                rows="4"
                auto-grow
                placeholder="One URI per line"
                hint="Paste one callback URI per line."
                persistent-hint
              />
            </v-col>
            <v-col cols="12">
              <v-checkbox
                v-model="form.public"
                label="Public client (PKCE / no client secret)"
                hide-details
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="saving" @click="closeCreateDialog">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" @click="submitCreate">Create client</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.oauth-secret {
  margin: 0;
  padding: 0.75rem;
  overflow-x: auto;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.08);
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
