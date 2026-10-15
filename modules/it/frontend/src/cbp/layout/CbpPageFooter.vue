<script setup lang="ts">
import { onMounted } from 'vue'
import { useBrandingStore } from '@/stores/branding'
import { useLocaleStore } from '@/stores/locale'

withDefaults(
  defineProps<{
    product?: string
    apiHref?: string | null
  }>(),
  {
    product: '',
    apiHref: null,
  },
)

const locale = useLocaleStore()
const branding = useBrandingStore()
const year = new Date().getFullYear()

onMounted(() => {
  void branding.bootstrap()
})
</script>

<template>
  <footer class="cbp-page-footer">
    <p>
      {{
        branding.copyright
          || locale.t('chrome.copyright', 'Copyright © Africa CDC {year}. All rights reserved.', { year })
      }}
      <template v-if="product"> · {{ product }}</template>
      <template v-if="apiHref">
        ·
        <a :href="apiHref" target="_blank" rel="noopener noreferrer">{{ locale.t('chrome.api', 'API') }}</a>
      </template>
    </p>
  </footer>
</template>
