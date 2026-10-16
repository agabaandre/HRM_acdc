<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import DocumentList from './DocumentList.vue'

const route = useRoute()
const year = computed(() => Number(route.params.year))
const total = ref<number | null>(null)
</script>

<template>
  <div>
    <CbpPageHeading :title="`${year} archive`" back-to="/dashboard" back-label="← Dashboard">
      <template #lede>
        Archived registry documents for {{ year }}<span v-if="total != null"> · {{ total.toLocaleString() }}</span>.
      </template>
    </CbpPageHeading>
    <DocumentList :key="year" scope="archive" :year="year" @loaded="(meta) => total = meta.total" />
  </div>
</template>
