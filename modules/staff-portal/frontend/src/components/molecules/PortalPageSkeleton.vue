<script setup lang="ts">
import PortalSkeleton from '@/components/atoms/PortalSkeleton.vue'

const props = withDefaults(
  defineProps<{
    /** Layout shaped to the destination page while JS/API loads. */
    variant?: 'default' | 'home' | 'dashboard' | 'form' | 'table'
    /** Skeleton table body rows (table variant). */
    rows?: number
    /** Skeleton table columns (table variant). */
    cols?: number
  }>(),
  { variant: 'default', rows: 8, cols: 6 },
)

const tableRows = Array.from({ length: Math.max(1, props.rows) }, (_, i) => i)
const tableCols = Array.from({ length: Math.max(2, props.cols) }, (_, i) => i)
</script>

<template>
  <div class="portal-page-skel" role="status" aria-live="polite" aria-busy="true">
    <span class="visually-hidden">Loading…</span>

    <template v-if="variant === 'home'">
      <div class="portal-page-skel__home-grid">
        <div v-for="n in 6" :key="n" class="portal-page-skel__home-card">
          <PortalSkeleton height="1.1rem" width="42%" class-name="mx-auto" />
          <PortalSkeleton height="0.75rem" width="88%" class-name="mx-auto mt" />
          <PortalSkeleton height="0.75rem" width="72%" class-name="mx-auto mt-sm" />
        </div>
      </div>
    </template>

    <template v-else-if="variant === 'dashboard'">
      <div class="portal-page-skel__filters">
        <PortalSkeleton v-for="n in 4" :key="n" height="2.5rem" />
      </div>
      <div class="portal-page-skel__kpis">
        <div v-for="n in 4" :key="n" class="portal-page-skel__kpi">
          <PortalSkeleton height="2.75rem" width="2.75rem" radius="0.65rem" />
          <div class="portal-page-skel__kpi-text">
            <PortalSkeleton height="0.7rem" width="70%" />
            <PortalSkeleton height="1.5rem" width="45%" class-name="mt-sm" />
          </div>
        </div>
      </div>
      <div class="portal-page-skel__charts">
        <div v-for="n in 4" :key="n" class="portal-page-skel__chart">
          <PortalSkeleton height="0.9rem" width="40%" />
          <PortalSkeleton height="14rem" class-name="mt" />
        </div>
      </div>
    </template>

    <template v-else-if="variant === 'form'">
      <div class="portal-page-skel__form">
        <div v-for="col in 2" :key="col" class="portal-page-skel__form-card">
          <PortalSkeleton height="1.25rem" width="45%" />
          <div class="portal-page-skel__form-fields">
            <PortalSkeleton v-for="n in 6" :key="n" height="2.5rem" />
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="variant === 'table'">
      <div class="portal-page-skel__table-toolbar">
        <PortalSkeleton height="2.25rem" width="9rem" />
        <PortalSkeleton height="2.25rem" width="7rem" />
        <PortalSkeleton height="2.25rem" width="5.5rem" />
      </div>
      <div class="portal-page-skel__table">
        <div class="portal-page-skel__table-head">
          <PortalSkeleton
            v-for="c in tableCols"
            :key="`h-${c}`"
            height="0.75rem"
            :width="c === 0 ? '2rem' : '70%'"
          />
        </div>
        <div v-for="r in tableRows" :key="`r-${r}`" class="portal-page-skel__table-row">
          <PortalSkeleton
            v-for="c in tableCols"
            :key="`c-${r}-${c}`"
            height="0.85rem"
            :width="c === 0 ? '1.5rem' : `${55 + ((r + c) % 4) * 8}%`"
          />
        </div>
      </div>
    </template>

    <template v-else>
      <PortalSkeleton height="1.5rem" width="28%" />
      <PortalSkeleton height="0.85rem" width="55%" class-name="mt-sm" />
      <PortalSkeleton height="12rem" class-name="mt" />
      <PortalSkeleton height="8rem" class-name="mt" />
    </template>
  </div>
</template>

<style scoped>
.portal-page-skel {
  width: 100%;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.mx-auto {
  margin-left: auto;
  margin-right: auto;
}
.mt {
  margin-top: 0.85rem;
}
.mt-sm {
  margin-top: 0.45rem;
}

.portal-page-skel__home-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}
@media (min-width: 768px) {
  .portal-page-skel__home-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.portal-page-skel__home-card {
  min-height: 8.75rem;
  padding: 1rem 1.1rem;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.1);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
  display: flex;
  flex-direction: column;
  justify-content: center;
  box-sizing: border-box;
}
html.helpdesk-theme-dark .portal-page-skel__home-card {
  background: #1e293b;
  border-color: rgba(148, 163, 184, 0.28);
}

.portal-page-skel__filters {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
@media (min-width: 600px) {
  .portal-page-skel__filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (min-width: 960px) {
  .portal-page-skel__filters {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

.portal-page-skel__kpis {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
@media (min-width: 960px) {
  .portal-page-skel__kpis {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
.portal-page-skel__kpi {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.1);
  border-top: 3px solid rgba(13, 122, 58, 0.35);
}
html.helpdesk-theme-dark .portal-page-skel__kpi {
  background: #1e293b;
  border-color: rgba(148, 163, 184, 0.28);
}
.portal-page-skel__kpi-text {
  flex: 1;
  min-width: 0;
}

.portal-page-skel__charts {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}
@media (min-width: 960px) {
  .portal-page-skel__charts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.portal-page-skel__chart {
  padding: 0.85rem;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.1);
}
html.helpdesk-theme-dark .portal-page-skel__chart {
  background: #1e293b;
  border-color: rgba(148, 163, 184, 0.28);
}

.portal-page-skel__form {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
}
@media (min-width: 960px) {
  .portal-page-skel__form {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.portal-page-skel__form-card {
  padding: 1rem;
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.1);
}
html.helpdesk-theme-dark .portal-page-skel__form-card {
  background: #1e293b;
  border-color: rgba(148, 163, 184, 0.28);
}
.portal-page-skel__form-fields {
  display: grid;
  gap: 0.75rem;
  margin-top: 1rem;
}

.portal-page-skel__table-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-bottom: 0.75rem;
}
.portal-page-skel__table {
  background: #fff;
  border: 1px solid rgba(15, 23, 42, 0.1);
  overflow: hidden;
}
html.helpdesk-theme-dark .portal-page-skel__table {
  background: #1e293b;
  border-color: rgba(148, 163, 184, 0.28);
}
.portal-page-skel__table-head,
.portal-page-skel__table-row {
  display: grid;
  grid-template-columns: 2.5rem repeat(auto-fit, minmax(4.5rem, 1fr));
  gap: 0.75rem;
  align-items: center;
  padding: 0.7rem 0.85rem;
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}
html.helpdesk-theme-dark .portal-page-skel__table-head,
html.helpdesk-theme-dark .portal-page-skel__table-row {
  border-bottom-color: rgba(148, 163, 184, 0.18);
}
.portal-page-skel__table-head {
  background: rgba(15, 23, 42, 0.03);
}
html.helpdesk-theme-dark .portal-page-skel__table-head {
  background: rgba(148, 163, 184, 0.08);
}
.portal-page-skel__table-row:last-child {
  border-bottom: 0;
}
</style>
