<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ensureHighcharts } from '@/lib/ensureHighcharts'

declare global {
  interface Window {
    Highcharts?: {
      chart: (el: HTMLElement | string, options: Record<string, unknown>) => { destroy: () => void }
      setOptions?: (opts: Record<string, unknown>) => void
    }
  }
}

const props = withDefaults(
  defineProps<{
    title: string
    type?: 'pie' | 'column' | 'bar' | 'area' | 'solidgauge'
    categories?: string[]
    series: Array<{
      name: string
      data: Array<number | { name: string; y: number; color?: string }>
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
  }>(),
  {
    type: 'column',
    height: 280,
    exporting: true,
    yAxisMin: 0,
  },
)

const el = ref<HTMLDivElement | null>(null)
let chart: { destroy: () => void } | null = null
let pollTimer: number | undefined
let cancelled = false
let exportingConfigured = false

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
  if (!el.value || !window.Highcharts) return
  chart?.destroy()
  chart = null

  const chartType = props.type || 'column'
  const isPie = chartType === 'pie'
  const isGauge = chartType === 'solidgauge'
  const isArea = chartType === 'area'
  const seriesColor = props.color || '#119a48'
  const palette = props.colors || ['#119A48', '#fbb924', '#911C39', '#385CAD', '#C3A366']

  const base: Record<string, unknown> = {
    chart: {
      type: chartType,
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
    tooltip: { shared: !isPie && !isGauge },
    plotOptions: {
      pie: {
        allowPointSelect: true,
        cursor: 'pointer',
        dataLabels: { enabled: true, format: '<b>{point.name}</b>: {point.y} ({point.percentage:.1f}%)' },
      },
      column: {
        color: seriesColor,
        borderRadius: 2,
        dataLabels: { enabled: true, format: '{y}' },
      },
      bar: {
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
      type: chartType,
      name: s.name,
      data: s.data,
      color: isPie ? undefined : s.color || seriesColor,
    })),
  }

  if (isGauge) {
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
    base.xAxis = {
      categories: props.categories || [],
      labels: { style: { fontSize: '10px' } },
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

onMounted(() => {
  cancelled = false
  void ensureHighchartsThenRender()
})
watch(
  () => [
    props.title,
    props.type,
    props.categories,
    props.series,
    props.height,
    props.colors,
    props.color,
    props.yAxisMax,
    props.yAxisMin,
    props.yAxisTitle,
    props.gaugeUnit,
    props.exporting,
  ],
  () => {
    if (window.Highcharts) render()
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
  <div ref="el" class="portal-highchart" />
</template>

<style scoped>
.portal-highchart {
  width: 100%;
  min-height: 240px;
}
</style>
