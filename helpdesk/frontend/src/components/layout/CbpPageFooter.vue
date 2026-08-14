<script setup lang="ts">
withDefaults(
  defineProps<{
    /** Product label after the copyright (omit / empty to hide). */
    product?: string
    /** Optional absolute or site-relative API base URL shown as “API”. */
    apiHref?: string | null
  }>(),
  {
    product: 'Service Desk',
    apiHref: null,
  },
)

const year = new Date().getFullYear()
</script>

<template>
  <footer class="cbp-page-footer">
    <p>
      Copyright © Africa CDC {{ year }}. All rights reserved.
      <template v-if="product"> · {{ product }}</template>
      <template v-if="apiHref">
        ·
        <a :href="apiHref" target="_blank" rel="noopener noreferrer">API</a>
      </template>
    </p>
  </footer>
</template>
