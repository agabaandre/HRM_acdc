<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { fetchSettingsHub, type SettingsHubCard } from '@/lib/settingsApi'
import { useLocaleStore } from '@/stores/locale'

const locale = useLocaleStore()
const cards = ref<SettingsHubCard[]>([])
const search = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

function cardLabel(card: SettingsHubCard): string {
  if (!card.i18n_key) return card.label
  return locale.t(`settings.${card.i18n_key}`, card.label)
}

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return cards.value
  return cards.value.filter((c) => cardLabel(c).toLowerCase().includes(q) || c.label.toLowerCase().includes(q))
})

onMounted(async () => {
  loading.value = true
  try {
    cards.value = await fetchSettingsHub()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('settings.load_error', 'Could not load settings'))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <PortalPageChrome :title="locale.t('settings.title', 'Settings')" :lede="locale.t('settings.lede', 'Configure portal modules, languages, AI, lookups, leave, and performance.')" />
    <v-text-field
      v-model="search"
      :label="locale.t('settings.search', 'Search settings')"
      density="compact"
      class="mb-4"
      clearable
      hide-details
      style="max-width: 360px"
    />
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">{{ locale.t('settings.loading', 'Loading…') }}</div>
    <v-row v-else>
      <v-col v-for="card in filtered" :key="card.to" cols="12" sm="6" md="4" lg="3">
        <RouterLink :to="card.to" style="text-decoration: none; color: inherit">
          <v-card variant="outlined" class="h-100 settings-card" hover>
            <v-card-text class="d-flex align-center gap-3">
              <i :class="['bx', card.icon, 'text-h5 text-primary']" aria-hidden="true" />
              <div>
                <div class="font-weight-medium">{{ cardLabel(card) }}</div>
                <div v-if="card.special" class="text-caption text-medium-emphasis">{{ locale.t('settings.special', 'Special') }}</div>
              </div>
            </v-card-text>
          </v-card>
        </RouterLink>
      </v-col>
    </v-row>
  </div>
</template>
