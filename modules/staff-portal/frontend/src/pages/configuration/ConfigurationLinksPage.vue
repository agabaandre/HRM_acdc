<script setup lang="ts">
import { RouterLink } from 'vue-router'

defineProps<{
  title: string
  links: Array<{ to: string; label: string; detail: string }>
}>()
</script>

<template>
  <div>
    <h2 class="cfg-links-title">{{ title }}</h2>
    <div class="cfg-links">
      <RouterLink v-for="link in links" :key="link.to" :to="link.to" class="cfg-link">
        <strong>{{ link.label }}</strong>
        <span>{{ link.detail }}</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.cfg-links-title {
  margin: 0 0 0.8rem;
  color: #1b2744;
  font-size: 1.15rem;
}

.cfg-links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.75rem;
}

.cfg-link {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.9rem 1rem;
  border: 2px solid #b7c3d4;
  border-radius: 12px;
  background: #fff;
  color: #1b2744;
  text-decoration: none;
}

.cfg-link:hover {
  border-color: #119a48;
}

.cfg-link span {
  color: #5c6b80;
  font-size: 0.84rem;
}
</style>
