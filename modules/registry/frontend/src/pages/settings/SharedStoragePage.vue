<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { notifyApiError, toast } from '@/features/toast'
import {
  enableHostSharedStorage,
  fetchSharedStorage,
  migrateSharedStorage,
  purgeCiSharedStorage,
  type SharedStorageModule,
  type SharedStorageStatus,
} from '@/lib/settingsApi'

const loading = ref(true)
const busy = ref<string | null>(null)
const status = ref<SharedStorageStatus | null>(null)
const lastOutput = ref('')

function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  if (n < 1024 * 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`
  return `${(n / (1024 * 1024 * 1024)).toFixed(2)} GB`
}

async function load() {
  loading.value = true
  try {
    status.value = await fetchSharedStorage()
  } catch (e) {
    notifyApiError(e, 'Could not load shared storage status')
  } finally {
    loading.value = false
  }
}

async function onEnableHost() {
  busy.value = 'enable'
  try {
    const res = await enableHostSharedStorage()
    status.value = res.data
    toast.success(res.message || 'Host storage enabled.', 'Storage')
  } catch (e) {
    notifyApiError(e, 'Could not enable host storage')
  } finally {
    busy.value = null
  }
}

async function onMigrate(module: string) {
  busy.value = `migrate:${module}`
  try {
    const res = await migrateSharedStorage(module)
    status.value = res.data.status
    lastOutput.value = res.data.result.output || ''
    toast.success(res.message || 'Migration finished.', 'Storage')
  } catch (e) {
    notifyApiError(e, 'Migration failed')
  } finally {
    busy.value = null
  }
}

async function onPurge(dryRun: boolean) {
  if (!dryRun) {
    const ok = window.confirm(
      'Archive the legacy CI3 uploads/ folder and symlink it to host storage?\n\nOnly run this after a successful CI migrate. Files are moved to uploads.purged-*, not deleted immediately.',
    )
    if (!ok) return
  }
  busy.value = dryRun ? 'purge-dry' : 'purge'
  try {
    const res = await purgeCiSharedStorage(dryRun)
    status.value = res.data.status
    lastOutput.value = res.data.result.output || ''
    toast.success(res.message || (dryRun ? 'Dry-run OK.' : 'Legacy CI uploads archived.'), 'Storage')
  } catch (e) {
    notifyApiError(e, 'Purge failed')
  } finally {
    busy.value = null
  }
}

function moduleRow(m: SharedStorageModule) {
  return m
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="shared-storage-settings">
    <div class="d-flex flex-wrap align-center justify-space-between ga-3 mb-4">
      <CbpPageHeading
        title="Shared storage"
        subtitle="Keep uploads outside the git repo under /var/staffdata. Migrate from CI3 and APM, then optionally archive legacy uploads/."
      />
      <div class="d-flex flex-wrap ga-2">
        <RouterLink to="/settings" style="text-decoration: none">
          <v-btn variant="outlined" size="small">← Settings</v-btn>
        </RouterLink>
        <v-btn variant="tonal" size="small" :loading="loading" @click="load">Refresh</v-btn>
      </div>
    </div>

    <div v-if="loading" class="text-medium-emphasis py-6">Loading…</div>

    <template v-else-if="status">
      <v-alert
        :type="status.using_host_storage ? 'success' : 'warning'"
        variant="tonal"
        density="comfortable"
        class="mb-4"
      >
        <div class="font-weight-medium">
          {{ status.using_host_storage ? 'Host storage is active' : 'Still using in-repo paths' }}
        </div>
        <div class="text-body-2 mt-1">
          Site <code>{{ status.site_id }}</code> · data root
          <code>{{ status.data_root }}</code>
        </div>
      </v-alert>

      <div class="d-flex flex-wrap ga-2 mb-4">
        <v-btn
          color="primary"
          variant="flat"
          :loading="busy === 'enable'"
          :disabled="!!busy"
          @click="onEnableHost"
        >
          Enable host storage in .env
        </v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          :loading="busy === 'migrate:all'"
          :disabled="!!busy"
          @click="onMigrate('all')"
        >
          Migrate all modules
        </v-btn>
      </div>

      <v-table density="comfortable" class="mb-4">
        <thead>
          <tr>
            <th>Module</th>
            <th>Legacy (in repo)</th>
            <th>Host (outside git)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="raw in status.modules" :key="raw.key">
            <td>
              <div class="font-weight-medium">{{ moduleRow(raw).label }}</div>
              <div class="text-caption text-medium-emphasis">
                <code>{{ raw.env_var }}</code>
                <v-chip
                  v-if="raw.needs_migration"
                  size="x-small"
                  color="warning"
                  variant="tonal"
                  class="ms-1"
                >
                  needs migrate
                </v-chip>
                <v-chip
                  v-if="raw.legacy_is_symlink"
                  size="x-small"
                  color="success"
                  variant="tonal"
                  class="ms-1"
                >
                  symlinked
                </v-chip>
              </div>
            </td>
            <td>
              <div class="text-caption text-break">{{ raw.legacy_path }}</div>
              <div class="text-body-2">
                {{ raw.legacy_files.toLocaleString() }} files · {{ formatBytes(raw.legacy_bytes) }}
              </div>
            </td>
            <td>
              <div class="text-caption text-break">{{ raw.host_path }}</div>
              <div class="text-body-2">
                {{ raw.host_files.toLocaleString() }} files · {{ formatBytes(raw.host_bytes) }}
              </div>
            </td>
            <td class="text-end">
              <v-btn
                size="small"
                variant="tonal"
                :loading="busy === `migrate:${raw.key}`"
                :disabled="!!busy"
                @click="onMigrate(raw.key)"
              >
                Migrate
              </v-btn>
            </td>
          </tr>
        </tbody>
      </v-table>

      <v-card variant="outlined" class="mb-4">
        <v-card-title class="text-subtitle-1">Archive legacy CI3 uploads/</v-card-title>
        <v-card-text>
          <p class="text-body-2 mb-3">
            After CI migrate succeeds, archive the in-repo <code>uploads/</code> folder and point
            <code>uploads</code> at the host <code>ci/</code> tree. Originals are moved to
            <code>uploads.purged-*</code> (not hard-deleted).
          </p>
          <div class="d-flex flex-wrap ga-2">
            <v-btn
              variant="tonal"
              :loading="busy === 'purge-dry'"
              :disabled="!!busy || !status.modules.some((m) => m.key === 'ci' && m.can_purge_legacy)"
              @click="onPurge(true)"
            >
              Dry-run purge
            </v-btn>
            <v-btn
              color="error"
              variant="flat"
              :loading="busy === 'purge'"
              :disabled="!!busy || !status.modules.some((m) => m.key === 'ci' && m.can_purge_legacy)"
              @click="onPurge(false)"
            >
              Archive CI uploads + symlink
            </v-btn>
          </div>
        </v-card-text>
      </v-card>

      <v-card v-if="lastOutput" variant="outlined">
        <v-card-title class="text-subtitle-1">Last command output</v-card-title>
        <v-card-text>
          <pre class="shared-storage-log">{{ lastOutput }}</pre>
        </v-card-text>
      </v-card>
    </template>
  </div>
</template>

<style scoped>
.shared-storage-log {
  margin: 0;
  max-height: 280px;
  overflow: auto;
  font-size: 0.78rem;
  line-height: 1.4;
  white-space: pre-wrap;
  word-break: break-word;
}
.text-break {
  word-break: break-all;
}
</style>
