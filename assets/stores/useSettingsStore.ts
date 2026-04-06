import { ref, watch } from 'vue'
import { defineStore } from 'pinia'

export const useSettingsStore = defineStore('settings', () => {
  const dashboardPageAutoRefreshInterval = ref<number | null>(null)
  const dashboardPageAutoRefreshEnabled = ref<boolean | null>(null)
  const dashboardPageAutoRefreshShowCountdown = ref<boolean | null>(null)
  const dashboardPageStatisticEnabled = ref<boolean | null>(null)

  const menuShowFileSize = ref<boolean | null>(null)

  const liveLogPageInterval = ref<number | null>(null)
  const liveLogPageLevels = ref<string[] | null>(null)

  const logPageAutoRefreshInterval = ref<number | null>(null)
  const logPageAutoRefreshEnabled = ref<boolean | null>(null)
  const logPageAutoRefreshShowCountdown = ref<boolean | null>(null)
  const logPageStatisticEnabled = ref<boolean | null>(null)
  const logPageLimit = ref<number | null>(null)

  const aiButtonLevels = ref<string[] | null>(null)

  const storageKey = 'danilovl.log_viewer.settings'

  const savedSettings = localStorage.getItem(storageKey) || localStorage.getItem('log-viewer-settings')
  if (savedSettings) {
    try {
      const parsed = JSON.parse(savedSettings)
      dashboardPageAutoRefreshInterval.value = parsed.dashboardPageAutoRefreshInterval ?? null
      dashboardPageAutoRefreshEnabled.value = parsed.dashboardPageAutoRefreshEnabled ?? null
      dashboardPageAutoRefreshShowCountdown.value = parsed.dashboardPageAutoRefreshShowCountdown ?? null
      dashboardPageStatisticEnabled.value = parsed.dashboardPageStatisticEnabled ?? null
      menuShowFileSize.value = parsed.menuShowFileSize ?? null

      liveLogPageInterval.value = parsed.liveLogPageInterval ?? null
      liveLogPageLevels.value = parsed.liveLogPageLevels ?? null

      logPageAutoRefreshInterval.value = parsed.logPageAutoRefreshInterval ?? null
      logPageAutoRefreshEnabled.value = parsed.logPageAutoRefreshEnabled ?? null
      logPageAutoRefreshShowCountdown.value = parsed.logPageAutoRefreshShowCountdown ?? null
      logPageStatisticEnabled.value = parsed.logPageStatisticEnabled ?? null
      logPageLimit.value = parsed.logPageLimit ?? null

      aiButtonLevels.value = parsed.aiButtonLevels ?? null
    } catch (e) {
      console.error('Failed  to  parse  settings  from  localStorage', e)
    }
  }

  watch(
    [
      dashboardPageAutoRefreshInterval,
      dashboardPageAutoRefreshEnabled,
      dashboardPageAutoRefreshShowCountdown,
      dashboardPageStatisticEnabled,
      menuShowFileSize,
      liveLogPageInterval,
      liveLogPageLevels,
      logPageAutoRefreshInterval,
      logPageAutoRefreshEnabled,
      logPageAutoRefreshShowCountdown,
      logPageStatisticEnabled,
      logPageLimit,
      aiButtonLevels,
    ],
    () => {
      if (dashboardPageAutoRefreshInterval.value !== null && dashboardPageAutoRefreshInterval.value <= 0) {
        dashboardPageAutoRefreshInterval.value = null
      }

      if (liveLogPageInterval.value !== null && liveLogPageInterval.value <= 0) {
        liveLogPageInterval.value = null
      }

      if (logPageAutoRefreshInterval.value !== null && logPageAutoRefreshInterval.value <= 0) {
        logPageAutoRefreshInterval.value = null
      }

      if (logPageLimit.value !== null && logPageLimit.value <= 0) {
        logPageLimit.value = null
      }

      localStorage.setItem(
        storageKey,
        JSON.stringify({
          dashboardPageAutoRefreshInterval: dashboardPageAutoRefreshInterval.value,
          dashboardPageAutoRefreshEnabled: dashboardPageAutoRefreshEnabled.value,
          dashboardPageAutoRefreshShowCountdown: dashboardPageAutoRefreshShowCountdown.value,
          dashboardPageStatisticEnabled: dashboardPageStatisticEnabled.value,
          menuShowFileSize: menuShowFileSize.value,
          liveLogPageInterval: liveLogPageInterval.value,
          liveLogPageLevels: liveLogPageLevels.value,
          logPageAutoRefreshInterval: logPageAutoRefreshInterval.value,
          logPageAutoRefreshEnabled: logPageAutoRefreshEnabled.value,
          logPageAutoRefreshShowCountdown: logPageAutoRefreshShowCountdown.value,
          logPageStatisticEnabled: logPageStatisticEnabled.value,
          logPageLimit: logPageLimit.value,
          aiButtonLevels: aiButtonLevels.value,
        }),
      )

      return
    },
    { deep: true },
  )

  function resetSettings() {
    dashboardPageAutoRefreshInterval.value = null
    dashboardPageAutoRefreshEnabled.value = null
    dashboardPageAutoRefreshShowCountdown.value = null
    dashboardPageStatisticEnabled.value = null
    menuShowFileSize.value = null
    liveLogPageInterval.value = null
    liveLogPageLevels.value = null
    logPageAutoRefreshInterval.value = null
    logPageAutoRefreshEnabled.value = null
    logPageAutoRefreshShowCountdown.value = null
    logPageStatisticEnabled.value = null
    logPageLimit.value = null
    aiButtonLevels.value = null
  }

  return {
    dashboardPageAutoRefreshInterval,
    dashboardPageAutoRefreshEnabled,
    dashboardPageAutoRefreshShowCountdown,
    dashboardPageStatisticEnabled,
    menuShowFileSize,
    liveLogPageInterval,
    liveLogPageLevels,
    logPageAutoRefreshInterval,
    logPageAutoRefreshEnabled,
    logPageAutoRefreshShowCountdown,
    logPageStatisticEnabled,
    logPageLimit,
    aiButtonLevels,
    resetSettings,
  }
})
