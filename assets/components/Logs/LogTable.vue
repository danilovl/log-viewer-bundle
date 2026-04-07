<template>
  <div class="log-card">
    <div class="log-table-container">
      <table class="log-table">
        <thead>
          <tr>
            <th class="col-bookmark"></th>
            <th class="col-timestamp" style="cursor: pointer" @click="store.toggleSort()">
              {{ t('timestamp') }}
              <span>{{ store.entries.sortDir === 'desc' ? '↓' : '↑' }}</span>
            </th>
            <th class="col-level">{{ t('level') }}</th>
            <th v-if="store.effective.logPageStatisticEnabled" class="col-channel">{{ t('channel') }}</th>
            <th class="col-message">{{ t('message') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filteredEntries.length === 0">
            <td :colspan="getColspan()" class="text-center no-results-cell">
              {{ store.filters.filterSearch ? t('noSearchResults') : t('noEntries') }}
            </td>
          </tr>
          <LogTableRow
            v-for="(entry, index) in filteredEntries"
            :key="index"
            :entry="entry"
            :log-page-statistic-enabled="store.effective.logPageStatisticEnabled"
            :show-source="showSource"
            @open-ai-editor="openAiEditor"
          />
        </tbody>
      </table>
    </div>
    <LogPagination v-if="filteredEntries.length > 0" />
  </div>

  <AiPromptModal
    v-model="promptEditorVisible"
    v-model:prompt="currentPrompt"
    :chat-name="targetChat?.name"
    @send="sendToAi"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import { useAiPrompt } from '@/composables/useAiPrompt'
import { useLogBookmarks } from '@/stores/log/useLogBookmarks'
import type { LogEntry } from '@/types'
import LogPagination from '@/components/Logs/LogPagination.vue'
import LogTableRow from '@/components/Logs/LogTableRow.vue'
import AiPromptModal from '@/components/UI/AiPromptModal.vue'

const store = useLogStore()
const { t } = useI18n()
const { promptEditorVisible, currentPrompt, targetChat, openAiEditor, sendToAi } = useAiPrompt()
const { sourceBookmarks } = useLogBookmarks()

const props = defineProps<{
  showSource?: boolean
}>()

const filteredEntries = computed(() => {
  return store.entries.data as LogEntry[]
})

function getColspan(): number {
  let count = 4

  if (store.effective.logPageStatisticEnabled) {
    count++
  }

  return count
}
</script>
