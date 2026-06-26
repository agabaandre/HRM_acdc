<script setup lang="ts">
import { routePreloaderVisible } from '../../lib/appPreloader'
</script>

<template>
  <Transition name="hd-route-fade">
    <div
      v-if="routePreloaderVisible"
      class="hd-route-loader"
      role="status"
      aria-live="polite"
      aria-busy="true"
      aria-label="Loading page"
    >
      <div class="hd-route-loader__inner">
        <div class="hd-route-loader__spinner" aria-hidden="true">
          <span class="hd-route-loader__ring" />
        </div>
        <p class="hd-route-loader__label">IT Service Desk</p>
      </div>
    </div>
  </Transition>
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
