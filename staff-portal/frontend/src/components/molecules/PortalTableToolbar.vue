<script setup lang="ts">
/**
 * Table chrome: header (count, per-page, exports) + footer (pagination bottom-right).
 * Font Awesome icons to match CI3 / portal nav.
 */
withDefaults(
  defineProps<{
    page: number
    lastPage: number
    total: number
    perPage: number
    perPageOptions?: number[]
    totalLabel?: string
    exporting?: boolean
    showCsv?: boolean
    showPdf?: boolean
    showPerPage?: boolean
    /** header = top actions; footer = bottom-right pager */
    placement?: 'header' | 'footer'
  }>(),
  {
    perPageOptions: () => [10, 20, 50, 100],
    totalLabel: 'Total',
    exporting: false,
    showCsv: true,
    showPdf: true,
    showPerPage: true,
    placement: 'header',
  },
)

const emit = defineEmits<{
  'update:page': [value: number]
  'update:perPage': [value: number]
  exportCsv: []
  exportPdf: []
}>()

function setPage(next: number, last: number) {
  if (next < 1 || next > Math.max(1, last)) return
  emit('update:page', next)
}
</script>

<template>
  <div
    class="portal-table-toolbar"
    :class="placement === 'footer' ? 'portal-table-toolbar--footer' : 'portal-table-toolbar--header'"
  >
    <template v-if="placement === 'header'">
      <div class="portal-table-toolbar__left">
        <span class="portal-table-toolbar__total" role="status">
          <strong>{{ total }}</strong> {{ totalLabel }}
        </span>
        <div v-if="showPerPage" class="portal-table-toolbar__per-page">
          <span class="text-caption text-medium-emphasis">Records per page</span>
          <v-select
            :model-value="perPage"
            :items="perPageOptions"
            density="compact"
            hide-details
            variant="outlined"
            style="max-width: 5.5rem"
            @update:model-value="(v) => emit('update:perPage', Number(v))"
          />
        </div>
      </div>
      <div class="portal-table-toolbar__actions">
        <slot name="actions" />
        <v-btn
          v-if="showCsv"
          size="small"
          variant="outlined"
          color="primary"
          :loading="exporting"
          @click="emit('exportCsv')"
        >
          <i class="fa-solid fa-file-csv me-2" aria-hidden="true" />
          Export CSV
        </v-btn>
        <v-btn
          v-if="showPdf"
          size="small"
          variant="outlined"
          color="error"
          :loading="exporting"
          @click="emit('exportPdf')"
        >
          <i class="fa-solid fa-file-pdf me-2" aria-hidden="true" />
          Export PDF
        </v-btn>
      </div>
    </template>

    <template v-else>
      <div class="portal-table-toolbar__footer-spacer" />
      <div class="portal-table-toolbar__pager">
        <v-btn
          size="x-small"
          variant="text"
          :disabled="page <= 1"
          @click="setPage(page - 1, lastPage)"
        >
          Previous
        </v-btn>
        <template v-if="lastPage <= 7">
          <v-btn
            v-for="n in Math.max(1, lastPage)"
            :key="n"
            size="x-small"
            :variant="n === page ? 'flat' : 'text'"
            :color="n === page ? 'primary' : undefined"
            @click="setPage(n, lastPage)"
          >
            {{ n }}
          </v-btn>
        </template>
        <template v-else>
          <v-btn
            size="x-small"
            :variant="page === 1 ? 'flat' : 'text'"
            :color="page === 1 ? 'primary' : undefined"
            @click="setPage(1, lastPage)"
          >
            1
          </v-btn>
          <span v-if="page > 3" class="portal-table-toolbar__ellipsis">…</span>
          <v-btn
            v-if="page > 2 && page < lastPage"
            size="x-small"
            variant="flat"
            color="primary"
          >
            {{ page }}
          </v-btn>
          <span v-if="page < lastPage - 2" class="portal-table-toolbar__ellipsis">…</span>
          <v-btn
            size="x-small"
            :variant="page === lastPage ? 'flat' : 'text'"
            :color="page === lastPage ? 'primary' : undefined"
            @click="setPage(lastPage, lastPage)"
          >
            {{ lastPage }}
          </v-btn>
        </template>
        <v-btn
          size="x-small"
          variant="text"
          :disabled="page >= lastPage"
          @click="setPage(page + 1, lastPage)"
        >
          Next
        </v-btn>
      </div>
    </template>
  </div>
</template>

<style scoped>
.portal-table-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1rem;
  padding: 0.65rem 0.15rem;
}

.portal-table-toolbar--header {
  justify-content: space-between;
  border-bottom: 1px solid #dfe5ef;
}

.portal-table-toolbar--footer {
  justify-content: flex-end;
  border-top: 1px solid #dfe5ef;
  margin-top: 0;
}

.portal-table-toolbar__left {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1rem;
}

.portal-table-toolbar__footer-spacer {
  flex: 1 1 auto;
}

.portal-table-toolbar__pager {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.15rem;
}

.portal-table-toolbar__ellipsis {
  padding: 0 0.25rem;
  color: #768b9e;
}

.portal-table-toolbar__total {
  font-size: 0.875rem;
  color: #768b9e;
}

.portal-table-toolbar__total strong {
  color: #3a4752;
  font-weight: 600;
}

.portal-table-toolbar__per-page {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.portal-table-toolbar__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
}

.portal-table-toolbar__actions :deep(.fa-solid) {
  font-size: 0.9rem;
}
</style>
