import { computed } from 'vue'
import type { Ref } from 'vue'
import type { useSettingsStore } from '../useSettingsStore'
import type { AiChat, RegexTemplate } from '@/types'

type SettingsStore = ReturnType<typeof useSettingsStore>

interface EffectiveSettingsOptions {
  settingsStore: SettingsStore
  aiButtonLevels: Ref<string[]>
  logPageStatisticEnabled: Ref<boolean>
  logPageAutoRefreshEnabled: Ref<boolean>
  logPageAutoRefreshShowCountdown: Ref<boolean>
  logPageLimit: Ref<number>
  dashboardPageAutoRefreshEnabled: Ref<boolean>
  dashboardPageAutoRefreshShowCountdown: Ref<boolean>
  dashboardPageStatisticEnabled: Ref<boolean>
  liveLogPageEnabled: Ref<boolean>
  liveSelectedLevels: Ref<string[]>
  aiChats: Ref<AiChat[]>
  regexTemplates: Ref<RegexTemplate[]>
  refreshCountdown: Ref<number | null>
  dashboardRefreshCountdown: Ref<number | null>
  liveRefreshCountdown: Ref<number | null>
}

export function useLogEffectiveSettings(options: EffectiveSettingsOptions) {
  const { settingsStore } = options

  const aiButtonLevels = computed(() => {
    const levels = settingsStore.aiButtonLevels || options.aiButtonLevels.value

    return levels.map((l) => {
      return l.toUpperCase()
    })
  })

  const liveSelectedLevels = computed(() => {
    return settingsStore.liveLogPageLevels || options.liveSelectedLevels.value
  })

  const logPageStatisticEnabled = computed(() => {
    return settingsStore.logPageStatisticEnabled ?? options.logPageStatisticEnabled.value
  })

  const logPageAutoRefreshEnabled = computed(() => {
    return settingsStore.logPageAutoRefreshEnabled ?? options.logPageAutoRefreshEnabled.value
  })

  const dashboardPageAutoRefreshEnabled = computed(() => {
    return settingsStore.dashboardPageAutoRefreshEnabled ?? options.dashboardPageAutoRefreshEnabled.value
  })

  const dashboardPageStatisticEnabled = computed(() => {
    return settingsStore.dashboardPageStatisticEnabled ?? options.dashboardPageStatisticEnabled.value
  })

  const logPageAutoRefreshShowCountdown = computed(() => {
    return settingsStore.logPageAutoRefreshShowCountdown ?? options.logPageAutoRefreshShowCountdown.value
  })

  const logPageLimit = computed(() => {
    return settingsStore.logPageLimit ?? options.logPageLimit.value
  })

  const dashboardPageAutoRefreshShowCountdown = computed(() => {
    return settingsStore.dashboardPageAutoRefreshShowCountdown ?? options.dashboardPageAutoRefreshShowCountdown.value
  })

  const showLogRefreshCountdown = computed(() => {
    return (
      logPageAutoRefreshEnabled.value &&
      logPageAutoRefreshShowCountdown.value &&
      options.refreshCountdown.value !== null
    )
  })

  const showDashboardRefreshCountdown = computed(() => {
    return (
      dashboardPageAutoRefreshEnabled.value &&
      dashboardPageAutoRefreshShowCountdown.value &&
      options.dashboardRefreshCountdown.value !== null
    )
  })

  const showLiveRefreshCountdown = computed(() => {
    return options.liveLogPageEnabled.value && options.liveRefreshCountdown.value !== null
  })

  const aiChats = computed(() => {
    return options.aiChats.value
  })

  const regexTemplates = computed(() => {
    return options.regexTemplates.value
  })

  return {
    aiButtonLevels,
    aiChats,
    regexTemplates,
    logPageStatisticEnabled,
    logPageAutoRefreshEnabled,
    logPageLimit,
    dashboardPageAutoRefreshEnabled,
    dashboardPageStatisticEnabled,
    logPageAutoRefreshShowCountdown,
    dashboardPageAutoRefreshShowCountdown,
    liveSelectedLevels,
    showLogRefreshCountdown,
    showDashboardRefreshCountdown,
    showLiveRefreshCountdown,
  }
}
