<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import ToastViewport from '@/components/organisms/ToastViewport.vue'
import PortalPageSkeleton from '@/components/molecules/PortalPageSkeleton.vue'

const PortalAppShell = defineAsyncComponent(() => import('@/components/templates/PortalAppShell.vue'))

const route = useRoute()
const showChrome = computed(() => route.meta.chrome !== false)

const skeletonVariant = computed(() => {
  const name = String(route.name || '')
  const path = route.path
  if (name === 'home' || path === '/' || path.startsWith('/home')) return 'home'
  if (name === 'dashboard' || path.startsWith('/dashboard')) return 'dashboard'
  if (name === 'staff-new' || path === '/staff/new') return 'form'
  if (path.includes('/new') || path.includes('/edit') || path.includes('/settings')) return 'form'
  return 'default'
})
</script>

<template>
  <UApp>
    <PortalAppShell v-if="showChrome" :show-chrome="true">
      <RouterView v-slot="{ Component, route: r }">
        <Suspense>
          <component :is="Component" :key="String(r.name ?? r.path)" />
          <template #fallback>
            <PortalPageSkeleton :variant="skeletonVariant" />
          </template>
        </Suspense>
      </RouterView>
    </PortalAppShell>
    <RouterView v-else v-slot="{ Component, route: r }">
      <Suspense>
        <component :is="Component" :key="String(r.name ?? r.path)" />
        <template #fallback>
          <PortalPageSkeleton :variant="skeletonVariant" />
        </template>
      </Suspense>
    </RouterView>
    <ToastViewport />
  </UApp>
</template>
