<script setup lang="ts">
import { useLocaleStore } from '@/stores/locale'

withDefaults(
  defineProps<{
    variant?: 'form' | 'table' | 'cards' | 'lines'
    rows?: number
  }>(),
  { variant: 'form', rows: 6 },
)

const locale = useLocaleStore()
</script>

<template>
  <div class="rr-skel" aria-busy="true" :aria-label="locale.t('rr.loading', 'Loading…')">
    <template v-if="variant === 'table'">
      <div class="rr-card">
        <div v-for="n in rows" :key="n" class="rr-skel__row">
          <div class="rr-skel__line" />
          <div class="rr-skel__line" />
          <div class="rr-skel__line" />
          <div class="rr-skel__line" />
          <div class="rr-skel__line" />
          <div class="rr-skel__line" />
          <div class="rr-skel__line" />
        </div>
      </div>
    </template>
    <template v-else-if="variant === 'cards'">
      <div class="rr-card-grid rr-card-grid--2">
        <div v-for="n in rows" :key="n" class="rr-card">
          <div class="rr-skel__line rr-skel__line--md" />
          <div class="rr-skel__block" style="margin-top: 0.75rem" />
        </div>
      </div>
    </template>
    <template v-else-if="variant === 'lines'">
      <div class="rr-card">
        <div v-for="n in rows" :key="n" class="rr-skel__line" :class="n % 2 ? 'rr-skel__line--md' : ''" style="margin-bottom: 0.65rem" />
      </div>
    </template>
    <template v-else>
      <div class="rr-card">
        <div class="rr-skel__line rr-skel__line--md" style="margin-bottom: 1rem" />
        <div class="rr-grid">
          <div v-for="n in 4" :key="n" class="rr-skel__block" style="height: 3.2rem" />
        </div>
        <div v-for="n in rows" :key="'t' + n" class="rr-skel__block" style="height: 4.5rem; margin-top: 0.85rem" />
      </div>
    </template>
  </div>
</template>
