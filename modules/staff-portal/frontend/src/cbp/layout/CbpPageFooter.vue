<script setup lang="ts">
import { useLocaleStore } from '@/stores/locale'

withDefaults(
  defineProps<{
    /** Product label after the copyright (omit / empty to hide). */
    product?: string
    /** Optional absolute or site-relative API base URL shown as “API”. */
    apiHref?: string | null
  }>(),
  {
    product: '',
    apiHref: null,
  },
)

const locale = useLocaleStore()
const year = new Date().getFullYear()
</script>

<template>
  <footer class="cbp-page-footer">
    <p>
      {{ locale.t('chrome.copyright', 'Copyright © Africa CDC {year}. All rights reserved.', { year }) }}
      <template v-if="product"> · {{ product }}</template>
      <template v-if="apiHref">
        ·
        <a :href="apiHref" target="_blank" rel="noopener noreferrer">{{ locale.t('chrome.api', 'API') }}</a>
      </template>
    </p>
  </footer>
</template>
