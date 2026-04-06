import { useTooltipStore } from '@/stores/useTooltipStore'
import type { DirectiveBinding } from 'vue'

export const vTooltip = {
  mounted(el: HTMLElement, binding: DirectiveBinding) {
    const store = useTooltipStore()

    el.addEventListener('mouseenter', (event: MouseEvent) => {
      if (binding.value) {
        store.show(binding.value, event)
      }
    })

    el.addEventListener('mouseleave', () => {
      store.hide()
    })

    el.addEventListener('mousemove', (event: MouseEvent) => {
      store.updatePosition(event)
    })
  },
  updated(el: HTMLElement, binding: DirectiveBinding) {
    const store = useTooltipStore()
    if (store.visible && el === document.querySelector(':hover')) {
      if (binding.value) {
        store.text = binding.value
      } else {
        store.hide()
      }
    }
  },
  unmounted() {
    const store = useTooltipStore()
    store.hide()
  },
}
