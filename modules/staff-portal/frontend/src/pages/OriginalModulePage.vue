<script setup lang="ts">
import { computed, ref } from 'vue'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { launchCbpModule } from '@/lib/cbpLaunch'
import { APM_MODULE, HELPDESK_MODULE, IT_MODULE, REGISTRY_MODULE, type OriginalModulePage } from '@/lib/originalModules'

const props = defineProps<{
  which: 'apm' | 'helpdesk' | 'it' | 'registry'
}>()

const pages: Record<typeof props.which, OriginalModulePage> = {
  apm: APM_MODULE,
  helpdesk: HELPDESK_MODULE,
  it: IT_MODULE,
  registry: REGISTRY_MODULE,
}

const page = computed(() => pages[props.which])
const opening = ref<string | null>(null)
const error = ref('')

async function openSection(path: string) {
  error.value = ''
  opening.value = path || page.value.title
  try {
    await launchCbpModule(page.value.moduleKey, false, path)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Could not open this section.'
    opening.value = null
  }
}
</script>

<template>
  <div>
    <PortalPageChrome :icon="page.icon" :title="page.title" :lede="page.lede" />
    <p v-if="error" class="text-error mb-4">{{ error }}</p>
    <v-row>
      <v-col v-for="section in page.sections" :key="section.label" cols="12" sm="6" md="4">
        <button type="button" class="module-section-link" :disabled="opening !== null" @click="openSection(section.path)">
          <v-card variant="outlined" class="h-100" hover>
            <v-card-text class="d-flex align-center ga-3">
              <i :class="[section.icon, 'text-h5 text-primary']" aria-hidden="true" />
              <div class="font-weight-medium">{{ section.label }}</div>
            </v-card-text>
          </v-card>
        </button>
      </v-col>
    </v-row>
  </div>
</template>

<style scoped>
.module-section-link {
  text-decoration: none;
  color: inherit;
  display: block;
  height: 100%;
  width: 100%;
  padding: 0;
  border: 0;
  background: transparent;
  text-align: inherit;
  cursor: pointer;
}

.module-section-link:disabled {
  cursor: progress;
}
</style>
