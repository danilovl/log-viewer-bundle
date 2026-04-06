import { ref } from 'vue'
import type { AppConfig, ServerConfig, AiChat } from '@/types'
import { fetchConfig } from '@/services/api'
import { logLevels } from '@/utils/constants'

export function useLogConfig() {
  const apiPrefix = ref('')
  const dashboardPageStatisticEnabled = ref(false)
  const logPageStatisticEnabled = ref(false)
  const sourceAllowDelete = ref(false)
  const sourceAllowDownload = ref(false)
  const logPageAutoRefreshEnabled = ref(false)
  const logPageAutoRefreshInterval = ref<number | null>(null)
  const logPageAutoRefreshShowCountdown = ref(false)
  const logPageLimit = ref(50)
  const dashboardPageAutoRefreshEnabled = ref(false)
  const dashboardPageAutoRefreshInterval = ref<number | null>(null)
  const dashboardPageAutoRefreshShowCountdown = ref(false)
  const liveLogPageEnabled = ref(false)
  const liveLogPageInterval = ref<number | null>(null)
  const liveSelectedLevels = ref<string[]>([...logLevels])
  const aiButtonLevels = ref<string[]>([])
  const aiChats = ref<AiChat[]>([])

  async function loadConfig(): Promise<void> {
    const response: ServerConfig = await fetchConfig()
    dashboardPageStatisticEnabled.value = response.dashboardPageStatisticEnabled
    logPageStatisticEnabled.value = response.logPageStatisticEnabled
    sourceAllowDelete.value = response.sourceAllowDelete
    sourceAllowDownload.value = response.sourceAllowDownload
    logPageAutoRefreshEnabled.value = response.logPageAutoRefreshEnabled
    logPageAutoRefreshInterval.value = response.logPageAutoRefreshInterval
    logPageAutoRefreshShowCountdown.value = response.logPageAutoRefreshShowCountdown
    logPageLimit.value = response.logPageLimit
    dashboardPageAutoRefreshEnabled.value = response.dashboardPageAutoRefreshEnabled
    dashboardPageAutoRefreshInterval.value = response.dashboardPageAutoRefreshInterval
    dashboardPageAutoRefreshShowCountdown.value = response.dashboardPageAutoRefreshShowCountdown
    liveLogPageEnabled.value = response.liveLogPageEnabled
    liveLogPageInterval.value = response.liveLogPageInterval
    liveSelectedLevels.value = response.liveSelectedLevels
    aiButtonLevels.value = response.aiButtonLevels
    aiChats.value = response.aiChats
  }

  function initFromConfig(config: AppConfig): void {
    apiPrefix.value = config.apiPrefix
    dashboardPageStatisticEnabled.value = config.dashboardPageStatisticEnabled
    logPageStatisticEnabled.value = config.logPageStatisticEnabled
    logPageAutoRefreshEnabled.value = config.logPageAutoRefreshEnabled
    logPageAutoRefreshInterval.value = config.logPageAutoRefreshInterval
    logPageAutoRefreshShowCountdown.value = config.logPageAutoRefreshShowCountdown
    logPageLimit.value = config.logPageLimit
    dashboardPageAutoRefreshEnabled.value = config.dashboardPageAutoRefreshEnabled
    dashboardPageAutoRefreshInterval.value = config.dashboardPageAutoRefreshInterval
    dashboardPageAutoRefreshShowCountdown.value = config.dashboardPageAutoRefreshShowCountdown
    liveLogPageEnabled.value = config.liveLogPageEnabled
    liveLogPageInterval.value = config.liveLogPageInterval
    liveSelectedLevels.value = config.liveSelectedLevels
    aiButtonLevels.value = config.aiButtonLevels
    aiChats.value = config.aiChats
  }

  return {
    apiPrefix,
    dashboardPageStatisticEnabled,
    logPageStatisticEnabled,
    sourceAllowDelete,
    sourceAllowDownload,
    logPageAutoRefreshEnabled,
    logPageAutoRefreshInterval,
    logPageAutoRefreshShowCountdown,
    logPageLimit,
    dashboardPageAutoRefreshEnabled,
    dashboardPageAutoRefreshInterval,
    dashboardPageAutoRefreshShowCountdown,
    liveLogPageEnabled,
    liveLogPageInterval,
    liveSelectedLevels,
    aiButtonLevels,
    aiChats,
    loadConfig,
    initFromConfig,
  }
}
