<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { fetchSettingsHub, type SettingsHubCard } from '@/lib/settingsApi'

const cards = ref<SettingsHubCard[]>([])
const search = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return cards.value
  return cards.value.filter((c) => c.label.toLowerCase().includes(q))
})

onMounted(async () => {
  loading.value = true
  try {
    cards.value = await fetchSettingsHub()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load settings')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <PortalPageChrome title="Settings" lede="Configure portal modules, lookups, leave, and performance." />
    <v-text-field
      v-model="search"
      label="Search settings"
      density="compact"
      class="mb-4"
      clearable
      hide-details
      style="max-width: 360px"
    />
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>
    <v-row v-else>
      <v-col v-for="card in filtered" :key="card.to" cols="12" sm="6" md="4" lg="3">
        <RouterLink :to="card.to" style="text-decoration: none; color: inherit">
          <v-card variant="outlined" class="h-100 settings-card" hover>
            <v-card-text class="d-flex align-center gap-3">
              <i :class="['bx', card.icon, 'text-h5 text-primary']" aria-hidden="true" />
              <div>
                <div class="font-weight-medium">{{ card.label }}</div>
                <div v-if="card.special" class="text-caption text-medium-emphasis">Special</div>
              </div>
            </v-card-text>
          </v-card>
        </RouterLink>
      </v-col>
    </v-row>
  </div>
</template>
