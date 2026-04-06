import { ref, computed } from 'vue'
import type { Ref } from 'vue'
import type { LogEntry } from '@/types'

export function useLogPagination(logEntries: Ref<LogEntry[]>, initialLimit: number = 50) {
  const currentPage = ref(1)
  const totalEntries = ref(0)
  const isCountSlow = ref(false)
  const limit = ref(initialLimit)

  const totalPages = computed(() => {
    return Math.ceil(totalEntries.value / limit.value)
  })

  const hasNextPage = computed(() => {
    const hasFullPage = logEntries.value.length === limit.value
    if (isCountSlow.value) {
      return hasFullPage
    }

    return hasFullPage && currentPage.value * limit.value < totalEntries.value
  })

  const hasPrevPage = computed(() => {
    return currentPage.value > 1
  })

  return {
    currentPage,
    totalEntries,
    isCountSlow,
    limit,
    totalPages,
    hasNextPage,
    hasPrevPage,
  }
}
