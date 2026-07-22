<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { routePreloaderVisible } from '../../lib/appPreloader'

const route = useRoute()
const fullFrame = computed(() => route.meta.chrome === false)
</script>

<template>
  <Teleport to="body">
    <Transition name="hd-route-fade">
      <div
        v-if="routePreloaderVisible"
        class="hd-route-loader"
        :class="fullFrame ? 'hd-route-loader--full' : 'hd-route-loader--chrome'"
        role="status"
        aria-live="polite"
        aria-busy="true"
        aria-label="Loading page"
      >
        <div class="hd-route-loader__inner">
          <div class="hd-route-loader__spinner" aria-hidden="true">
            <span class="hd-route-loader__ring" />
          </div>
          <p class="hd-route-loader__label">Service Desk</p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.hd-route-fade-enter-active,
.hd-route-fade-leave-active {
  transition: opacity 0.28s ease;
}

.hd-route-fade-enter-from,
.hd-route-fade-leave-to {
  opacity: 0;
}
</style>
