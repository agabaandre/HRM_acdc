<script setup lang="ts">
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PayrollSubnav from '@/components/payroll/PayrollSubnav.vue'

defineProps<{
  title: string
  lede?: string
}>()
</script>

<template>
  <div class="payroll-page">
    <div class="payroll-page__inner">
      <PortalPageChrome :title="title" :lede="lede" icon="fa-solid fa-money-check-dollar">
        <template v-if="$slots.actions" #actions>
          <slot name="actions" />
        </template>
        <template #tabs>
          <PayrollSubnav />
        </template>
      </PortalPageChrome>

      <div class="payroll-page__body">
        <slot />
      </div>
    </div>
  </div>
</template>

<style scoped>
.payroll-page {
  margin: -0.35rem -0.35rem 0;
  padding: 0.85rem 0.85rem 1.5rem;
  min-height: calc(100vh - 11rem);
  border-radius: 0.85rem;
  background:
    radial-gradient(900px 320px at 8% -5%, rgba(17, 154, 72, 0.11), transparent 55%),
    radial-gradient(700px 280px at 100% 0%, rgba(58, 71, 82, 0.06), transparent 50%),
    linear-gradient(180deg, #eef5f1 0%, #f2f5f3 42%, #f6f7f6 100%);
}

.payroll-page__inner {
  max-width: 1180px;
}

.payroll-page__body {
  margin-top: 0.15rem;
}

.payroll-page :deep(.portal-page-chrome) {
  margin-bottom: 1rem;
}
</style>

<style>
.payroll-panel {
  padding: 1rem 1.05rem 1.1rem;
  border-radius: 0.75rem;
  border: 1px solid rgba(58, 71, 82, 0.1);
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 1px 2px rgba(58, 71, 82, 0.04);
  margin-bottom: 0.85rem;
}
.payroll-panel__title {
  margin: 0 0 0.65rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: #3a4752;
}
.payroll-table-wrap {
  border-radius: 0.75rem;
  border: 1px solid rgba(58, 71, 82, 0.1);
  background: rgba(255, 255, 255, 0.94);
  overflow: auto;
  box-shadow: 0 1px 2px rgba(58, 71, 82, 0.04);
}
.payroll-table-wrap .v-table {
  background: transparent;
}
.payroll-status {
  display: inline-block;
  padding: 0.12rem 0.5rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  background: #eef2f7;
  color: #3a4752;
  text-transform: capitalize;
}
.payroll-status--posted,
.payroll-status--active,
.payroll-status--simulated {
  background: #e8f6ee;
  color: #0d7a3a;
}
.payroll-status--draft,
.payroll-status--pending_supervisor,
.payroll-status--pending_payroll {
  background: #fef3c7;
  color: #92400e;
}
.payroll-status--cancelled,
.payroll-status--cancelleded,
.payroll-status--cancelledled {
  background: #fee2e2;
  color: #991b1b;
}
</style>
