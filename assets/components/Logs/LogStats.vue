<template>
  <div>
    <div class="log-stats-row">
      <div class="stat-card stat-card-inline">
        <div class="stat-card-title">{{ t('filePath') }}:</div>
        <div v-tooltip="store.source.path" class="stat-card-value">{{ store.source.path || '-' }}</div>
      </div>

      <div class="stat-card stat-card-inline">
        <div class="stat-card-title">{{ t('fileSize') }}:</div>
        <div class="stat-card-value">{{ fileSizeFormatted }}</div>
      </div>

      <div v-if="store.source.parserType" class="stat-card stat-card-inline">
        <div class="stat-card-title">{{ t('parser') }}:</div>
        <div class="stat-card-value">{{ store.source.parserType }}</div>
      </div>

      <div v-if="store.source.host" class="stat-card stat-card-inline">
        <div class="stat-card-title">{{ t('remoteHost') }}:</div>
        <div class="stat-card-value">{{ store.source.host }}</div>
      </div>

      <div class="log-stats-actions">
        <button
          class="btn-icon btn-bookmarks-toggle"
          :class="{ active: store.filters.filterBookmarks }"
          :title="t('bookmarksTitle')"
          @click="store.filters.filterBookmarks = !store.filters.filterBookmarks"
        >
          <IconStar :width="16" :height="16" :fill="store.filters.filterBookmarks ? 'currentColor' : 'none'" />
          <span v-if="sourceBookmarksCount > 0" class="badge-bookmarks">
            {{ sourceBookmarksCount }}
          </span>
        </button>
        <button
          v-if="store.source.canDownload"
          class="btn-icon btn-download"
          :title="t('download')"
          @click="store.downloadFile(store.source.id)"
        >
          <IconDownload :width="16" :height="16" />
        </button>
        <button
          v-if="store.source.canDelete"
          class="btn-icon btn-delete"
          :title="t('delete')"
          @click="store.deleteFile(store.source.id, getFileName(store.source.path))"
        >
          <IconDelete :width="16" :height="16" />
        </button>
      </div>
    </div>

    <div v-if="store.effective.logPageStatisticEnabled" class="stats-grid small log-stats-grid">
      <div class="stat-card">
        <div class="stat-card-title">{{ t('totalEntries') }}</div>
        <div class="stat-card-value log-stats-value">
          <DotLoader v-if="store.entries.statsLoadingDelayed" />
          <template v-else>{{ entriesCountFormatted }}</template>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-title">{{ t('errors') }}</div>
        <div class="stat-card-value log-stats-value" style="color: var(--danger)">
          <DotLoader v-if="store.entries.statsLoadingDelayed" />
          <template v-else>{{ store.entries.errorCount?.toLocaleString() ?? '-' }}</template>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-title">{{ t('topChannel') }}</div>
        <div class="stat-card-value log-stats-value">
          <DotLoader v-if="store.entries.statsLoadingDelayed" />
          <template v-else>{{ store.entries.topChannel || '-' }}</template>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-card-title">{{ t('lastModified') }}</div>
        <div class="stat-card-value log-stats-modified">
          <DotLoader v-if="store.entries.statsLoadingDelayed" />
          <template v-else>{{ formattedModified }}</template>
        </div>
      </div>
      <div v-if="store.entries.stats?.calculatedAt" class="stat-card">
        <div class="stat-card-title">{{ t('dataTime') }}</div>
        <div class="stat-card-value log-stats-modified">
          <DotLoader v-if="store.entries.statsLoadingDelayed" />
          <template v-else>{{ formattedCalculated }}</template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import { useLogBookmarks } from '@/stores/log/useLogBookmarks'
import { formatBytes, formatDateTime, getFileName } from '@/utils/format'
import IconDownload from '@/components/icons/IconDownload.vue'
import IconDelete from '@/components/icons/IconDelete.vue'
import IconStar from '@/components/icons/IconStar.vue'
import DotLoader from '@/components/UI/DotLoader.vue'

const store = useLogStore()
const { t } = useI18n()
const { sourceBookmarksCount } = useLogBookmarks()

const entriesCountFormatted = computed(() => {
  if (!store.entries.stats) {
    return '-'
  }

  return store.entries.stats?.total?.toLocaleString() ?? '-'
})

const fileSizeFormatted = computed(() => {
  return formatBytes(store.source.size || 0)
})

const formattedModified = computed(() => {
  return formatDateTime(store.entries.stats?.updatedAt) || t('na')
})

const formattedCalculated = computed(() => {
  return formatDateTime(store.entries.stats?.calculatedAt) || t('na')
})
</script>
