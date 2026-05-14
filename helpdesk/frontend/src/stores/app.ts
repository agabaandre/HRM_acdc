import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export const useAppStore = defineStore('app', () => {
  const health = ref<Record<string, unknown> | null>(null)
  const healthError = ref<string | null>(null)

  async function fetchHealth() {
    healthError.value = null
    try {
      const { data } = await axios.get('/api/v1/health')
      health.value = data
    } catch (e: unknown) {
      healthError.value = e instanceof Error ? e.message : 'Health check failed'
    }
  }

  return { health, healthError, fetchHealth }
})
