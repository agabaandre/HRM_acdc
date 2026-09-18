<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ensureHighcharts } from '@/lib/ensureHighcharts'
import { useLocaleStore } from '@/stores/locale'

declare global {
  interface Window {
    Highcharts?: {
      chart: (el: HTMLElement | string, options: Record<string, unknown>) => { destroy: () => void }
      mapChart?: (el: HTMLElement | string, options: Record<string, unknown>) => { destroy: () => void }
      maps?: Record<string, unknown>
      setOptions?: (opts: Record<string, unknown>) => void
    }
  }
}

const locale = useLocaleStore()

const props = withDefaults(
  defineProps<{
    title: string
    type?: 'pie' | 'column' | 'bar' | 'area' | 'solidgauge' | 'heatmap'
    categories?: string[]
    yCategories?: string[]
    series: Array<{
      name: string
      data: Array<number | { name: string; y: number; color?: string } | [number, number, number]>
      color?: string
    }>
    height?: number
    colors?: string[]
    color?: string
    yAxisTitle?: string
    yAxisMax?: number
    yAxisMin?: number
    gaugeUnit?: string
    exporting?: boolean
    showTableToggle?: boolean
  }>(),
  {
    type: 'column',
    height: 280,
    exporting: true,
    yAxisMin: 0,
    showTableToggle: true,
  },
)

const emit = defineEmits<{
  pointClick: [payload: { name: string; y: number; category?: string }]
}>()

const el = ref<HTMLDivElement | null>(null)
const viewMode = ref<'chart' | 'table'>('chart')
let chart: { destroy: () => void } | null = null
let pollTimer: number | undefined
let cancelled = false
let exportingConfigured = false

const tableRows = computed(() => {
  const rows: Array<{ label: string; value: number }> = []
  const series = props.series[0]
  if (!series) return rows
  series.data.forEach((point, idx) => {
    if (typeof point === 'number') {
      rows.push({ label: props.categories?.[idx] || String(idx + 1), value: point })
      return
    }
    if (Array.isArray(point)) {
      rows.push({ label: `L${point[0]} × I${point[1]}`, value: Number(point[2] || 0) })
      return
    }
    rows.push({ label: point.name, value: Number(point.y || 0) })
  })
  return rows
})

function ensureExportingDefaults() {
  if (!window.Highcharts || exportingConfigured) return
  window.Highcharts.setOptions?.({
    credits: { enabled: false },
    exporting: {
      enabled: true,
      buttons: {
        contextButton: {
          menuItems: [
            'downloadPNG',
            'downloadJPEG',
            'downloadPDF',
            'downloadSVG',
            'separator',
            'downloadCSV',
            'downloadXLS',
            'viewData',
            'printChart',
          ],
        },
      },
    },
  })
  exportingConfigured = true
}

function render() {
  if (!el.value || !window.Highcharts || viewMode.value !== 'chart') return
  chart?.destroy()
  chart = null

  const chartType = props.type || 'column'
  const isPie = chartType === 'pie'
  const isGauge = chartType === 'solidgauge'
  const isArea = chartType === 'area'
  const isHeat = chartType === 'heatmap'
  const seriesColor = props.color || '#119a48'
  const palette = props.colors || ['#119A48', '#fbb924', '#911C39', '#385CAD', '#C3A366']

  const base: Record<string, unknown> = {
    chart: {
      type: isHeat ? 'heatmap' : chartType,
      height: props.height ?? 280,
      backgroundColor: 'transparent',
    },
    title: {
      text: props.title,
      align: 'left',
      style: { fontSize: '13px', fontWeight: '600', color: '#2c3e50' },
    },
    credits: { enabled: false },
    colors: palette,
    exporting: { enabled: props.exporting !== false },
    legend: { enabled: !isPie && !isGauge },
    tooltip: { shared: !isPie && !isGauge && !isHeat },
    plotOptions: {
      series: {
        cursor: 'pointer',
        point: {
          events: {
            click() {
              const p = this as { name?: string; y?: number; category?: string }
              emit('pointClick', {
                name: String(p.name || p.category || ''),
                y: Number(p.y || 0),
                category: p.category,
              })
            },
          },
        },
      },
      pie: {
        allowPointSelect: true,
        cursor: 'pointer',
        dataLabels: { enabled: true, format: '<b>{point.name}</b>: {point.y} ({point.percentage:.1f}%)' },
      },
      column: {
        colorByPoint: Boolean(props.colors?.length),
        color: seriesColor,
        borderRadius: 2,
        dataLabels: { enabled: true, format: '{y}' },
      },
      bar: {
        colorByPoint: Boolean(props.colors?.length),
        color: seriesColor,
        borderRadius: 2,
        dataLabels: { enabled: true, format: '{y}' },
      },
      area: {
        color: seriesColor,
        fillOpacity: 0.45,
        marker: { enabled: false },
      },
      solidgauge: {
        dataLabels: { y: 5, borderWidth: 0, useHTML: true },
      },
    },
    series: props.series.map((s) => ({
      type: isHeat ? 'heatmap' : chartType,
      name: s.name,
      data: s.data,
      color: isPie || isHeat ? undefined : s.color || seriesColor,
      borderWidth: isHeat ? 1 : undefined,
      dataLabels: isHeat ? { enabled: true, color: '#1a2b3c' } : undefined,
    })),
  }

  if (isHeat) {
    base.xAxis = {
      categories: props.categories || ['Negligible', 'Minor', 'Moderate', 'Major', 'Critical'],
      title: { text: 'Impact' },
    }
    base.yAxis = {
      categories: props.yCategories || ['Unlikely', 'Possible', 'Likely', 'Almost Certain', 'Certain'],
      title: { text: 'Likelihood' },
      reversed: false,
    }
    base.colorAxis = {
      min: 0,
      minColor: '#E8F5E9',
      maxColor: '#C00000',
    }
    base.tooltip = {
      formatter() {
        const p = this as {
          point: { x: number; y: number; value: number }
          series: { xAxis: { categories: string[] }; yAxis: { categories: string[] } }
        }
        const impact = p.series.xAxis.categories[p.point.x] || p.point.x
        const likelihood = p.series.yAxis.categories[p.point.y] || p.point.y
        return `<b>${likelihood}</b> × <b>${impact}</b><br/>${locale.t('rr.heat_count', 'Count: {n}', { n: p.point.value })}`
      },
    }
  } else if (isGauge) {
    const unit = props.gaugeUnit || 'days'
    const max = props.yAxisMax ?? 30
    base.pane = {
      center: ['50%', '75%'],
      size: '140%',
      startAngle: -90,
      endAngle: 90,
      background: {
        backgroundColor: '#f4f4f4',
        innerRadius: '60%',
        outerRadius: '100%',
        shape: 'arc',
      },
    }
    base.tooltip = { enabled: false }
    base.yAxis = {
      min: props.yAxisMin ?? 0,
      max,
      stops: [
        [0.1, '#119A48'],
        [0.5, '#fbb924'],
        [0.9, '#911C39'],
      ],
      lineWidth: 0,
      tickWidth: 0,
      minorTickInterval: null,
      tickAmount: 2,
      title: { text: null },
      labels: { enabled: false },
    }
    base.series = props.series.map((s) => ({
      type: 'solidgauge',
      name: s.name,
      data: s.data,
      dataLabels: {
        format:
          `<div style="text-align:center"><span style="font-size:2em;color:#5F5F5F;font-weight:bold">{y}</span>` +
          `<br/><span style="font-size:12px;color:#999">${unit}</span></div>`,
        borderWidth: 0,
        y: 20,
        useHTML: true,
      },
    }))
  } else if (!isPie) {
    const longCategoryLabels = chartType === 'bar' && (props.categories || []).some((c) => c.length > 28)
    base.chart = {
      ...(base.chart as Record<string, unknown>),
      ...(longCategoryLabels ? { marginLeft: 240 } : {}),
    }
    base.xAxis = {
      categories: props.categories || [],
      labels: {
        style: { fontSize: chartType === 'bar' ? '11px' : '10px' },
        ...(chartType === 'bar'
          ? {
              useHTML: true,
              formatter(this: { value?: string }) {
                const raw = String(this.value ?? '')
                return `<span title="${raw.replace(/"/g, '&quot;')}">${raw}</span>`
              },
            }
          : {}),
      },
      tickmarkPlacement: isArea ? 'on' : undefined,
    }
    base.yAxis = {
      title: { text: props.yAxisTitle || null },
      allowDecimals: false,
      min: props.yAxisMin,
      max: props.yAxisMax,
    }
  }

  chart = window.Highcharts.chart(el.value, base)
}

async function ensureHighchartsThenRender() {
  try {
    await ensureHighcharts()
  } catch {
    return
  }
  if (cancelled || !window.Highcharts) return
  ensureExportingDefaults()
  render()
}

function setMode(mode: 'chart' | 'table') {
  viewMode.value = mode
  if (mode === 'chart') {
    void ensureHighchartsThenRender()
  } else {
    chart?.destroy()
    chart = null
  }
}

onMounted(() => {
  cancelled = false
  void ensureHighchartsThenRender()
})
watch(
  () => [
    props.title,
    props.type,
    props.categories,
    props.yCategories,
    props.series,
    props.height,
    props.colors,
    props.color,
    props.yAxisMax,
    props.yAxisMin,
    props.yAxisTitle,
    props.gaugeUnit,
    props.exporting,
    viewMode.value,
  ],
  () => {
    if (viewMode.value === 'chart' && window.Highcharts) render()
  },
  { deep: true },
)
onBeforeUnmount(() => {
  cancelled = true
  window.clearInterval(pollTimer)
  chart?.destroy()
  chart = null
})
</script>

<template>
  <div class="portal-highchart-wrap">
    <div v-if="showTableToggle" class="portal-highchart-toolbar">
      <button
        type="button"
        class="portal-highchart-toggle"
        :class="{ 'is-active': viewMode === 'chart' }"
        @click="setMode('chart')"
      >
        {{ locale.t('rr.view_chart', 'Chart') }}
      </button>
      <button
        type="button"
        class="portal-highchart-toggle"
        :class="{ 'is-active': viewMode === 'table' }"
        @click="setMode('table')"
      >
        {{ locale.t('rr.view_table', 'Table') }}
      </button>
    </div>
    <div v-show="viewMode === 'chart'" ref="el" class="portal-highchart" />
    <div v-if="viewMode === 'table'" class="portal-highchart-table-wrap">
      <table class="portal-highchart-table">
        <thead>
          <tr>
            <th>{{ title }}</th>
            <th>{{ locale.t('rr.col_count', 'Count') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, idx) in tableRows"
            :key="idx"
            class="portal-highchart-table__row"
            @click="emit('pointClick', { name: row.label, y: row.value })"
          >
            <td>{{ row.label }}</td>
            <td>{{ row.value }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.portal-highchart-wrap { width: 100%; }
.portal-highchart {
  width: 100%;
  min-height: 240px;
}
.portal-highchart-toolbar {
  display: flex;
  gap: 0.35rem;
  justify-content: flex-end;
  margin-bottom: 0.35rem;
}
.portal-highchart-toggle {
  font: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.2rem 0.55rem;
  border: 1px solid #c5ced8;
  border-radius: 4px;
  background: #fff;
  color: #455a64;
  cursor: pointer;
}
.portal-highchart-toggle.is-active {
  background: #119a48;
  border-color: #119a48;
  color: #fff;
}
.portal-highchart-table-wrap { overflow: auto; max-height: 320px; }
.portal-highchart-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.portal-highchart-table th,
.portal-highchart-table td {
  padding: 0.45rem 0.55rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}
.portal-highchart-table th {
  background: #4a5560;
  color: #fff;
  position: sticky;
  top: 0;
}
.portal-highchart-table__row { cursor: pointer; }
.portal-highchart-table__row:hover { background: #f0fdf4; }
</style>
