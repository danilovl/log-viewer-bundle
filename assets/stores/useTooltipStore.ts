import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useTooltipStore = defineStore('tooltip', () => {
  const visible = ref(false)
  const text = ref('')
  const x = ref(0)
  const y = ref(0)

  function show(newText: string, event: MouseEvent) {
    text.value = newText
    visible.value = true
    updatePosition(event)
  }

  function hide() {
    visible.value = false
  }

  function updatePosition(event: MouseEvent) {
    x.value = event.clientX
    y.value = event.clientY
  }

  return {
    visible,
    text,
    x,
    y,
    show,
    hide,
    updatePosition,
  }
})
