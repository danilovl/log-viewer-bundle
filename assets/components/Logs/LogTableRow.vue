<template>
  <tr
    v-memo="[
      entry.timestamp,
      entry.level,
      entry.message,
      entry.channel,
      entry.sql,
      entry.context,
      isBookmarked(entry),
      store.filters.filterSearchHighlight,
      store.filters.filterSearch,
      logPageStatisticEnabled,
    ]"
    class="log-entry"
  >
    <td class="col-bookmark">
      <div class="bookmark-source-container">
        <button class="btn-bookmark" :class="{ active: isBookmarked(entry) }" @click="toggleBookmark(entry)">
          <IconStar :width="18" :height="18" :fill="isBookmarked(entry) ? 'currentColor' : 'none'" />
        </button>
        <div v-if="showSource && entry.sourceId" class="source-link" :title="entry.file">
          <IconFile :width="18" :height="18" />
        </div>
      </div>
    </td>
    <td class="col-timestamp text-muted small">
      <div class="ts">
        <div class="ts-date">{{ parseTs(entry.timestamp).datePart }}</div>
        <div class="ts-time">{{ parseTs(entry.timestamp).timePart }}</div>
      </div>
    </td>
    <td class="col-level">
      <span :class="'level-badge level-' + entry.level.toLowerCase()">{{ entry.level }}</span>
    </td>
    <td v-if="logPageStatisticEnabled" class="col-channel text-muted">{{ entry.channel }}</td>
    <td class="col-message">
      <LogMessage
        :message="entry.message"
        message-class="log-message"
        :highlight-text="store.filters.filterSearchHighlight ? store.filters.filterSearch : ''"
      />
      <AskAi :entry="entry" @open-editor="onOpenAiEditor" />
      <div v-if="entry.sql" class="sql-block">
        <code>{{ entry.sql }}</code>
      </div>
      <LogContext :context="entry.context" />
    </td>
  </tr>
</template>

<script setup lang="ts">
import type { LogEntry, AiChat } from '@/types'
import { parseTimestamp } from '@/utils/format'
import { useLogBookmarks } from '@/stores/log/useLogBookmarks'
import { useLogStore } from '@/stores/useLogStore'
import LogMessage from '@/components/Logs/LogMessage.vue'
import LogContext from '@/components/Logs/LogContext.vue'
import AskAi from '@/components/Logs/AskAi.vue'
import IconStar from '@/components/icons/IconStar.vue'
import IconFile from '@/components/icons/IconFile.vue'

defineProps<{
  entry: LogEntry
  logPageStatisticEnabled: boolean
  showSource?: boolean
}>()

const store = useLogStore()
const { toggleBookmark, isBookmarked } = useLogBookmarks()

const emit = defineEmits<{
  (e: 'open-ai-editor', chat: AiChat, entry: LogEntry): void
}>()

function onOpenAiEditor(chat: AiChat, entry: LogEntry): void {
  emit('open-ai-editor', chat, entry)
}

function parseTs(ts: string): { datePart: string; timePart: string } {
  return parseTimestamp(ts || '')
}
</script>
