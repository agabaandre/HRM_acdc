import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchPerformancePendingCount } from '@/lib/performanceApi'

export const usePerformancePendingStore = defineStore('performancePending', () => {
  const count = ref(0)
  const loaded = ref(false)

  async function refresh(): Promise<void> {
    try {
      count.value = await fetchPerformancePendingCount()
      loaded.value = true
    } catch {
      // Keep last known count; nav badge is best-effort.
    }
  }

  function setCount(n: number): void {
    count.value = Math.max(0, Number(n) || 0)
    loaded.value = true
  }

  return { count, loaded, refresh, setCount }
})
