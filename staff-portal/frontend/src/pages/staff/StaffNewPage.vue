<script setup lang="ts">
import { RouterLink } from 'vue-router'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
</script>

<template>
  <div>
    <PortalPageChrome title="New staff" lede="New staff form coming soon">
      <template #actions>
        <RouterLink to="/staff" style="text-decoration:none">
          <v-btn size="small" variant="outlined">Directory</v-btn>
        </RouterLink>
      </template>
    </PortalPageChrome>
    <p class="text-medium-emphasis">New staff form coming soon</p>
  </div>
</template>
