<template>
  <div id="live-view">
    <div class="live-header">
      <div class="live-title">
        <IconZap :width="24" :height="24" />
        <h2>{{ t('liveUpdate') }}</h2>
        <span v-if="formattedCalculated" class="live-date" :title="t('dataTime')">
          {{ formattedCalculated }}
        </span>
      </div>
      <div class="live-actions">
        <button
          class="btn btn-icon btn-bookmarks-toggle"
          :class="{ active: store.filters.filterBookmarks }"
          :title="t('bookmarksTitle')"
          @click="store.filters.filterBookmarks = !store.filters.filterBookmarks"
        >
          <IconStar :width="18" :height="18" :fill="store.filters.filterBookmarks ? 'currentColor' : 'none'" />
          <span v-if="sourceBookmarksCount > 0" class="badge badge-bookmarks">
            {{ sourceBookmarksCount }}
          </span>
        </button>
        <button class="btn btn-outline" @click="showFileModal = true">
          <IconFile :width="16" :height="16" />
          {{ t('selectFiles') }}
          <span v-if="store.source.liveIds.length > 0" class="badge">
            {{ store.source.liveIds.length }}
          </span>
        </button>
        <button class="btn btn-outline" @click="showLevelModal = true">
          <IconLayers :width="16" :height="16" />
          {{ t('selectLevels') }}
          <span v-if="store.effective.liveSelectedLevels.length > 0" class="badge">
            {{ store.effective.liveSelectedLevels.length }}
          </span>
        </button>
        <LevelSelectionModal
          v-model="showLevelModal"
          :selected-levels="store.effective.liveSelectedLevels"
          @toggle="toggleLevel"
          @select-all="selectAllLevels"
          @reset-all="resetAllLevels"
        />
        <RefreshCountdown
          v-if="store.effective.showLiveRefreshCountdown"
          :countdown="store.live.refreshCountdown"
          show-label
        />
        <div class="live-status" :class="{ 'is-loading': store.live.loading }">
          <div class="status-dot"></div>
          {{ store.live.loading ? t('loading') : t('live') }}
        </div>
      </div>
    </div>

    <div class="live-container" style="position: relative">
      <div v-if="store.live.loadingDelayed" class="dashboard-loader-overlay">
        <div class="dashboard-loader">
          <div class="spinner"></div>
          <span class="dashboard-loader-text">{{ t('loading') }}...</span>
        </div>
      </div>

      <template v-if="store.filters.filterBookmarks">
        <LogTable />
      </template>

      <div v-else class="live-list-wrapper">
        <TransitionGroup name="list" tag="div" class="live-list">
          <div
            v-for="entry in store.live.data"
            :key="getEntryKey(entry)"
            class="live-entry-card"
            :class="[
              'border-level-' + entry.level.toLowerCase(),
              'border-status-' + (entryStatuses[getEntryKey(entry)] || 'old'),
            ]"
          >
            <div class="entry-header">
              <span :class="'level-badge  level-' + entry.level.toLowerCase()">{{ entry.level }}</span>
              <span class="entry-timestamp">{{ entry.timestamp }}</span>
              <span v-if="entry.channel" class="entry-channel">{{ entry.channel }}</span>

              <div class="entry-file-info" :title="entry.file">
                <button class="btn-bookmark" :class="{ active: isBookmarked(entry) }" @click="toggleBookmark(entry)">
                  <IconStar :width="14" :height="14" :fill="isBookmarked(entry) ? 'currentColor' : 'none'" />
                </button>
                <IconFile :width="12" :height="12" class="entry-file-icon" />
                <span class="entry-file-name">{{ getFileName(entry.file) }}</span>
                <router-link
                  v-if="entry.sourceId"
                  :to="{ name: 'logs', params: { sourceId: entry.sourceId } }"
                  class="entry-source-link"
                  :title="t('viewLogs')"
                >
                  <IconSearch :width="12" :height="12" />
                </router-link>
              </div>
            </div>

            <LogMessage :message="entry.message" message-class="entry-message" />
            <AskAi :entry="entry" @open-editor="openAiEditor" />

            <div v-if="entry.sql" class="entry-sql">
              <code>{{ entry.sql }}</code>
            </div>

            <LogContext :context="entry.context" />
          </div>
        </TransitionGroup>

        <div v-if="store.live.data.length === 0 && !store.live.loading" class="no-entries">
          <div class="no-entries-icon">
            <IconInbox :width="48" :height="48" />
          </div>
          <p>{{ t('noEntries') }}</p>
        </div>
      </div>
    </div>

    <AiPromptModal
      v-model="promptEditorVisible"
      v-model:prompt="currentPrompt"
      :chat-name="targetChat?.name"
      @send="sendToAi"
    />

    <FileSelectionModal
      v-model="showFileModal"
      :title="t('selectFiles')"
      :selected-ids="store.source.liveIds"
      @toggle="toggleFile"
      @selectAll="selectAllFiles"
      @resetAll="resetAllFiles"
    />

    <LevelSelectionModal
      v-model="showLevelModal"
      :selected-levels="store.effective.liveSelectedLevels"
      @toggle="toggleLevel"
      @selectAll="selectAllLevels"
      @resetAll="resetAllLevels"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import type { LogEntry } from '../types'
import { useLogStore } from '../stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import { useAiPrompt } from '@/composables/useAiPrompt'
import { getFileName, formatDateTime } from '@/utils/format'
import { useLogBookmarks } from '@/stores/log/useLogBookmarks'
import { logLevels } from '@/utils/constants'
import IconZap from '@/components/icons/IconZap.vue'
import IconInbox from '@/components/icons/IconInbox.vue'
import IconSearch from '@/components/icons/IconSearch.vue'
import IconFile from '@/components/icons/IconFile.vue'
import IconLayers from '@/components/icons/IconLayers.vue'
import IconStar from '@/components/icons/IconStar.vue'
import LogTable from '@/components/Logs/LogTable.vue'
import RefreshCountdown from '@/components/UI/RefreshCountdown.vue'
import FileSelectionModal from '@/components/UI/FileSelectionModal.vue'
import LogMessage from '@/components/Logs/LogMessage.vue'
import LogContext from '@/components/Logs/LogContext.vue'
import AskAi from '@/components/Logs/AskAi.vue'
import AiPromptModal from '@/components/UI/AiPromptModal.vue'

import LevelSelectionModal from '@/components/UI/LevelSelectionModal.vue'

const store = useLogStore()
const { toggleBookmark, isBookmarked, sourceBookmarksCount } = useLogBookmarks()

const { t } = useI18n()
const { promptEditorVisible, currentPrompt, targetChat, openAiEditor, sendToAi } = useAiPrompt()
const settingsStore = store.config.settingsStore

const showFileModal = ref(false)
const showLevelModal = ref(false)

const formattedCalculated = computed(() => {
  return formatDateTime(store.live.calculatedAt)
})

const entryStatuses = reactive<Record<string, 'new' | 'old'>>({})

function getEntryKey(entry: LogEntry): string {
  return entry.timestamp + entry.message + entry.level + entry.channel
}

function toggleFile(id: string): void {
  const index = store.source.liveIds.indexOf(id)
  if (index === -1) {
    store.source.liveIds.push(id)
  } else {
    store.source.liveIds.splice(index, 1)
  }
}

function selectAllFiles(ids: string[]): void {
  store.source.liveIds = [...ids]
}

function resetAllFiles(): void {
  store.source.liveIds = []
}

function toggleLevel(level: string): void {
  const currentLevels = store.effective.liveSelectedLevels ? [...store.effective.liveSelectedLevels] : []
  const index = currentLevels.indexOf(level)

  if (index === -1) {
    currentLevels.push(level)
  } else {
    currentLevels.splice(index, 1)
  }

  settingsStore.liveLogPageLevels = currentLevels
}

function selectAllLevels(): void {
  settingsStore.liveLogPageLevels = [...logLevels]
}

function resetAllLevels(): void {
  settingsStore.liveLogPageLevels = []
}

watch(
  () => {
    return store.source.liveIds
  },
  () => {
    store.live.data = []
    store.loadLiveEntries()

    return
  },
  { deep: true },
)

watch(
  () => {
    return store.effective.liveSelectedLevels
  },
  () => {
    store.live.data = []
    store.loadLiveEntries()

    return
  },
)

watch(
  () => {
    return store.live.data
  },
  (newVal, oldVal) => {
    const currentKeys = newVal.map((entry) => {
      return getEntryKey(entry)
    })

    const oldKeys = oldVal
      ? oldVal.map((entry) => {
          return getEntryKey(entry)
        })
      : []

    currentKeys.forEach((key) => {
      if (!oldKeys.includes(key)) {
        entryStatuses[key] = 'new'
      } else {
        entryStatuses[key] = 'old'
      }
    })

    Object.keys(entryStatuses).forEach((key) => {
      if (!currentKeys.includes(key)) {
        delete entryStatuses[key]
      }
    })

    return
  },
  { deep: true },
)

onMounted(() => {
  if (store.isStructureLoaded) {
    store.loadLiveEntries()
  }
  store.startLiveAutoRefresh()

  return
})

onUnmounted(() => {
  store.stopLiveAutoRefresh()

  return
})
</script>
