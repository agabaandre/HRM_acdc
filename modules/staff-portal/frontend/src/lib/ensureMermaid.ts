import { loadExternalScript } from '@/lib/loadExternalScript'

declare global {
  interface Window {
    mermaid?: {
      initialize: (config: Record<string, unknown>) => void
      run: (opts: { nodes: HTMLElement[] }) => Promise<void>
    }
  }
}

const MERMAID_SRC = 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js'

let mermaidReady: Promise<void> | null = null

/** Lazily load Mermaid (org-structure only — not on login). */
export function ensureMermaid(): Promise<void> {
  if (typeof window !== 'undefined' && window.mermaid) {
    return Promise.resolve()
  }
  if (!mermaidReady) {
    mermaidReady = loadExternalScript(MERMAID_SRC)
  }
  return mermaidReady
}
