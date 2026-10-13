<script setup lang="ts">
withDefaults(
  defineProps<{
    /** Shape of the placeholder while the page loads. */
    variant?: 'dashboard' | 'form' | 'table'
    /** KPI cards for dashboard, or table body rows. */
    rows?: number
  }>(),
  { variant: 'dashboard', rows: 4 },
)
</script>

<template>
  <div class="fin-skel" role="status" aria-live="polite" aria-busy="true" aria-label="Loading">
    <template v-if="variant === 'dashboard'">
      <div class="fin-skel__kpis">
        <div v-for="n in Math.max(3, rows)" :key="`k-${n}`" class="fin-skel__kpi">
          <div class="fin-skel__kpi-body">
            <div class="fin-skel__line fin-skel__line--sm" />
            <div class="fin-skel__line fin-skel__line--lg" />
            <div class="fin-skel__line fin-skel__line--xs" />
          </div>
          <div class="fin-skel__icon" />
        </div>
      </div>
      <div class="fin-skel__charts">
        <div v-for="n in 2" :key="`c-${n}`" class="fin-skel__chart">
          <div class="fin-skel__line fin-skel__line--md" />
          <div class="fin-skel__block" />
        </div>
      </div>
      <div class="fin-skel__panel">
        <div class="fin-skel__line fin-skel__line--md" style="margin-bottom: 0.85rem" />
        <div v-for="n in 5" :key="`r-${n}`" class="fin-skel__row">
          <div class="fin-skel__line" />
          <div class="fin-skel__line" />
          <div class="fin-skel__line" />
          <div class="fin-skel__line" />
        </div>
      </div>
    </template>

    <template v-else-if="variant === 'table'">
      <div class="fin-skel__panel">
        <div class="fin-skel__toolbar">
          <div class="fin-skel__line fin-skel__line--btn" />
          <div class="fin-skel__line fin-skel__line--btn" />
        </div>
        <div v-for="n in Math.max(4, rows)" :key="`t-${n}`" class="fin-skel__row">
          <div class="fin-skel__line" />
          <div class="fin-skel__line" />
          <div class="fin-skel__line" />
          <div class="fin-skel__line" />
          <div class="fin-skel__line" />
        </div>
      </div>
    </template>

    <template v-else>
      <div class="fin-skel__panel">
        <div class="fin-skel__line fin-skel__line--md" style="margin-bottom: 1rem" />
        <div class="fin-skel__form-grid">
          <div v-for="n in Math.max(4, rows)" :key="`f-${n}`" class="fin-skel__field" />
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.fin-skel {
  width: 100%;
  margin-bottom: 1rem;
}
.fin-skel__kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: 0.85rem;
  margin-bottom: 0.85rem;
}
.fin-skel__kpi {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  min-height: 102px;
  padding: 1.05rem 1.15rem;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 2px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 16px rgba(15, 23, 42, 0.04);
}
.fin-skel__kpi-body {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.fin-skel__icon {
  flex: 0 0 auto;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 2px;
  background: linear-gradient(90deg, #eef2f7 0%, #f8fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: fin-skel-shimmer 1.1s ease-in-out infinite;
}
.fin-skel__charts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.85rem;
  margin-bottom: 0.85rem;
}
.fin-skel__chart,
.fin-skel__panel {
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 2px;
  padding: 0.95rem 1.05rem;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.fin-skel__block {
  margin-top: 0.75rem;
  height: 14rem;
  border-radius: 2px;
  background: linear-gradient(90deg, #eef2f7 0%, #f8fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: fin-skel-shimmer 1.1s ease-in-out infinite;
}
.fin-skel__toolbar {
  display: flex;
  gap: 0.65rem;
  margin-bottom: 0.85rem;
}
.fin-skel__row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(4rem, 1fr));
  gap: 0.65rem;
  margin-bottom: 0.55rem;
}
.fin-skel__form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
  gap: 0.75rem;
}
.fin-skel__field {
  height: 2.65rem;
  border-radius: 2px;
  background: linear-gradient(90deg, #eef2f7 0%, #f8fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: fin-skel-shimmer 1.1s ease-in-out infinite;
}
.fin-skel__line {
  height: 0.7rem;
  border-radius: 2px;
  background: linear-gradient(90deg, #eef2f7 0%, #f8fafc 50%, #eef2f7 100%);
  background-size: 200% 100%;
  animation: fin-skel-shimmer 1.1s ease-in-out infinite;
}
.fin-skel__line--xs { width: 45%; height: 0.55rem; }
.fin-skel__line--sm { width: 55%; height: 0.6rem; }
.fin-skel__line--md { width: 38%; height: 0.85rem; }
.fin-skel__line--lg { width: 62%; height: 1.35rem; }
.fin-skel__line--btn { width: 7rem; height: 2.25rem; }
@keyframes fin-skel-shimmer {
  0% { background-position: 100% 0; }
  100% { background-position: -100% 0; }
}
@media (max-width: 900px) {
  .fin-skel__charts { grid-template-columns: 1fr; }
}
</style>
