import { defineStore } from 'pinia'
import { ref } from 'vue'
import { TOAST_DEFAULT_DURATION, TOAST_DEFAULT_TITLES } from './constants'
import type { ToastInput, ToastRecord } from './types'

let toastCounter = 0

function nextToastId() {
  toastCounter += 1
  return `toast-${Date.now()}-${toastCounter}`
}

export const useToastStore = defineStore('toast', () => {
  const items = ref<ToastRecord[]>([])

  function show(input: ToastInput): string {
    const id = nextToastId()
    const type = input.type ?? 'info'
    const record: ToastRecord = {
      id,
      title: input.title ?? TOAST_DEFAULT_TITLES[type],
      message: input.message,
      type,
      duration: input.duration ?? TOAST_DEFAULT_DURATION,
      visible: false,
    }

    items.value = [...items.value, record]

    window.setTimeout(() => {
      items.value = items.value.map((item) =>
        item.id === id ? { ...item, visible: true } : item,
      )
    }, 100)

    if (record.duration > 0) {
      window.setTimeout(() => {
        dismiss(id)
      }, record.duration)
    }

    return id
  }

  function dismiss(id: string) {
    items.value = items.value.map((item) =>
      item.id === id ? { ...item, visible: false } : item,
    )
    window.setTimeout(() => remove(id), 300)
  }

  function remove(id: string) {
    items.value = items.value.filter((item) => item.id !== id)
  }

  return { items, show, dismiss, remove }
})
