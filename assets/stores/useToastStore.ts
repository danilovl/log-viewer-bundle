import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Toast {
  id: number
  message: string
  type: 'success' | 'error' | 'warning' | 'info'
  duration?: number
}

export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])
  let nextId = 0

  function add(message: string, type: Toast['type'] = 'info', duration = 5000) {
    if (
      toasts.value.some((t) => {
        return t.message === message && t.type === type
      })
    ) {
      return
    }

    const id = nextId++
    toasts.value.push({ id, message, type, duration })

    if (duration > 0) {
      setTimeout(() => {
        remove(id)
      }, duration)
    }
  }

  function success(message: string, duration?: number) {
    add(message, 'success', duration)
  }

  function error(message: string, duration?: number) {
    add(message, 'error', duration)
  }

  function warning(message: string, duration?: number) {
    add(message, 'warning', duration)
  }

  function info(message: string, duration?: number) {
    add(message, 'info', duration)
  }

  function remove(id: number) {
    const index = toasts.value.findIndex((t) => {
      return t.id === id
    })
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  return {
    toasts,
    success,
    error,
    warning,
    info,
    remove,
  }
})
