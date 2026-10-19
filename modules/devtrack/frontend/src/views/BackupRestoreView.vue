<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'

type CountPair = { created: number; updated: number }

type ImportSummary = {
  providers: CountPair
  users: CountPair
  clients: CountPair
  links_synced: number
  branding_updated: boolean
  warnings: string[]
}

const exporting = ref(false)
const importing = ref(false)
const file = ref<File[] | File | null>(null)
const encryptionKey = ref('')
const error = ref('')
const message = ref('')
const summary = ref<ImportSummary | null>(null)

const keyDialog = ref(false)
const issuedKey = ref('')
const issuedFilename = ref('')
const pendingPackage = ref<unknown>(null)
const keyCopied = ref(false)

const selectedFile = computed(() => {
  const value = file.value
  if (Array.isArray(value)) return value[0] ?? null
  return value
})

const canRestore = computed(
  () => !!selectedFile.value && encryptionKey.value.trim().length >= 40,
)

function downloadJson(filename: string, data: unknown) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

async function copyIssuedKey() {
  if (!issuedKey.value) return
  try {
    await navigator.clipboard.writeText(issuedKey.value)
    keyCopied.value = true
  } catch {
    error.value = 'Could not copy key — select and copy it manually.'
  }
}

function saveEncryptedFile() {
  if (!pendingPackage.value || !issuedFilename.value) return
  downloadJson(issuedFilename.value, pendingPackage.value)
}

async function downloadPackage() {
  exporting.value = true
  error.value = ''
  message.value = ''
  keyCopied.value = false
  try {
    const res = await api.get('/admin/migration/export')
    const payload = res.data?.data && res.data.encryption_key === undefined ? res.data.data : res.data
    const key = String(payload?.encryption_key || '')
    const filename = String(payload?.filename || `email-server-migration-${Date.now()}.json`)
    const pkg = payload?.package
    if (!key || !pkg) {
      throw new Error('Export response missing encryption key or package.')
    }

    // Show the key first — do not start the file download until the modal is open.
    issuedKey.value = key
    issuedFilename.value = filename
    pendingPackage.value = pkg
    keyDialog.value = true
    message.value =
      'Copy the encryption key below. The package file is not downloaded until you confirm.'

    await nextTick()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not export migration package.')
  } finally {
    exporting.value = false
  }
}

function confirmKeyAndDownload() {
  if (!keyCopied.value) {
    error.value = 'Copy the encryption key before downloading the package.'
    return
  }
  saveEncryptedFile()
  keyDialog.value = false
  message.value =
    'Encrypted package downloaded. Keep the encryption key separate from the file — it is not stored on the server.'
}

async function restorePackage() {
  const selected = selectedFile.value
  if (!selected) {
    error.value = 'Choose a migration JSON file to restore.'
    return
  }
  if (!encryptionKey.value.trim()) {
    error.value = 'Paste the encryption key that was shown when this package was downloaded.'
    return
  }
  if (
    !confirm(
      'Restore this encrypted migration package? Matching users (by email), clients (by slug), and providers (by slug) will be created or updated.',
    )
  ) {
    return
  }

  importing.value = true
  error.value = ''
  message.value = ''
  summary.value = null
  try {
    const body = new FormData()
    body.append('file', selected)
    body.append('encryption_key', encryptionKey.value.trim())
    const res = await api.post('/admin/migration/import', body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    summary.value = res.data.data as ImportSummary
    message.value = res.data.message || 'Migration imported.'
    file.value = null
    encryptionKey.value = ''
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not import migration package.')
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="Backup / Restore"
      subtitle="Export encrypted users, clients, providers, and branding for migration to another server"
    />

    <v-alert type="warning" variant="tonal" class="mb-4" border="start">
      Each download is encrypted with a unique 256-bit key generated for that file only. The key is shown
      once after download — copy it and store it separately. Without the key, the package cannot be restored
      (including by brute force).
    </v-alert>

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4" closable @click:close="message = ''">
      {{ message }}
    </v-alert>
    <v-alert
      v-if="issuedKey"
      type="error"
      variant="tonal"
      class="mb-4"
      border="start"
    >
      <div class="text-subtitle-2 mb-2">Encryption key (not stored on the server — copy it now)</div>
      <code class="d-block text-break mb-3" style="user-select: all">{{ issuedKey }}</code>
      <v-btn size="small" color="error" variant="flat" prepend-icon="mdi-content-copy" class="me-2" @click="copyIssuedKey">
        {{ keyCopied ? 'Copied' : 'Copy key' }}
      </v-btn>
      <v-btn
        v-if="pendingPackage"
        size="small"
        color="primary"
        variant="flat"
        prepend-icon="mdi-download"
        :disabled="!keyCopied"
        @click="confirmKeyAndDownload"
      >
        Download encrypted file
      </v-btn>
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
      {{ error }}
    </v-alert>

    <v-row>
      <v-col cols="12" md="6">
        <ParentCard title="Export">
          <p class="text-medium-emphasis mb-4">
            Download an encrypted JSON package. Credentials inside are sealed with AES-256-GCM using a
            fresh random key. You will need that key on the destination server.
          </p>
          <v-btn
            color="primary"
            prepend-icon="mdi-download"
            :loading="exporting"
            @click="downloadPackage"
          >
            Download encrypted package
          </v-btn>
        </ParentCard>
      </v-col>

      <v-col cols="12" md="6">
        <ParentCard title="Restore">
          <p class="text-medium-emphasis mb-4">
            Upload the encrypted package and paste the encryption key from the source server. Missing users
            are created; existing users and clients matched by email/slug are updated.
          </p>
          <v-file-input
            v-model="file"
            label="Encrypted migration JSON"
            accept=".json,application/json"
            variant="outlined"
            density="comfortable"
            prepend-icon="mdi-file-upload-outline"
            show-size
            clearable
            class="mb-3"
          />
          <v-text-field
            v-model="encryptionKey"
            label="Encryption key"
            variant="outlined"
            density="comfortable"
            autocomplete="off"
            spellcheck="false"
            hint="The one-time key shown when the package was downloaded"
            persistent-hint
            class="mb-4"
          />
          <v-btn
            color="warning"
            prepend-icon="mdi-database-import-outline"
            :loading="importing"
            :disabled="!canRestore"
            @click="restorePackage"
          >
            Restore package
          </v-btn>
        </ParentCard>
      </v-col>
    </v-row>

    <ParentCard v-if="summary" title="Last restore summary" class="mt-4">
      <v-row dense>
        <v-col cols="12" sm="4">
          <div class="text-caption text-medium-emphasis">Providers</div>
          <div>Created {{ summary.providers.created }}, updated {{ summary.providers.updated }}</div>
        </v-col>
        <v-col cols="12" sm="4">
          <div class="text-caption text-medium-emphasis">Users</div>
          <div>Created {{ summary.users.created }}, updated {{ summary.users.updated }}</div>
        </v-col>
        <v-col cols="12" sm="4">
          <div class="text-caption text-medium-emphasis">Clients</div>
          <div>Created {{ summary.clients.created }}, updated {{ summary.clients.updated }}</div>
        </v-col>
        <v-col cols="12" sm="4">
          <div class="text-caption text-medium-emphasis">Links synced</div>
          <div>{{ summary.links_synced }}</div>
        </v-col>
        <v-col cols="12" sm="4">
          <div class="text-caption text-medium-emphasis">Branding</div>
          <div>{{ summary.branding_updated ? 'Updated' : 'Unchanged' }}</div>
        </v-col>
      </v-row>
      <div v-if="summary.warnings?.length" class="mt-4">
        <div class="text-subtitle-2 mb-2">Warnings</div>
        <ul class="text-medium-emphasis">
          <li v-for="(warning, index) in summary.warnings" :key="index">{{ warning }}</li>
        </ul>
      </div>
    </ParentCard>

    <v-dialog v-model="keyDialog" max-width="640" persistent>
      <v-card>
        <v-card-title>Copy encryption key before download</v-card-title>
        <v-card-text>
          <v-alert type="error" variant="tonal" class="mb-4">
            This key is shown once and is not stored on the server. Copy it first — the encrypted
            package file is downloaded only after you confirm.
          </v-alert>
          <v-textarea
            :model-value="issuedKey"
            label="Encryption key"
            variant="outlined"
            readonly
            auto-grow
            rows="2"
            class="font-mono"
          />
        </v-card-text>
        <v-card-actions>
          <v-btn color="primary" variant="tonal" prepend-icon="mdi-content-copy" @click="copyIssuedKey">
            {{ keyCopied ? 'Copied' : 'Copy key' }}
          </v-btn>
          <v-spacer />
          <v-btn
            color="primary"
            variant="flat"
            prepend-icon="mdi-download"
            :disabled="!keyCopied"
            @click="confirmKeyAndDownload"
          >
            Download encrypted file
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
