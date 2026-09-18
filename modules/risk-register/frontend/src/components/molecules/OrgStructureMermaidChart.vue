<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import type { OrgStructureNode } from '@/lib/settingsApi'
import { ensureMermaid } from '@/lib/ensureMermaid'

const props = defineProps<{
  tree: OrgStructureNode[]
  maxDepth?: number
}>()

const mountEl = ref<HTMLElement | null>(null)
const error = ref<string | null>(null)
const rendering = ref(false)
const nodeCount = ref(0)

declare global {
  interface Window {
    mermaid?: {
      initialize: (config: Record<string, unknown>) => void
      run: (opts: { nodes: HTMLElement[] }) => Promise<void>
    }
  }
}

function escapeLabel(text: string): string {
  return String(text || '')
    .replace(/\\/g, '/')
    .replace(/"/g, "'")
    .replace(/[\[\]]/g, '')
    .replace(/[<>]/g, '')
    .replace(/\r?\n/g, ' ')
    .slice(0, 80)
}

function tierClass(tier?: string | null): string {
  if (!tier) return 'staff'
  if (['dg', 'ddg', 'cos', 'dcos'].includes(tier)) return 'exec'
  if (tier === 'director') return 'director'
  if (tier === 'hod') return 'hod'
  if (tier === 'root') return 'root'
  return 'staff'
}

function buildMermaidSource(tree: OrgStructureNode[], maxDepth = 99): string {
  const lines = [
    'flowchart TB',
    '  classDef root fill:#e2e3e5,stroke:#6c757d,color:#41464b,stroke-width:1.5px',
    '  classDef exec fill:#d1e7dd,stroke:#198754,color:#0f5132,stroke-width:1.5px',
    '  classDef director fill:#cfe2ff,stroke:#0d6efd,color:#084298,stroke-width:1.5px',
    '  classDef hod fill:#fff3cd,stroke:#ffc107,color:#664d03,stroke-width:1.5px',
    '  classDef staff fill:#f8f9fa,stroke:#adb5bd,color:#3a4752,stroke-width:1px',
    '  classDef vacant fill:#f8d7da,stroke:#dc3545,color:#842029,stroke-width:1.5px,stroke-dasharray: 5 4',
  ]

  let count = 0
  const walk = (nodes: OrgStructureNode[], depth: number) => {
    for (const node of nodes) {
      count++
      const id = `N${node.id}`
      const person = node.filled_by?.[0]?.name
      const grade = node.grade_code ? ` · ${node.grade_code}` : ''
      const slots = `A${node.approved_slots}/F${node.filled_slots}`
      const parts = [escapeLabel(node.title)]
      if (person) parts.push(escapeLabel(person) + escapeLabel(grade))
      else if (node.node_type === 'position') parts.push('Vacant')
      parts.push(slots)
      const label = parts.join('<br/>')
      const shape = node.node_type === 'organization' ? `(["${label}"])` : `["${label}"]`
      lines.push(`  ${id}${shape}`)
      const cls = node.filled_slots === 0 && node.node_type === 'position'
        ? 'vacant'
        : tierClass(node.tier)
      lines.push(`  class ${id} ${cls}`)

      if (node.parent_id) {
        lines.push(`  N${node.parent_id} --> ${id}`)
      }

      if (node.children?.length && depth < maxDepth) {
        walk(node.children, depth + 1)
      } else if (node.children?.length && depth >= maxDepth) {
        const moreId = `M${node.id}`
        lines.push(`  ${moreId}["+ ${node.children.length} more…"]`)
        lines.push(`  class ${moreId} staff`)
        lines.push(`  ${id} --> ${moreId}`)
      }
    }
  }

  walk(tree, 0)
  nodeCount.value = count
  if (count === 0) {
    lines.push('  EMPTY(["No structure yet"])')
    lines.push('  class EMPTY root')
  }
  return lines.join('\n')
}

/** Mermaid defaults to max-width:100%, which squash-renders wide org trees into a thin strip. */
function sizeSvgToNatural(root: HTMLElement) {
  const svg = root.querySelector('svg')
  if (!svg) return
  const vb = svg.viewBox?.baseVal
  const w = vb?.width || Number(svg.getAttribute('width')) || 0
  const h = vb?.height || Number(svg.getAttribute('height')) || 0
  svg.removeAttribute('width')
  svg.removeAttribute('height')
  svg.style.maxWidth = 'none'
  svg.style.width = w > 0 ? `${w}px` : 'auto'
  svg.style.height = h > 0 ? `${h}px` : 'auto'
}

async function waitForMermaid(timeoutMs = 8000): Promise<typeof window.mermaid | null> {
  try {
    await ensureMermaid()
  } catch {
    return null
  }
  const start = Date.now()
  while (Date.now() - start < timeoutMs) {
    if (window.mermaid) return window.mermaid
    await new Promise((r) => setTimeout(r, 50))
  }
  return null
}

async function render() {
  error.value = null
  if (!mountEl.value) return
  rendering.value = true
  try {
    const mermaid = await waitForMermaid()
    if (!mermaid) {
      error.value = 'Mermaid library failed to load.'
      mountEl.value.textContent = ''
      return
    }

    mermaid.initialize({
      startOnLoad: false,
      securityLevel: 'loose',
      theme: 'base',
      themeVariables: {
        fontFamily: 'inherit',
        fontSize: '13px',
        primaryColor: '#eef5f0',
        primaryTextColor: '#3a4752',
        lineColor: '#6c757d',
      },
      flowchart: {
        htmlLabels: true,
        curve: 'basis',
        padding: 10,
        nodeSpacing: 24,
        rankSpacing: 36,
        useMaxWidth: false,
      },
    })

    const source = buildMermaidSource(props.tree, props.maxDepth ?? 99)
    mountEl.value.removeAttribute('data-processed')
    mountEl.value.textContent = source
    await nextTick()
    await mermaid.run({ nodes: [mountEl.value] })
    sizeSvgToNatural(mountEl.value)
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Could not render chart'
  } finally {
    rendering.value = false
  }
}

watch(
  () => props.tree,
  () => {
    void render()
  },
  { deep: true },
)

watch(
  () => props.maxDepth,
  () => {
    void render()
  },
)

onMounted(() => void render())

defineExpose({ render })
</script>

<template>
  <div class="org-mermaid">
    <div class="org-mermaid__toolbar text-caption text-medium-emphasis mb-2">
      <span v-if="rendering">Rendering chart…</span>
      <span v-else>{{ nodeCount }} node(s) · scroll to explore · same Mermaid flowchart style as APM workflows</span>
    </div>
    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-2">{{ error }}</v-alert>
    <div class="org-mermaid__pane">
      <div ref="mountEl" class="mermaid org-mermaid__mount">Loading chart…</div>
    </div>
  </div>
</template>

<style scoped>
.org-mermaid__pane {
  overflow: auto;
  max-height: min(78vh, 960px);
  border: 1px solid rgba(58, 71, 82, 0.14);
  border-radius: 0.55rem;
  background:
    linear-gradient(180deg, #f7faf8 0%, #ffffff 40%),
    radial-gradient(circle at top left, rgba(17, 154, 72, 0.05), transparent 40%);
  padding: 1rem;
}
.org-mermaid__mount {
  display: inline-block;
  min-width: max-content;
  min-height: 12rem;
}
.org-mermaid__mount :deep(svg) {
  max-width: none !important;
  height: auto !important;
}
</style>
