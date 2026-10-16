<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import {
  createOAuthClient,
  fetchOAuthClients,
  revokeOAuthClient,
  updateOAuthClient,
  type OAuthClientRow,
} from '@/lib/authAdminApi'

interface OAuthClientForm {
  name: string
  redirectUris: string[]
  public: boolean
}

function defaultForm(): OAuthClientForm {
  return {
    name: '',
    redirectUris: [''],
    public: true,
  }
}

const loading = ref(false)
const saving = ref(false)
const revokingId = ref<string | null>(null)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const rows = ref<OAuthClientRow[]>([])
const showDialog = ref(false)
const editingClient = ref<OAuthClientRow | null>(null)
const form = ref<OAuthClientForm>(defaultForm())
const createdClient = ref<OAuthClientRow | null>(null)

const clientCount = computed(() => rows.value.length)
const dialogTitle = computed(() => (editingClient.value ? 'Edit OAuth client' : 'Create OAuth client'))
const canSubmit = computed(
  () =>
    form.value.name.trim().length > 0 &&
    form.value.redirectUris.some((uri) => uri.trim().length > 0),
)

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
  editingClient.value = null
  form.value = defaultForm()
  error.value = null
  showDialog.value = true
}

function openEditDialog(row: OAuthClientRow) {
  editingClient.value = row
  form.value = {
    name: row.name,
    redirectUris: row.redirect_uris.length ? [...row.redirect_uris] : [''],
    public: row.public,
  }
  error.value = null
  showDialog.value = true
}

function closeDialog() {
  showDialog.value = false
  editingClient.value = null
  form.value = defaultForm()
}

function addRedirectUri() {
  form.value.redirectUris.push('')
}

function removeRedirectUri(index: number) {
  if (form.value.redirectUris.length <= 1) {
    form.value.redirectUris[0] = ''
    return
  }
  form.value.redirectUris.splice(index, 1)
}

function formatTimestamp(value: string | null | undefined): string {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return parsed.toLocaleString()
}

async function submitForm() {
  if (!canSubmit.value) {
    error.value = 'Enter a client name and at least one redirect URL.'
    return
  }

  saving.value = true
  error.value = null
  success.value = null

  const redirectUris = form.value.redirectUris.map((uri) => uri.trim()).filter(Boolean)

  try {
    if (editingClient.value) {
      const res = await updateOAuthClient(editingClient.value.id, {
        name: form.value.name.trim(),
        redirect_uris: redirectUris,
      })
      success.value = res.message || 'OAuth client updated.'
      if (createdClient.value?.id === editingClient.value.id) {
        createdClient.value = { ...createdClient.value, ...res.data, plain_secret: createdClient.value.plain_secret }
      }
    } else {
      const res = await createOAuthClient({
        name: form.value.name.trim(),
        redirect_uris: redirectUris,
        public: form.value.public,
      })
      createdClient.value = res.data
      success.value = res.data.public
        ? 'OAuth client created.'
        : 'OAuth client created. Copy the secret now because it will not be shown again.'
    }

    closeDialog()
    await load()
  } catch (e) {
    error.value = apiErrorMessage(
      e,
      editingClient.value ? 'Could not update OAuth client' : 'Could not create OAuth client',
    )
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
    <v-alert v-if="error && !showDialog" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <v-card
      v-if="createdClient"
      :color="createdClient.plain_secret ? 'warning' : undefined"
      :variant="createdClient.plain_secret ? 'tonal' : 'outlined'"
      class="mb-4"
    >
      <v-card-title>{{ createdClient.plain_secret ? 'Copy these credentials now' : 'Client created' }}</v-card-title>
      <v-card-text>
        <div class="mb-2">
          <strong>{{ createdClient.name }}</strong> is ready to use.
        </div>
        <div class="mb-3">
          <div class="text-caption text-medium-emphasis">Client ID</div>
          <pre class="oauth-value">{{ createdClient.id }}</pre>
        </div>
        <div v-if="createdClient.redirect_uris?.length" class="mb-3">
          <div class="text-caption text-medium-emphasis">Redirect URLs</div>
          <div v-for="uri in createdClient.redirect_uris" :key="uri" class="text-body-2">{{ uri }}</div>
        </div>
        <template v-if="createdClient.plain_secret">
          <div class="mb-2">
            The secret will not be shown again after this page is refreshed.
          </div>
          <div class="text-caption text-medium-emphasis">Client secret</div>
          <pre class="oauth-value">{{ createdClient.plain_secret }}</pre>
        </template>
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
            Clients can register multiple redirect URLs. Use public clients for PKCE-based browser or mobile apps.
            Use confidential clients when the integrator can keep a client secret safely on the server side.
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
              <th>Client ID</th>
              <th>Type</th>
              <th>Redirect URLs</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>{{ row.name }}</td>
              <td class="text-no-wrap"><code>{{ row.id }}</code></td>
              <td>
                <v-chip size="small" :color="row.public ? 'info' : 'success'" variant="tonal">
                  {{ row.public ? 'Public' : 'Confidential' }}
                </v-chip>
              </td>
              <td>
                <div v-for="uri in row.redirect_uris" :key="uri" class="text-body-2 oauth-uri">
                  {{ uri }}
                </div>
                <div v-if="!row.redirect_uris.length" class="text-medium-emphasis">—</div>
              </td>
              <td class="text-no-wrap">{{ formatTimestamp(row.created_at) }}</td>
              <td class="text-no-wrap">
                <v-btn size="x-small" variant="tonal" class="me-1" @click="openEditDialog(row)">
                  Edit
                </v-btn>
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
              <td colspan="6" class="text-medium-emphasis">No OAuth clients.</td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="showDialog" max-width="760" persistent>
      <v-card>
        <v-card-title>{{ dialogTitle }}</v-card-title>
        <v-card-text>
          <v-alert v-if="error && showDialog" type="error" variant="tonal" class="mb-3" density="compact">
            {{ error }}
          </v-alert>
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="form.name"
                label="Client name"
                density="compact"
                placeholder="Helpdesk Web"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12">
              <div class="d-flex align-center justify-space-between mb-2">
                <div>
                  <div class="text-subtitle-2">Redirect URLs</div>
                  <div class="text-caption text-medium-emphasis">
                    Add every allowed callback URL for this client.
                  </div>
                </div>
                <v-btn size="small" variant="text" @click="addRedirectUri">
                  <i class="fa-solid fa-plus me-1" aria-hidden="true" />
                  Add URL
                </v-btn>
              </div>
              <div
                v-for="(_uri, index) in form.redirectUris"
                :key="index"
                class="d-flex align-center ga-2 mb-2"
              >
                <v-text-field
                  v-model="form.redirectUris[index]"
                  :label="`Redirect URL ${index + 1}`"
                  density="compact"
                  placeholder="https://app.example.test/oauth/callback"
                  hide-details="auto"
                  class="flex-grow-1"
                />
                <v-btn
                  icon
                  variant="text"
                  size="small"
                  :disabled="form.redirectUris.length === 1 && !form.redirectUris[0]"
                  @click="removeRedirectUri(index)"
                >
                  <i class="fa-solid fa-trash" aria-hidden="true" />
                </v-btn>
              </div>
            </v-col>
            <v-col v-if="!editingClient" cols="12">
              <v-checkbox
                v-model="form.public"
                label="Public client (PKCE / no client secret)"
                hide-details
              />
            </v-col>
            <v-col v-else cols="12">
              <div class="text-caption text-medium-emphasis">
                Client type cannot be changed after creation
                ({{ editingClient.public ? 'public' : 'confidential' }}).
              </div>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="saving" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" :disabled="!canSubmit" @click="submitForm">
            {{ editingClient ? 'Save changes' : 'Create client' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.oauth-value {
  margin: 0;
  padding: 0.75rem;
  overflow-x: auto;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.08);
  white-space: pre-wrap;
  word-break: break-word;
}

.oauth-uri {
  word-break: break-all;
}
</style>
