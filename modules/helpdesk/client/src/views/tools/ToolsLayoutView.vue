<script setup lang="ts">
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="tools-layout">
    <RouterView />
  </div>
</template>

<style scoped>
.tools-layout {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
</style>
