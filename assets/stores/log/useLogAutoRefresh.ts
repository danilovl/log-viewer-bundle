import { ref, watch, onUnmounted } from 'vue'
import type { Ref } from 'vue'
import type { useSettingsStore } from '../useSettingsStore'

type SettingsStore = ReturnType<typeof useSettingsStore>

interface AutoRefreshOptions {
  settingsStore: SettingsStore
  logPageAutoRefreshEnabled: Ref<boolean>
  logPageAutoRefreshInterval: Ref<number | null>
  dashboardPageAutoRefreshEnabled: Ref<boolean>
  dashboardPageAutoRefreshInterval: Ref<number | null>
  dashboardPageStatisticEnabled: Ref<boolean>
  liveLogPageEnabled: Ref<boolean>
  liveLogPageInterval: Ref<number | null>
  getCurrentSourceId: () => string
  isEntriesLoading: () => boolean
  isDashboardLoading: () => boolean
  isLiveLoading: () => boolean
  onLoadEntries: () => Promise<void>
  onLoadDashboardStats: () => Promise<void>
  onLoadLiveEntries: () => Promise<void>
}

export function useLogAutoRefresh(options: AutoRefreshOptions) {
  const {
    settingsStore,
    logPageAutoRefreshEnabled,
    logPageAutoRefreshInterval,
    dashboardPageAutoRefreshEnabled,
    dashboardPageAutoRefreshInterval,
    dashboardPageStatisticEnabled,
    liveLogPageEnabled,
    liveLogPageInterval,
    getCurrentSourceId,
    isEntriesLoading,
    isDashboardLoading,
    isLiveLoading,
    onLoadEntries,
    onLoadDashboardStats,
    onLoadLiveEntries,
  } = options

  const refreshCountdown = ref<number | null>(null)
  const dashboardRefreshCountdown = ref<number | null>(null)
  const liveRefreshCountdown = ref<number | null>(null)

  let autoRefreshIntervalId: number | null = null
  let dashboardAutoRefreshIntervalId: number | null = null
  let liveAutoRefreshIntervalId: number | null = null

  function stopAutoRefresh(): void {
    if (autoRefreshIntervalId) {
      clearInterval(autoRefreshIntervalId)
      autoRefreshIntervalId = null
    }
  }

  function stopDashboardAutoRefresh(): void {
    if (dashboardAutoRefreshIntervalId) {
      clearInterval(dashboardAutoRefreshIntervalId)
      dashboardAutoRefreshIntervalId = null
    }
  }

  function stopLiveAutoRefresh(): void {
    if (liveAutoRefreshIntervalId) {
      clearInterval(liveAutoRefreshIntervalId)
      liveAutoRefreshIntervalId = null
    }
  }

  function startAutoRefresh(): void {
    stopAutoRefresh()
    const interval = settingsStore.logPageAutoRefreshInterval || logPageAutoRefreshInterval.value
    const enabled = settingsStore.logPageAutoRefreshEnabled ?? logPageAutoRefreshEnabled.value
    if (!enabled || !getCurrentSourceId() || interval === null) {
      return
    }

    refreshCountdown.value = interval
    autoRefreshIntervalId = window.setInterval(async () => {
      if (isEntriesLoading()) {
        return
      }

      if (refreshCountdown.value !== null) {
        refreshCountdown.value--

        if (refreshCountdown.value <= 0) {
          refreshCountdown.value = interval
          await onLoadEntries()
        }
      }

      return
    }, 1000)
  }

  function startDashboardAutoRefresh(): void {
    stopDashboardAutoRefresh()
    const interval = settingsStore.dashboardPageAutoRefreshInterval || dashboardPageAutoRefreshInterval.value
    const enabled = settingsStore.dashboardPageAutoRefreshEnabled ?? dashboardPageAutoRefreshEnabled.value
    if (!enabled || !dashboardPageStatisticEnabled.value || interval === null) {
      return
    }

    dashboardRefreshCountdown.value = interval
    dashboardAutoRefreshIntervalId = window.setInterval(async () => {
      if (isDashboardLoading()) {
        return
      }

      if (dashboardRefreshCountdown.value !== null) {
        dashboardRefreshCountdown.value--

        if (dashboardRefreshCountdown.value <= 0) {
          dashboardRefreshCountdown.value = interval
          await onLoadDashboardStats()
        }
      }

      return
    }, 1000)
  }

  function startLiveAutoRefresh(): void {
    stopLiveAutoRefresh()
    if (!liveLogPageEnabled.value) {
      return
    }

    const interval = settingsStore.liveLogPageInterval || liveLogPageInterval.value || 5
    liveRefreshCountdown.value = interval

    liveAutoRefreshIntervalId = window.setInterval(async () => {
      if (isLiveLoading()) {
        return
      }

      if (liveRefreshCountdown.value !== null) {
        liveRefreshCountdown.value--

        if (liveRefreshCountdown.value <= 0) {
          liveRefreshCountdown.value = interval
          await onLoadLiveEntries()
        }
      }

      return
    }, 1000)
  }

  watch(
    () => {
      return settingsStore.logPageAutoRefreshInterval
    },
    () => {
      if (autoRefreshIntervalId) {
        startAutoRefresh()
      }

      return
    },
  )

  watch(
    () => {
      return settingsStore.logPageAutoRefreshEnabled
    },
    () => {
      if (settingsStore.logPageAutoRefreshEnabled === false) {
        stopAutoRefresh()
      } else {
        startAutoRefresh()
      }

      return
    },
  )

  watch(
    () => {
      return settingsStore.dashboardPageAutoRefreshInterval
    },
    () => {
      if (dashboardAutoRefreshIntervalId) {
        startDashboardAutoRefresh()
      }

      return
    },
  )

  watch(
    () => {
      return settingsStore.dashboardPageAutoRefreshEnabled
    },
    () => {
      if (settingsStore.dashboardPageAutoRefreshEnabled === false) {
        stopDashboardAutoRefresh()
      } else {
        startDashboardAutoRefresh()
      }

      return
    },
  )

  watch(
    () => {
      return settingsStore.liveLogPageInterval
    },
    () => {
      if (liveAutoRefreshIntervalId) {
        startLiveAutoRefresh()
      }

      return
    },
  )

  onUnmounted(() => {
    stopAutoRefresh()
    stopDashboardAutoRefresh()
    stopLiveAutoRefresh()
  })

  return {
    refreshCountdown,
    dashboardRefreshCountdown,
    liveRefreshCountdown,
    startAutoRefresh,
    stopAutoRefresh,
    startDashboardAutoRefresh,
    stopDashboardAutoRefresh,
    startLiveAutoRefresh,
    stopLiveAutoRefresh,
  }
}
