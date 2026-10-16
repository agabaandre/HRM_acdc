<script setup lang="ts">
import { ref } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import DocumentList from './DocumentList.vue'

const year = new Date().getFullYear()
const total = ref<number | null>(null)
</script>

<template>
  <div>
    <CbpPageHeading :title="`${year} documents`" back-to="/dashboard" back-label="← Dashboard">
      <template #lede>
        Every document recorded for the current year<span v-if="total != null"> · {{ total.toLocaleString() }}</span>.
      </template>
    </CbpPageHeading>
    <DocumentList scope="year" @loaded="(meta) => total = meta.total" />
  </div>
</template>
