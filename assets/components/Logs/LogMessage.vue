<template>
  <div class="log-message-container">
    <div ref="messageRef" :class="[messageClass, { expanded: isExpanded }]" v-html="highlightedMessage"></div>
    <div v-if="isOverflowing || isExpanded" class="log-message-toggle" @click="isExpanded = !isExpanded">
      {{ isExpanded ? t('showLess') : t('showMore') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, computed } from 'vue'
import { useI18n } from '@/i18n/useI18n'
import { useResizeObserver } from '@/composables/useResizeObserver'
import { useLogStore } from '@/stores/useLogStore'
import { useHighlight } from '@/composables/useHighlight'

const props = defineProps<{
  message: string
  messageClass?: string
  highlightText?: string
}>()

const store = useLogStore()
const { t } = useI18n()
const { highlight } = useHighlight()
const isOverflowing = ref(false)
const isExpanded = ref(false)
const messageRef = ref<HTMLElement | null>(null)

const highlightedMessage = computed(() => {
  return highlight(props.message, props.highlightText || '')
})

function checkOverflow() {
  if (messageRef.value && !isExpanded.value) {
    isOverflowing.value = messageRef.value.scrollHeight > messageRef.value.clientHeight
  }
}

useResizeObserver(messageRef, () => {
  checkOverflow()

  return
})

watch(
  () => {
    return props.message
  },
  () => {
    nextTick(() => {
      checkOverflow()
    })
  },
)

watch(isExpanded, (val) => {
  if (!val) {
    nextTick(() => {
      checkOverflow()
    })
  }
})
</script>
