import { watch } from 'vue'
import type { Ref } from 'vue'

export function createDelayedWatcher(loadingRef: Ref<boolean>, delayedRef: Ref<boolean>, delay = 100): void {
  let timeoutId: number | null = null

  watch(loadingRef, (val) => {
    if (val) {
      timeoutId = window.setTimeout(() => {
        if (loadingRef.value) {
          delayedRef.value = true
        }

        return
      }, delay)
    } else {
      if (timeoutId) {
        window.clearTimeout(timeoutId)
        timeoutId = null
      }

      delayedRef.value = false
    }

    return
  })

  return
}
