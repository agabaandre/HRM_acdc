<script setup lang="ts">
defineProps<{
  product?: string
}>()
</script>

<template>
  <div class="cbp-badge-row">
    <span class="cbp-badge">Central Business Platform</span>
    <span class="cbp-badge cbp-badge-gold">{{ product ?? 'ITSM' }}</span>
  </div>
</template>

<style scoped>
.cbp-badge-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}
.cbp-badge {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  padding: 0.25rem 0.55rem;
  border-radius: 999px;
  background: #e8f5ee;
  color: #065f2c;
  border: 1px solid rgba(17, 154, 72, 0.25);
}
.cbp-badge-gold {
  background: #faf6e7;
  color: #7a5f08;
  border-color: rgba(201, 162, 39, 0.35);
}
</style>
