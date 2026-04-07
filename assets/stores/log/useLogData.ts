import { ref } from 'vue'
import type { LogStats, DashboardStats, TimelineFormat } from '@/types'
import {
  fetchDashboardStats,
  fetchLogStats,
  fetchLogEntries,
  fetchLogEntriesCount,
  fetchNewLogEntries,
  fetchGlobalSearch,
} from '@/services/api'
import { createDelayedWatcher } from './useDelayedLoading'

interface SourceState {
  id: string
  ids: string[]
  liveIds: string[]
  path: string
  host: string
  canDelete: boolean
  canDownload: boolean
  parserType: string
  size: number
}

interface PaginationState {
  currentPage: number
  totalEntries: number
  isCountSlow: boolean
  limit: number
}

interface FiltersState {
  filterLevel: string
  filterChannel: string
  filterSearch: string
  filterDateFrom: string
  filterDateTo: string
  filterSearchRegex: boolean
  filterSearchCaseSensitive: boolean
  filterBookmarks: boolean
  channels: string[]
  levels: string[]
  syncFiltersFromUrl: () => void
  syncFiltersToUrl: () => void
}

interface LogDataOptions {
  source: SourceState
  logEntries: any
  liveEntries: any
  sortDir: any
  logPageStatisticEnabled: any
  liveSelectedLevels: any
  pagination: PaginationState
  filters: FiltersState
  sourceBookmarks: any
}

export function useLogData(options: LogDataOptions) {
  const {
    source,
    logEntries,
    liveEntries,
    sortDir,
    logPageStatisticEnabled,
    liveSelectedLevels,
    pagination,
    filters,
    sourceBookmarks,
  } = options

  const logStats = ref<LogStats | null>(null)
  const dashboardStats = ref<DashboardStats | null>(null)
  const responseTime = ref<number | null>(null)
  const liveCalculatedAt = ref<string | null>(null)
  const dashboardTimelineFormat = ref<TimelineFormat>('day')

  const loadingRefs = {
    dashboard: ref(false),
    log: ref(false),
    pagination: ref(false),
    stats: ref(false),
    count: ref(false),
    live: ref(false),
  }

  const loadingDelayedRefs = {
    dashboard: ref(false),
    log: ref(false),
    pagination: ref(false),
    stats: ref(false),
    count: ref(false),
    live: ref(false),
  }

  Object.keys(loadingRefs).forEach((key) => {
    const k = key as keyof typeof loadingRefs
    createDelayedWatcher(loadingRefs[k], loadingDelayedRefs[k], k === 'stats' ? 50 : 100)
  })

  let currentEntriesRequest = 0
  let currentStatsRequest = 0
  let currentCountRequest = 0

  async function loadDashboardStats(): Promise<void> {
    loadingRefs.dashboard.value = true
    try {
      const result = await fetchDashboardStats(dashboardTimelineFormat.value)
      dashboardStats.value = result.data
      responseTime.value = result.duration
    } catch (e) {
      console.error('Failed  to  load  dashboard  stats', e)
    } finally {
      loadingRefs.dashboard.value = false
    }
  }

  async function changeDashboardTimelineFormat(format: TimelineFormat): Promise<void> {
    dashboardTimelineFormat.value = format
    await loadDashboardStats()
  }

  async function loadLogStats(): Promise<void> {
    if (filters.filterBookmarks) {
      pagination.totalEntries = sourceBookmarks.value.length

      return
    }

    const requestId = ++currentStatsRequest
    loadingRefs.stats.value = true
    try {
      const result = await fetchLogStats(
        source.id,
        filters.filterLevel,
        filters.filterChannel,
        filters.filterSearch,
        filters.filterDateFrom,
        filters.filterDateTo,
        filters.filterSearchRegex,
        filters.filterSearchCaseSensitive,
      )
      if (requestId !== currentStatsRequest) {
        return
      }

      logStats.value = result.data
      pagination.totalEntries = result.data.total
      responseTime.value = result.duration
      source.path = result.path || source.path
      source.parserType = result.parserType || ''
      source.host = result.host || ''
      source.size = result.data.size || 0
      source.canDelete = result.canDelete
      source.canDownload = result.canDownload

      if (result.duration > 500) {
        pagination.isCountSlow = true
      }

      Object.keys(result.data.channels)
        .sort()
        .forEach((ch) => {
          if (!filters.channels.includes(ch)) {
            filters.channels.push(ch)
          }

          return
        })

      Object.keys(result.data.levels)
        .sort()
        .forEach((level) => {
          if (!filters.levels.includes(level)) {
            filters.levels.push(level)
          }

          return
        })
    } catch (e) {
      console.error('Failed  to  load  log  stats', e)
    } finally {
      if (requestId === currentStatsRequest) {
        loadingRefs.stats.value = false
      }
    }
  }

  async function loadCount(): Promise<void> {
    if (filters.filterBookmarks) {
      pagination.totalEntries = sourceBookmarks.value.length

      return
    }

    if (!source.id) {
      return
    }

    const requestId = ++currentCountRequest
    loadingRefs.count.value = true
    try {
      const result = await fetchLogEntriesCount(
        source.id,
        filters.filterLevel,
        filters.filterChannel,
        filters.filterSearch,
        filters.filterDateFrom,
        filters.filterDateTo,
        filters.filterSearchRegex,
        filters.filterSearchCaseSensitive,
      )
      if (requestId !== currentCountRequest) {
        return
      }

      pagination.totalEntries = result.totalCount

      if (result.duration > 500) {
        pagination.isCountSlow = true
      }
    } catch (e) {
      console.error('Failed  to  load  count', e)
    } finally {
      if (requestId === currentCountRequest) {
        loadingRefs.count.value = false
      }
    }
  }

  async function loadEntries(
    withStats: boolean = false,
    fromUrl: boolean = false,
    isPagination: boolean = false,
  ): Promise<void> {
    if (fromUrl) {
      filters.syncFiltersFromUrl()
      pagination.isCountSlow = false
    }

    if (filters.filterBookmarks) {
      loadingRefs.log.value = true
      const offset = (pagination.currentPage - 1) * pagination.limit
      logEntries.value = sourceBookmarks.value.slice(offset, offset + pagination.limit)
      pagination.totalEntries = sourceBookmarks.value.length
      loadingRefs.log.value = false
      filters.syncFiltersToUrl()

      return
    }

    if (!source.id) {
      return
    }

    const requestId = ++currentEntriesRequest
    if (isPagination) {
      loadingRefs.pagination.value = true
      loadingRefs.log.value = false
    } else {
      loadingRefs.log.value = true
      loadingRefs.pagination.value = false
    }

    filters.syncFiltersToUrl()

    try {
      const offset = (pagination.currentPage - 1) * pagination.limit
      const result = await fetchLogEntries(
        source.id,
        pagination.limit,
        offset,
        sortDir.value,
        filters.filterLevel,
        filters.filterChannel,
        filters.filterSearch,
        filters.filterDateFrom,
        filters.filterDateTo,
        filters.filterSearchRegex,
        filters.filterSearchCaseSensitive,
      )

      if (requestId !== currentEntriesRequest) {
        return
      }

      logEntries.value = result.data
      responseTime.value = result.duration
      source.parserType = result.parserType || ''
      source.host = result.host || ''
      source.path = result.path || source.path
      source.size = result.size || 0
      source.canDelete = result.canDelete
      source.canDownload = result.canDownload

      if (withStats || (!pagination.isCountSlow && !isPagination)) {
        if (logEntries.value.length > 0) {
          if (logPageStatisticEnabled.value) {
            loadLogStats()
          } else {
            loadCount()
          }
        } else {
          pagination.totalEntries = 0
          logStats.value = { total: 0, size: result.size, levels: {}, channels: {} }
        }
      }

      if (!logStats.value) {
        logStats.value = { total: 0, size: result.size, levels: {}, channels: {} }
      } else {
        logStats.value.size = result.size
      }
    } catch (e) {
      console.error('Failed  to  load  entries', e)
    } finally {
      if (requestId === currentEntriesRequest) {
        loadingRefs.log.value = false
        loadingRefs.pagination.value = false
      }
    }
  }

  async function loadLiveEntries(): Promise<void> {
    if (filters.filterBookmarks) {
      loadingRefs.log.value = true
      const offset = (pagination.currentPage - 1) * pagination.limit
      logEntries.value = sourceBookmarks.value.slice(offset, offset + pagination.limit)
      pagination.totalEntries = sourceBookmarks.value.length
      loadingRefs.log.value = false
      filters.syncFiltersToUrl()

      return
    }

    loadingRefs.live.value = true
    try {
      const result = await fetchNewLogEntries(liveSelectedLevels.value, source.liveIds)
      responseTime.value = result.duration
      liveCalculatedAt.value = result.calculatedAt
      if (result.entries.length > 0) {
        const combined = [...result.entries, ...liveEntries.value]
        liveEntries.value = combined.slice(0, 100)
      }
    } catch (e) {
      console.error('Failed  to  load  live  entries', e)
    } finally {
      loadingRefs.live.value = false
    }

    return
  }

  async function loadGlobalSearchEntries(): Promise<void> {
    if (filters.filterBookmarks) {
      loadingRefs.log.value = true
      const offset = (pagination.currentPage - 1) * pagination.limit
      logEntries.value = sourceBookmarks.value.slice(offset, offset + pagination.limit)
      pagination.totalEntries = sourceBookmarks.value.length
      loadingRefs.log.value = false
      filters.syncFiltersToUrl()

      return
    }

    const requestId = ++currentEntriesRequest
    loadingRefs.log.value = true

    try {
      const offset = (pagination.currentPage - 1) * pagination.limit
      const result = await fetchGlobalSearch(
        source.ids,
        pagination.limit,
        offset,
        sortDir.value,
        filters.filterLevel,
        filters.filterChannel,
        filters.filterSearch,
        filters.filterDateFrom,
        filters.filterDateTo,
        filters.filterSearchRegex,
        filters.filterSearchCaseSensitive,
      )

      if (requestId !== currentEntriesRequest) {
        return
      }

      logEntries.value = result.entries
      pagination.totalEntries = result.count
      responseTime.value = result.duration
    } catch (e) {
      console.error('Failed  to  load  global  search  entries', e)
    } finally {
      if (requestId === currentEntriesRequest) {
        loadingRefs.log.value = false
      }
    }
  }

  return {
    logStats,
    dashboardStats,
    responseTime,
    liveCalculatedAt,
    dashboardTimelineFormat,
    dashboardLoading: loadingRefs.dashboard,
    dashboardLoadingDelayed: loadingDelayedRefs.dashboard,
    logLoading: loadingRefs.log,
    logLoadingDelayed: loadingDelayedRefs.log,
    paginationLoading: loadingRefs.pagination,
    paginationLoadingDelayed: loadingDelayedRefs.pagination,
    statsLoading: loadingRefs.stats,
    statsLoadingDelayed: loadingDelayedRefs.stats,
    countLoading: loadingRefs.count,
    countLoadingDelayed: loadingDelayedRefs.count,
    liveLoading: loadingRefs.live,
    liveLoadingDelayed: loadingDelayedRefs.live,
    loadDashboardStats,
    changeDashboardTimelineFormat,
    loadEntries,
    loadLiveEntries,
    loadGlobalSearchEntries,
  }
}
