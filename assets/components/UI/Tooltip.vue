<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="store.visible && store.text" ref="tooltipRef" class="custom-tooltip" :style="tooltipStyle">
        {{ store.text }}
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTooltipStore } from '@/stores/useTooltipStore'

const store = useTooltipStore()
const tooltipRef = ref<HTMLElement | null>(null)

const tooltipStyle = computed(() => {
  const offset = 12
  let top = store.y + offset
  let left = store.x + offset

  if (tooltipRef.value) {
    const rect = tooltipRef.value.getBoundingClientRect()
    const winWidth = window.innerWidth
    const winHeight = window.innerHeight

    if (left + rect.width > winWidth) {
      left = store.x - rect.width - offset
    }
    if (left < 10) {
      left = 10
    }

    if (top + rect.height > winHeight) {
      top = store.y - rect.height - offset
    }
    if (top < 10) {
      top = 10
    }
  }

  return {
    top: `${top}px`,
    left: `${left}px`,
  }
})
</script>
