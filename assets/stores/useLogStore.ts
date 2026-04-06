import { defineStore } from 'pinia'
import { ref, reactive, computed, watch } from 'vue'
import type { AppConfig, TreeRootNode, LogEntry } from '@/types'
import { errorLevels, STORAGE_KEY_LIVE, STORAGE_KEY_GLOBAL, logLevels } from '@/utils/constants'
import { fetchStructure } from '@/services/api'
import { useSettingsStore } from './useSettingsStore'
import { useLogConfig } from './log/useLogConfig'
import { useLogSource } from './log/useLogSource'
import { useLogFilters } from './log/useLogFilters'
import { useLogPagination } from './log/useLogPagination'
import { useLogData } from './log/useLogData'
import { useLogAutoRefresh } from './log/useLogAutoRefresh'
import { useLogEffectiveSettings } from './log/useLogEffectiveSettings'
import { useLogFiles } from './log/useLogFiles'
import { useLogBookmarks } from './log/useLogBookmarks'

export const useLogStore = defineStore('log', () => {
  const settingsStore = useSettingsStore()
  const logConfig = useLogConfig()

  const { source } = useLogSource()
  const isAnyFilesLoaded = ref(false)

  const logEntries = ref<LogEntry[]>([])
  const liveEntries = ref<LogEntry[]>([])
  const sortDir = ref<'asc' | 'desc'>('desc')
  const structure = ref<TreeRootNode[]>([])
  const isStructureLoaded = ref(false)
  const sidebarMobileOpen = ref(false)
  const isZenMode = ref(false)
  const paginationComposable = useLogPagination(logEntries, settingsStore.logPageLimit ?? logConfig.logPageLimit.value)
  const filtersComposable = useLogFilters(paginationComposable.currentPage)
  const bookmarksComposable = useLogBookmarks()

  const effectiveLogPageStatisticEnabled = computed(() => {
    return settingsStore.logPageStatisticEnabled ?? logConfig.logPageStatisticEnabled.value
  })

  const pagination = reactive(paginationComposable)
  const filters = reactive(filtersComposable)

  const data = useLogData({
    source,
    logEntries,
    liveEntries,
    sortDir,
    logPageStatisticEnabled: effectiveLogPageStatisticEnabled,
    liveSelectedLevels: computed(() => {
      return effective.liveSelectedLevels
    }),
    pagination,
    filters,
    sourceBookmarks: bookmarksComposable.sourceBookmarks,
  })

  const autoRefresh = useLogAutoRefresh({
    settingsStore,
    logPageAutoRefreshEnabled: logConfig.logPageAutoRefreshEnabled,
    logPageAutoRefreshInterval: logConfig.logPageAutoRefreshInterval,
    dashboardPageAutoRefreshEnabled: logConfig.dashboardPageAutoRefreshEnabled,
    dashboardPageAutoRefreshInterval: logConfig.dashboardPageAutoRefreshInterval,
    dashboardPageStatisticEnabled: logConfig.dashboardPageStatisticEnabled,
    liveLogPageEnabled: logConfig.liveLogPageEnabled,
    liveLogPageInterval: logConfig.liveLogPageInterval,
    getCurrentSourceId: () => {
      return source.id
    },
    isEntriesLoading: () => {
      return data.logLoading.value || data.statsLoading.value || data.countLoading.value || data.paginationLoading.value
    },
    isDashboardLoading: () => {
      return data.dashboardLoading.value
    },
    isLiveLoading: () => {
      return data.liveLoading.value
    },
    onLoadEntries: () => {
      return data.loadEntries(false)
    },
    onLoadDashboardStats: data.loadDashboardStats,
    onLoadLiveEntries: data.loadLiveEntries,
  })

  const effective = reactive(
    useLogEffectiveSettings({
      settingsStore,
      aiButtonLevels: logConfig.aiButtonLevels,
      logPageStatisticEnabled: logConfig.logPageStatisticEnabled,
      logPageAutoRefreshEnabled: logConfig.logPageAutoRefreshEnabled,
      logPageAutoRefreshShowCountdown: logConfig.logPageAutoRefreshShowCountdown,
      logPageLimit: logConfig.logPageLimit,
      dashboardPageAutoRefreshEnabled: logConfig.dashboardPageAutoRefreshEnabled,
      dashboardPageAutoRefreshShowCountdown: logConfig.dashboardPageAutoRefreshShowCountdown,
      dashboardPageStatisticEnabled: logConfig.dashboardPageStatisticEnabled,
      liveLogPageEnabled: logConfig.liveLogPageEnabled,
      liveSelectedLevels: logConfig.liveSelectedLevels,
      refreshCountdown: autoRefresh.refreshCountdown,
      dashboardRefreshCountdown: autoRefresh.dashboardRefreshCountdown,
      liveRefreshCountdown: autoRefresh.liveRefreshCountdown,
    }),
  )

  const config = reactive({
    apiPrefix: logConfig.apiPrefix,
    dashboardPageStatisticEnabled: logConfig.dashboardPageStatisticEnabled,
    logPageStatisticEnabled: logConfig.logPageStatisticEnabled,
    sourceAllowDelete: logConfig.sourceAllowDelete,
    sourceAllowDownload: logConfig.sourceAllowDownload,
    logPageAutoRefreshEnabled: logConfig.logPageAutoRefreshEnabled,
    logPageAutoRefreshInterval: logConfig.logPageAutoRefreshInterval,
    logPageAutoRefreshShowCountdown: logConfig.logPageAutoRefreshShowCountdown,
    logPageLimit: logConfig.logPageLimit,
    dashboardPageAutoRefreshEnabled: logConfig.dashboardPageAutoRefreshEnabled,
    dashboardPageAutoRefreshInterval: logConfig.dashboardPageAutoRefreshInterval,
    dashboardPageAutoRefreshShowCountdown: logConfig.dashboardPageAutoRefreshShowCountdown,
    liveLogPageEnabled: logConfig.liveLogPageEnabled,
    liveLogPageInterval: logConfig.liveLogPageInterval,
    liveSelectedLevels: logConfig.liveSelectedLevels,
    aiButtonLevels: logConfig.aiButtonLevels,
    settingsStore,
  })

  const openedAiEntryKey = ref<string | null>(null)
  const activeFileDropdownId = ref<string | null>(null)

  function setOpenedAiEntryKey(key: string | null) {
    openedAiEntryKey.value = key
  }

  const errorCount = computed(() => {
    if (!data.dashboardStats.value) {
      return 0
    }

    const levels = data.dashboardStats.value.levels

    return errorLevels.reduce((sum, level) => {
      return sum + (levels[level] || 0)
    }, 0)
  })

  const logErrorCount = computed(() => {
    if (!data.logStats.value) {
      return 0
    }

    const levels = data.logStats.value.levels

    return errorLevels.reduce((sum, level) => {
      return sum + (levels[level] || 0)
    }, 0)
  })

  const topChannel = computed(() => {
    if (!data.logStats.value) {
      return '-'
    }

    let top = '-'
    let max = 0
    for (const ch in data.logStats.value.channels) {
      if (data.logStats.value.channels[ch] > max) {
        max = data.logStats.value.channels[ch]
        top = ch
      }
    }

    return top
  })

  const dashboard = {
    stats: data.dashboardStats,
    loading: data.dashboardLoading,
    loadingDelayed: data.dashboardLoadingDelayed,
    timelineFormat: data.dashboardTimelineFormat,
    refreshCountdown: autoRefresh.dashboardRefreshCountdown,
    errorCount,
  }

  const entries = {
    data: logEntries,
    stats: data.logStats,
    loading: data.logLoading,
    loadingDelayed: data.logLoadingDelayed,
    paginationLoading: data.paginationLoading,
    paginationLoadingDelayed: data.paginationLoadingDelayed,
    statsLoading: data.statsLoading,
    statsLoadingDelayed: data.statsLoadingDelayed,
    countLoading: data.countLoading,
    countLoadingDelayed: data.countLoadingDelayed,
    sortDir,
    responseTime: data.responseTime,
    refreshCountdown: autoRefresh.refreshCountdown,
    errorCount: logErrorCount,
    topChannel,
  }

  const live = {
    data: liveEntries,
    loading: data.liveLoading,
    loadingDelayed: data.liveLoadingDelayed,
    selectedLevels: effective.liveSelectedLevels,
    refreshCountdown: autoRefresh.liveRefreshCountdown,
    calculatedAt: data.liveCalculatedAt,
  }

  watch(
    () => {
      return source.id
    },
    () => {
      pagination.isCountSlow = false
      logEntries.value = []
      data.logStats.value = null
      pagination.totalEntries = 0
      pagination.currentPage = 1

      return
    },
  )

  watch(
    () => {
      return effective.logPageLimit
    },
    (newLimit) => {
      pagination.limit = newLimit
      data.loadEntries(true)
    },
  )

  watch(effectiveLogPageStatisticEnabled, (newVal) => {
    if (newVal) {
      data.loadEntries(true)
    }
  })

  watch(
    () => {
      return []
    },
    () => {
      return
    },
  )

  async function loadStructure(): Promise<void> {
    structure.value = await fetchStructure()
    isStructureLoaded.value = true
  }

  function initFromConfig(appConfig: AppConfig): void {
    logConfig.initFromConfig(appConfig)
    structure.value = appConfig.structure
    isStructureLoaded.value = true
  }

  const { deleteFile, downloadFile } = useLogFiles(source, loadStructure)

  watch(
    () => {
      return filters.filterBookmarks
    },
    () => {
      data.loadEntries(true)
    },
  )

  function applyFilters(): void {
    pagination.currentPage = 1
    data.loadEntries(true, false)
  }

  watch(
    [isStructureLoaded, structure],
    ([loaded, struct]) => {
      if (loaded && struct.length > 0 && !isAnyFilesLoaded.value) {
        const allIds = getAllFileIds()
        if (allIds.length === 0) {
          return
        }

        const savedGlobalRaw =
          localStorage.getItem(STORAGE_KEY_GLOBAL) || localStorage.getItem('global_selected_sources')
        let savedGlobal: string[] = []
        try {
          if (savedGlobalRaw) {
            const parsed = JSON.parse(savedGlobalRaw)
            if (Array.isArray(parsed)) {
              savedGlobal = parsed
            }
          }
        } catch (e) {}

        if (savedGlobal.length === 0) {
          source.ids = [...allIds]
        } else {
          const filtered = savedGlobal.filter((id) => {
            return allIds.includes(id)
          })

          source.ids = filtered.length > 0 ? filtered : [...allIds]
        }

        const savedLiveRaw = localStorage.getItem(STORAGE_KEY_LIVE) || localStorage.getItem('live_selected_sources')
        let savedLive: string[] = []
        try {
          if (savedLiveRaw) {
            const parsed = JSON.parse(savedLiveRaw)
            if (Array.isArray(parsed)) {
              savedLive = parsed
            }
          }
        } catch (e) {}

        if (savedLive.length === 0) {
          source.liveIds = [...allIds]
        } else {
          const filtered = savedLive.filter((id) => {
            return allIds.includes(id)
          })

          source.liveIds = filtered.length > 0 ? filtered : [...allIds]
        }

        isAnyFilesLoaded.value = true
      }
    },
    { immediate: true },
  )

  function resetFilters(): void {
    filters.filterLevel = ''
    filters.filterChannel = ''
    filters.filterSearch = ''
    filters.channels = []
    pagination.currentPage = 1
    data.loadEntries(true, false)
  }

  function goToPage(page: number): void {
    if (page === pagination.currentPage) {
      return
    }
    pagination.currentPage = page
    data.loadEntries(false, false, true)
  }

  function nextPage(): void {
    if (pagination.hasNextPage) {
      pagination.currentPage++
      data.loadEntries(false, false, true)
    }
  }

  function prevPage(): void {
    if (pagination.hasPrevPage) {
      pagination.currentPage--
      data.loadEntries(false, false, true)
    }
  }

  function toggleSort(): void {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
    pagination.currentPage = 1
    data.loadEntries()
  }

  function refresh(): void {
    data.loadEntries(true, false)
  }

  function getAllFileIds(): string[] {
    const ids: string[] = []
    const extractIds = (node: any): void => {
      const files = node?.files || []
      const folders = node?.folders || []

      if (Array.isArray(files)) {
        files.forEach((file: any) => {
          if (file?.id) {
            ids.push(file.id)
          }
        })
      }

      const folderValues = Array.isArray(folders) ? folders : Object.values(folders)
      folderValues.forEach((folder: any) => {
        extractIds(folder)
      })
    }

    if (Array.isArray(structure.value)) {
      structure.value.forEach((root: any) => {
        extractIds(root)
      })
    }

    return ids
  }

  function resetLiveFilters(): void {
    logConfig.liveSelectedLevels.value = [...logLevels]
  }

  const hasAnyFiles = computed(() => {
    return structure.value.some((root) => {
      return (root.files && root.files.length > 0) || (root.folders && root.folders.length > 0)
    })
  })

  return {
    structure,
    isStructureLoaded,
    hasAnyFiles,
    sidebarMobileOpen,
    isZenMode,
    source,
    config,
    filters,
    pagination,
    dashboard,
    entries,
    live,
    effective,
    openedAiEntryKey,
    activeFileDropdownId,
    setOpenedAiEntryKey,
    loadConfig: logConfig.loadConfig,
    loadStructure,
    initFromConfig,
    loadDashboardStats: data.loadDashboardStats,
    changeDashboardTimelineFormat: data.changeDashboardTimelineFormat,
    loadEntries: data.loadEntries,
    loadLiveEntries: data.loadLiveEntries,
    loadGlobalSearchEntries: data.loadGlobalSearchEntries,
    applyFilters,
    resetFilters,
    refresh,
    getAllFileIds,
    goToPage,
    nextPage,
    prevPage,
    toggleSort,
    deleteFile,
    downloadFile,
    resetLiveFilters,
    syncFiltersFromUrl: filtersComposable.syncFiltersFromUrl,
    startAutoRefresh: autoRefresh.startAutoRefresh,
    stopAutoRefresh: autoRefresh.stopAutoRefresh,
    startDashboardAutoRefresh: autoRefresh.startDashboardAutoRefresh,
    stopDashboardAutoRefresh: autoRefresh.stopDashboardAutoRefresh,
    startLiveAutoRefresh: autoRefresh.startLiveAutoRefresh,
    stopLiveAutoRefresh: autoRefresh.stopLiveAutoRefresh,
  }
})
