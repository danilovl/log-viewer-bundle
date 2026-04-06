<template>
  <div class="bookmarks-view-container">
    <div class="view-header">
      <h2>{{ t('allBookmarks') }}</h2>
    </div>

    <EmptyState v-if="bookmarkSections.length === 0" :title="t('noEntries')" />

    <div v-for="section in bookmarkSections" :key="section.sourceId" class="bookmark-section">
      <div class="view-header section-header">
        <div class="source-info">
          <h3>{{ section.sourceName }}</h3>
          <div v-if="section.sourcePath" class="source-path">{{ section.sourcePath }}</div>
        </div>
      </div>

      <div class="log-card">
        <div class="log-table-container">
          <table class="log-table">
            <thead>
              <tr>
                <th class="col-bookmark" style="width: 24px"></th>
                <th class="col-timestamp">{{ t('timestamp') }}</th>
                <th class="col-level">{{ t('level') }}</th>
                <th v-if="store.effective.logPageStatisticEnabled" class="col-channel">{{ t('channel') }}</th>
                <th class="col-message">{{ t('message') }}</th>
              </tr>
            </thead>
            <tbody>
              <LogTableRow
                v-for="(entry, index) in section.entries"
                :key="index"
                :entry="entry"
                :log-page-statistic-enabled="store.effective.logPageStatisticEnabled"
                @open-ai-editor="openAiEditor"
              />
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <AiPromptModal
      v-model="promptEditorVisible"
      v-model:prompt="currentPrompt"
      :chat-name="targetChat?.name"
      @send="sendToAi"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import { useLogBookmarks } from '@/stores/log/useLogBookmarks'
import { useAiPrompt } from '@/composables/useAiPrompt'
import LogTableRow from '@/components/Logs/LogTableRow.vue'
import AiPromptModal from '@/components/UI/AiPromptModal.vue'
import EmptyState from '@/components/UI/EmptyState.vue'

const store = useLogStore()
const { t } = useI18n()
const { bookmarks } = useLogBookmarks()
const { promptEditorVisible, currentPrompt, targetChat, openAiEditor, sendToAi } = useAiPrompt()

onMounted(() => {
  if (!store.isStructureLoaded) {
    store.loadStructure()
  }
})

const bookmarkSections = computed(() => {
  const result: any[] = []
  const structure = store.structure || []

  const findFileInfo = (nodes: any[], id: string): { name: string; path: string } | null => {
    for (const node of nodes) {
      if (node.files) {
        for (const file of node.files) {
          if (file.id === id) {
            return { name: file.name, path: file.path }
          }
        }
      }

      const folders = node.folders ? (Array.isArray(node.folders) ? node.folders : Object.values(node.folders)) : []
      if (folders.length > 0) {
        const found = findFileInfo(folders, id)
        if (found) {
          return found
        }
      }
    }

    return null
  }

  Object.entries(bookmarks.value).forEach(([sId, entries]) => {
    if (entries.length === 0) {
      return
    }

    const fileInfo = findFileInfo(structure, sId)
    result.push({
      sourceId: sId,
      sourceName: fileInfo?.name || sId,
      sourcePath: fileInfo?.path || '',
      entries: [...entries].sort((a, b) => {
        return b.timestamp.localeCompare(a.timestamp)
      }),
    })
  })

  return result.sort((a, b) => {
    return a.sourceName.localeCompare(b.sourceName)
  })
})
</script>
