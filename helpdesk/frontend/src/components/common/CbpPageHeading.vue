<script setup lang="ts">
import { RouterLink } from 'vue-router'

defineProps<{
  title: string
  backTo?: string
  backLabel?: string
}>()
</script>

<template>
  <header class="cbp-view-head">
    <p v-if="backTo" class="cbp-view-back">
      <RouterLink :to="backTo">{{ backLabel ?? '← Back' }}</RouterLink>
    </p>
    <h1 class="cbp-view-title">{{ title }}</h1>
    <p v-if="$slots.lede" class="cbp-view-lede">
      <slot name="lede" />
    </p>
  </header>
</template>

<style scoped>
.cbp-view-head {
  margin-bottom: 1.1rem;
}
.cbp-view-back {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
}
.cbp-view-back a {
  color: #119a48;
  font-weight: 600;
  text-decoration: none;
}
.cbp-view-back a:hover {
  text-decoration: underline;
}
.cbp-view-title {
  font-size: 1.35rem;
  margin: 0 0 0.35rem;
  color: #2c3e50;
  font-weight: 700;
}
.cbp-view-lede {
  margin: 0;
  color: #5c6c7c;
  line-height: 1.55;
  font-size: 0.95rem;
}
</style>
