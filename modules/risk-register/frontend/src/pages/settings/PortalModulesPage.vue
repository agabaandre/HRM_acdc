<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { useAuthStore } from '@/stores/auth'
import {
  fetchPortalModules,
  savePortalModules,
  type PortalModuleRow,
} from '@/lib/settingsApi'

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const modules = ref<PortalModuleRow[]>([])

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await fetchPortalModules()
    modules.value = data.modules
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load portal modules')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    const payload: Record<string, boolean> = {}
    for (const m of modules.value) {
      payload[m.key] = m.enabled
    }
    const data = await savePortalModules(payload)
    modules.value = data.modules
    await auth.fetchMe(true)
    success.value = 'Portal modules saved. Navigation updates immediately.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save portal modules')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PortalPageChrome
      title="Portal modules"
      lede="Turn Staff Portal feature areas on or off for everyone. Payroll is off by default until you enable it."
    >
      <template #actions>
        <v-btn color="primary" :loading="saving" :disabled="loading" @click="save">Save</v-btn>
      </template>
    </PortalPageChrome>

    <v-alert type="info" variant="tonal" density="compact" class="mb-3">
      Disabling a module hides it from the main navigation and blocks its routes. Backend APIs for that
      module stay available for admins who already know the URL only if the route is still registered —
      users without access are redirected home. Settings stays on so you can re-enable modules.
    </v-alert>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <v-card v-else variant="outlined">
      <v-list lines="two">
        <v-list-item v-for="m in modules" :key="m.key">
          <template #prepend>
            <v-switch
              v-model="m.enabled"
              color="primary"
              hide-details
              density="compact"
              class="me-2"
              :disabled="m.locked"
              :aria-label="`Toggle ${m.label}`"
            />
          </template>
          <v-list-item-title class="font-weight-medium">
            {{ m.label }}
            <v-chip v-if="m.locked" size="x-small" class="ms-2" variant="tonal">Required</v-chip>
            <v-chip
              v-else-if="m.key === 'payroll' && !m.enabled"
              size="x-small"
              class="ms-2"
              color="warning"
              variant="tonal"
            >
              Off by default
            </v-chip>
          </v-list-item-title>
          <v-list-item-subtitle>{{ m.description }}</v-list-item-subtitle>
        </v-list-item>
      </v-list>
      <v-card-actions class="px-4 pb-4">
        <v-spacer />
        <v-btn color="primary" :loading="saving" @click="save">Save changes</v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>
