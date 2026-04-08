<template>
  <router-link
    v-if="showReaderButton"
    :to="{ name: 'file-reader', params: { sourceId: entry.sourceId }, query: { line: entry.lineNumber } }"
    target="_blank"
    class="view-reader-btn"
    :title="t('viewInReader')"
  >
    <IconEye :width="14" :height="14" />
    <span>{{ t('viewInReader') }}</span>
  </router-link>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import type { LogEntry } from '@/types'
import IconEye from '@/components/icons/IconEye.vue'

const props = defineProps<{
  entry: LogEntry
}>()

const { t } = useI18n()
const store = useLogStore()

const showReaderButton = computed(() => {
  return (
    store.effective.showReaderButton &&
    props.entry.sourceId &&
    props.entry.lineNumber !== undefined &&
    props.entry.lineNumber !== null
  )
})
</script>
