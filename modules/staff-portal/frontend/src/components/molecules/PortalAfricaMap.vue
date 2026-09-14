<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ensureHighchartsMap } from '@/lib/ensureHighcharts'
import type { DashboardMapPoint } from '@/lib/dashboardApi'

const props = withDefaults(
  defineProps<{
    title: string
    points: DashboardMapPoint[]
    height?: number
    unmapped?: number
    outsideAfrica?: number
    tooltipMode?: 'stations' | 'count'
  }>(),
  {
    height: 420,
    unmapped: 0,
    outsideAfrica: 0,
    tooltipMode: 'count',
  },
)

const el = ref<HTMLDivElement | null>(null)
const loadError = ref<string | null>(null)
let chart: { destroy: () => void } | null = null
let cancelled = false

function seriesPoints(): DashboardMapPoint[] {
  return props.points.filter((p) => p.on_map !== false && Boolean(p.iso2))
}

function caption(): string | null {
  const bits: string[] = []
  if (props.outsideAfrica > 0) {
    bits.push(`${props.outsideAfrica} staff outside Africa are not painted`)
  }
  if (props.unmapped > 0) {
    bits.push(`${props.unmapped} staff could not be matched to a country`)
  }
  return bits.length ? bits.join(' · ') : null
}

function tooltipHtml(point: DashboardMapPoint & { value?: number; name?: string }): string {
  const name = point.name || point.iso2
  const value = Number(point.value) || 0
  let html = `<b>${name}</b>: ${value} staff`
  if (props.tooltipMode === 'stations' && Array.isArray(point.stations) && point.stations.length) {
    html +=
      '<br/>' +
      point.stations
        .map((s) => {
          const loc = s.city ? ` (${s.city})` : ''
          return `${s.name}${loc}: ${s.count}`
        })
        .join('<br/>')
  }
  return html
}

function render() {
  if (!el.value || !window.Highcharts?.mapChart || !window.Highcharts.maps?.['custom/africa']) return
  chart?.destroy()
  chart = null

  const onMap = seriesPoints()
  const values = onMap.map((p) => Number(p.value) || 0)
  const max = values.length ? Math.max(...values, 1) : 1

  chart = window.Highcharts.mapChart(el.value, {
    chart: {
      map: 'custom/africa',
      height: props.height,
      backgroundColor: 'transparent',
    },
    title: {
      text: props.title,
      align: 'left',
      style: { fontSize: '13px', fontWeight: '600', color: '#2c3e50' },
    },
    credits: { enabled: false },
    mapNavigation: {
      enabled: true,
      buttonOptions: { verticalAlign: 'bottom' },
    },
    colorAxis: {
      min: 0,
      max,
      minColor: '#e8f5ee',
      maxColor: '#119A48',
    },
    legend: {
      layout: 'horizontal',
      align: 'center',
      verticalAlign: 'bottom',
    },
    tooltip: {
      useHTML: true,
      formatter() {
        return tooltipHtml(this.point as unknown as DashboardMapPoint)
      },
    },
    series: [
      {
        type: 'map',
        name: 'Staff',
        mapData: window.Highcharts.maps['custom/africa'],
        data: onMap,
        joinBy: ['iso-a2', 'iso2'],
        allAreas: true,
        colorKey: 'value',
        nullColor: '#eef2f0',
        borderColor: '#ffffff',
        borderWidth: 0.6,
        states: {
          hover: { color: '#0d7a3a' },
        },
        dataLabels: {
          enabled: true,
          format: '{point.value}',
          nullFormat: '',
          style: {
            fontSize: '10px',
            fontWeight: '600',
            textOutline: '1px contrast',
          },
        },
      },
    ],
  })
}

async function ensureThenRender() {
  loadError.value = null
  try {
    await ensureHighchartsMap()
  } catch {
    if (!cancelled) loadError.value = 'Could not load the Africa map'
    return
  }
  if (cancelled || !window.Highcharts) return
  render()
}

onMounted(() => {
  cancelled = false
  void ensureThenRender()
})
watch(
  () => [props.title, props.points, props.height, props.tooltipMode],
  () => {
    if (window.Highcharts?.mapChart) render()
  },
  { deep: true },
)
onBeforeUnmount(() => {
  cancelled = true
  chart?.destroy()
  chart = null
})
</script>

<template>
  <div>
    <div v-if="loadError" class="text-medium-emphasis text-body-2">{{ loadError }}</div>
    <div v-else ref="el" class="portal-africa-map" />
    <div v-if="caption()" class="text-caption text-medium-emphasis mt-1">{{ caption() }}</div>
  </div>
</template>

<style scoped>
.portal-africa-map {
  width: 100%;
  min-height: 360px;
}
</style>
