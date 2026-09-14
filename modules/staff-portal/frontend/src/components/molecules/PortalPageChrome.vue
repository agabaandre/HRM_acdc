<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { pageIconForPath } from '@/lib/portalNav'

const props = defineProps<{
  title: string
  lede?: string
  icon?: string
}>()

const route = useRoute()
const resolvedIcon = computed(
  () => props.icon || (route.meta.icon as string | undefined) || pageIconForPath(route.path),
)
</script>

<template>
  <header class="portal-page-chrome">
    <div class="portal-page-chrome__title-row">
      <div class="portal-page-chrome__heading">
        <i v-if="resolvedIcon" :class="[resolvedIcon, 'portal-page-chrome__icon']" aria-hidden="true" />
        <CbpPageHeading :title="title">
          <template v-if="lede || $slots.lede" #lede>
            <slot name="lede">{{ lede }}</slot>
          </template>
        </CbpPageHeading>
      </div>
      <div v-if="$slots.actions" class="portal-page-chrome__actions">
        <slot name="actions" />
      </div>
    </div>
    <div v-if="$slots.tabs" class="portal-page-chrome__tabs">
      <slot name="tabs" />
    </div>
  </header>
</template>

<style scoped>
.portal-page-chrome {
  margin-bottom: 1.15rem;
  padding-bottom: 0.15rem;
}

.portal-page-chrome__title-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.65rem 1rem;
}

.portal-page-chrome__heading {
  display: flex;
  align-items: flex-start;
  gap: 0.65rem;
  min-width: 0;
}

.portal-page-chrome__icon {
  margin-top: 0.2rem;
  font-size: 1.35rem;
  color: #119a48;
  flex-shrink: 0;
}

.portal-page-chrome :deep(.cbp-view-head) {
  margin-bottom: 0;
}

.portal-page-chrome__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding-top: 0.1rem;
}

.portal-page-chrome__tabs {
  margin-top: 0.55rem;
}
</style>
