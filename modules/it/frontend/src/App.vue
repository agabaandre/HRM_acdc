<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import ToastViewport from '@/components/organisms/ToastViewport.vue'

const PortalAppShell = defineAsyncComponent(
  () => import('@/components/templates/PortalAppShell.vue'),
)

const route = useRoute()
const showChrome = computed(() => route.meta.chrome !== false)
</script>

<template>
  <UApp>
    <PortalAppShell v-if="showChrome" :show-chrome="true">
      <RouterView />
    </PortalAppShell>
    <RouterView v-else />
    <ToastViewport />
  </UApp>
</template>
