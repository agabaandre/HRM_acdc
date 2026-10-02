<script setup lang="ts">
withDefaults(
  defineProps<{
    width?: string
    height?: string
    radius?: string
    className?: string
  }>(),
  {
    width: '100%',
    height: '1rem',
    radius: '0',
    className: '',
  },
)
</script>

<template>
  <span
    class="portal-skel"
    :class="className"
    :style="{ width, height, borderRadius: radius }"
    aria-hidden="true"
  />
</template>

<style scoped>
.portal-skel {
  display: block;
  background: linear-gradient(
    90deg,
    rgba(15, 23, 42, 0.06) 0%,
    rgba(15, 23, 42, 0.12) 45%,
    rgba(15, 23, 42, 0.06) 90%
  );
  background-size: 200% 100%;
  animation: portalSkelShimmer 1.15s ease-in-out infinite;
}

html.helpdesk-theme-dark .portal-skel {
  background: linear-gradient(
    90deg,
    rgba(226, 232, 240, 0.06) 0%,
    rgba(226, 232, 240, 0.14) 45%,
    rgba(226, 232, 240, 0.06) 90%
  );
  background-size: 200% 100%;
}

@keyframes portalSkelShimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .portal-skel {
    animation: none;
  }
}
</style>
