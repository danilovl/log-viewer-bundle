<template>
  <div id="dashboard-view">
    <template v-if="store.effective.dashboardPageStatisticEnabled">
      <DashboardSkeleton v-if="store.dashboard.loadingDelayed && !store.dashboard.stats" />

      <div v-if="store.dashboard.stats" class="dashboard-stats-wrapper">
        <div class="dashboard-info-row">
          <div class="dashboard-info-actions">
            <div v-if="store.effective.showDashboardRefreshCountdown" class="dashboard-action-item refresh-countdown">
              <IconClock :width="14" :height="14" />
              <span class="countdown-label">{{ t('refreshIn') }}:</span>
              <span class="countdown-value">{{ store.dashboard.refreshCountdown }}s</span>
            </div>
            <button class="dashboard-action-item btn-refresh" @click="handleRefresh">
              <IconRefresh :width="14" :height="14" />
              <span>{{ t('refresh') }}</span>
            </button>
          </div>
          <div v-if="formattedCalculated" class="dashboard-date">{{ t('dataTime') }}: {{ formattedCalculated }}</div>
        </div>

        <div v-if="store.dashboard.loadingDelayed" class="dashboard-loader-overlay">
          <div class="dashboard-loader">
            <div class="spinner"></div>
            <span>{{ t('loading') }}...</span>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-card-title">{{ t('totalLogs') }}</div>
              <div class="stat-card-value">{{ store.dashboard.stats?.totalFiles }}</div>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(99, 102, 241, 0.1); color: #6366f1">
              <IconFolder :width="24" :height="24" />
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-card-title">{{ t('totalEntries') }}</div>
              <div class="stat-card-value">
                {{ store.dashboard.stats?.totalEntries?.toLocaleString() }}
              </div>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(16, 185, 129, 0.1); color: #10b981">
              <IconFile :width="24" :height="24" />
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-card-title">{{ t('errorsCritical') }}</div>
              <div class="stat-card-value" style="color: var(--danger)">
                {{ store.dashboard.errorCount?.toLocaleString() }}
              </div>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(239, 68, 68, 0.1); color: #ef4444">
              <IconAlert :width="24" :height="24" />
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-card-content">
              <div class="stat-card-title">{{ t('totalSize') }}</div>
              <div class="stat-card-value">{{ formatBytes(store.dashboard.stats?.totalSize || 0) }}</div>
            </div>
            <div class="stat-icon-wrapper" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b">
              <IconPackage :width="24" :height="24" />
            </div>
          </div>
        </div>

        <div class="charts-grid">
          <LogChart />
        </div>

        <div v-if="sortedLevels.length > 0" class="dashboard-section">
          <h4>{{ t('entriesByLevel') }}</h4>
          <div class="stats-grid small">
            <div v-for="(item, idx) in sortedLevels" :key="item.level" class="stat-card">
              <div class="stat-card-content">
                <div class="stat-card-title">{{ item.level }}</div>
                <div class="stat-card-value" :style="{ color: palette[idx % palette.length] }">
                  {{ item.count.toLocaleString() }}
                </div>
              </div>
              <div
                class="stat-icon-wrapper"
                :style="{
                  background: iconBg(palette[idx % palette.length]),
                  color: palette[idx % palette.length],
                }"
              >
                <IconLayers :width="18" :height="18" />
              </div>
            </div>
          </div>
        </div>

        <div v-if="sortedChannels.length > 0" class="dashboard-section">
          <h4>{{ t('entriesByChannel') }}</h4>
          <div class="stats-grid small">
            <div v-for="(item, idx) in sortedChannels" :key="item.channel" class="stat-card">
              <div class="stat-card-content">
                <div class="stat-card-title">{{ item.channel }}</div>
                <div class="stat-card-value" :style="{ color: palette[(idx + 2) % palette.length] }">
                  {{ item.count.toLocaleString() }}
                </div>
              </div>
              <div
                class="stat-icon-wrapper"
                :style="{
                  background: iconBg(palette[(idx + 2) % palette.length]),
                  color: palette[(idx + 2) % palette.length],
                }"
              >
                <IconMenu :width="18" :height="18" />
              </div>
            </div>
          </div>
        </div>

        <div class="dashboard-section">
          <h4>{{ t('entriesBySource') }}</h4>
          <div class="stats-grid small">
            <div
              v-for="(source, idx) in sortedSources"
              :key="source.id || source.name"
              v-tooltip="source.path"
              class="stat-card"
              :style="{ cursor: sourceClickable(source) ? 'pointer' : 'default' }"
              @click="handleSourceClick(source)"
            >
              <div class="stat-card-content">
                <div class="stat-card-title">{{ sourceTitle(source) }}</div>
                <div class="stat-card-value" :style="{ color: sourceColor(source, idx) }">
                  {{ source.total.toLocaleString() }}
                </div>
                <div v-if="source.size !== undefined" class="source-subtitle">
                  {{ formatBytes(source.size) }}
                </div>
                <div v-if="source.calculatedAt" class="source-subtitle">
                  {{ t('dataTime') }}: {{ source.calculatedAt }}
                </div>
              </div>
              <div
                class="stat-icon-wrapper"
                :style="{
                  background: iconBg(sourceColor(source, idx)),
                  color: sourceColor(source, idx),
                }"
              >
                <IconFile :width="18" :height="18" />
              </div>
              <div class="source-card-actions">
                <button
                  class="action-btn view-btn"
                  :disabled="!source.isReadable"
                  v-tooltip="source.isReadable ? t('viewContent') : t('noReadPermission')"
                  @click.stop="handleViewContent(source)"
                >
                  <IconEye :width="14" :height="14" />
                </button>
                <button
                  v-if="source.canDownload"
                  class="action-btn download-btn"
                  :disabled="!source.isDownloadable"
                  v-tooltip="source.isDownloadable ? t('download') : t('noDownloadPermission')"
                  @click.stop="handleDownload(source)"
                >
                  <IconDownload :width="14" :height="14" />
                </button>
                <button
                  v-if="source.canDelete"
                  class="action-btn delete-btn"
                  :disabled="!source.isDeletable"
                  v-tooltip="source.isDeletable ? t('delete') : t('noDeletePermission')"
                  @click.stop="handleDelete(source)"
                >
                  <IconDelete :width="14" :height="14" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <div v-if="bookmarkSources.length > 0" class="dashboard-section">
          <h4>{{ t('bookmarks') }}</h4>
          <div class="stats-grid small">
            <div
              v-for="(source, idx) in bookmarkSources"
              :key="'bm-' + (source.id || source.name)"
              v-tooltip="source.path"
              class="stat-card"
              style="cursor: pointer"
              @click="handleBookmarkClick(source)"
            >
              <div class="stat-card-content">
                <div class="stat-card-title">{{ source.name }}</div>
                <div class="stat-card-value" style="color: var(--warning)">
                  {{ source.bookmarkCount.toLocaleString() }}
                </div>
              </div>
              <div
                class="stat-icon-wrapper"
                :style="{
                  background: iconBg('#f59e0b'),
                  color: '#f59e0b',
                }"
              >
                <IconStar :width="18" :height="18" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="dashboard-hint">
      <span class="dashboard-hint-text">
        {{ t('enableDashboardHint') }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useLogStore } from '@/stores/useLogStore'
import { useLogBookmarks } from '@/stores/log/useLogBookmarks'
import { useI18n } from '@/i18n/useI18n'
import { formatBytes, formatDateTime } from '@/utils/format'
import { palette, iconBg } from '@/utils/color'
import type { SourceInfo } from '@/types'
import IconFolder from '@/components/icons/IconFolder.vue'
import IconFile from '@/components/icons/IconFile.vue'
import IconAlert from '@/components/icons/IconAlert.vue'
import IconPackage from '@/components/icons/IconPackage.vue'
import IconClock from '@/components/icons/IconClock.vue'
import IconRefresh from '@/components/icons/IconRefresh.vue'
import IconLayers from '@/components/icons/IconLayers.vue'
import IconMenu from '@/components/icons/IconMenu.vue'
import IconStar from '@/components/icons/IconStar.vue'
import IconEye from '@/components/icons/IconEye.vue'
import IconDownload from '@/components/icons/IconDownload.vue'
import IconDelete from '@/components/icons/IconDelete.vue'
import LogChart from '@/components/Dashboard/LogChart.vue'
import DashboardSkeleton from '@/components/Dashboard/DashboardSkeleton.vue'

const store = useLogStore()
const { bookmarks } = useLogBookmarks()
const router = useRouter()
const { t } = useI18n()

const sortedSources = computed(() => {
  if (!store.dashboard.stats) {
    return []
  }

  return [...(store.dashboard.stats?.sources || [])].sort((a, b) => {
    return b.total - a.total
  })
})

const sortedLevels = computed(() => {
  const levels = store.dashboard.stats?.levels || {}

  return Object.entries(levels)
    .sort(([, a], [, b]) => {
      return b - a
    })
    .map(([level, count]) => {
      return { level, count }
    })
})

const sortedChannels = computed(() => {
  const channels = store.dashboard.stats?.channels || {}

  return Object.entries(channels)
    .sort(([, a], [, b]) => {
      return b - a
    })
    .map(([channel, count]) => {
      return { channel, count }
    })
})

const bookmarkSources = computed(() => {
  if (!store.dashboard.stats) {
    return []
  }

  const result: any[] = []
  const sources = store.dashboard.stats.sources || []

  Object.entries(bookmarks.value).forEach(([sId, entries]) => {
    const source = sources.find((s) => {
      return s.id === sId
    })
    if (source) {
      result.push({
        ...source,
        bookmarkCount: entries.length,
      })
    }
  })

  return result.sort((a, b) => {
    return b.bookmarkCount - a.bookmarkCount
  })
})

const formattedCalculated = computed(() => {
  return formatDateTime(store.dashboard.stats?.calculatedAt)
})

function sourceClickable(source: SourceInfo): boolean {
  return source.isValid !== false && !source.isEmpty && !source.isTooLarge
}

function sourceTitle(source: SourceInfo): string {
  if (source.isTooLarge) {
    return source.name + ` (${t('tooLarge')})`
  }

  if (source.isValid === false) {
    return source.name + ` (${t('invalidFormat')})`
  }

  if (source.isEmpty) {
    return source.name + ` (${t('empty')})`
  }

  return source.name
}

function sourceColor(source: SourceInfo, idx: number): string {
  if (source.isTooLarge) {
    return '#f59e0b'
  }

  if (source.isValid === false) {
    return '#ef4444'
  }

  return palette[idx % palette.length]
}

function handleSourceClick(source: SourceInfo): void {
  if (sourceClickable(source)) {
    router.push({ name: 'logs', params: { sourceId: source.id } })
  }
}

function handleBookmarkClick(source: SourceInfo): void {
  router.push({
    name: 'logs',
    params: { sourceId: source.id },
    query: { bookmarks: '1' },
  })
}

function handleRefresh(): void {
  store.loadDashboardStats()
}

function handleViewContent(source: SourceInfo): void {
  const url = router.resolve({ name: 'file-reader', params: { sourceId: source.id } }).href
  window.open(url, '_blank')
}

function handleDownload(source: SourceInfo): void {
  store.downloadFile(source.id)
}

function handleDelete(source: SourceInfo): void {
  store.deleteFile(source.id, source.name)
}

onMounted(() => {
  if (store.config.dashboardPageStatisticEnabled) {
    store.loadDashboardStats()
    store.startDashboardAutoRefresh()
  }
})

onUnmounted(() => {
  store.stopDashboardAutoRefresh()
})
</script>
