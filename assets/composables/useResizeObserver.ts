import { onUnmounted, type Ref, watch } from 'vue'

export function useResizeObserver(target: Ref<HTMLElement | null>, callback: (entries: ResizeObserverEntry[]) => void) {
  const observer = new ResizeObserver(callback)

  watch(
    target,
    (el, oldEl) => {
      if (oldEl) {
        observer.unobserve(oldEl)
      }
      if (el) {
        observer.observe(el)
      }
    },
    { immediate: true },
  )

  onUnmounted(() => {
    observer.disconnect()
  })

  return {
    stop: () => {
      return observer.disconnect()
    },
  }
}
