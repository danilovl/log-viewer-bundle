import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from '@/App.vue'
import router from '@/router'
import { vTooltip } from '@/directives/tooltip'
import { useLogStore } from '@/stores/useLogStore'
import { setApiPrefix } from '@/services/api'
import type { AppConfig } from '@/types'
import '@/style/dashboard.css'
;(async () => {
  const appElement = document.getElementById('app')
  if (!appElement) {
    return
  }

  const config: AppConfig = {
    apiPrefix: appElement.dataset.apiPrefix || '',
    structure: [],
    dashboardPageStatisticEnabled: false,
    logPageStatisticEnabled: false,
    logPageAutoRefreshEnabled: false,
    logPageAutoRefreshInterval: null,
    logPageAutoRefreshShowCountdown: false,
    logPageLimit: 100,
    dashboardPageAutoRefreshEnabled: false,
    dashboardPageAutoRefreshInterval: null,
    dashboardPageAutoRefreshShowCountdown: false,
    liveLogPageEnabled: false,
    liveLogPageInterval: null,
    liveSelectedLevels: [],
    aiButtonLevels: [],
  }

  setApiPrefix(config.apiPrefix)

  const app = createApp(App)
  const pinia = createPinia()
  app.use(pinia)
  app.use(router)
  app.directive('tooltip', vTooltip)

  const store = useLogStore()
  store.initFromConfig(config)
  store.syncFiltersFromUrl()

  await Promise.all([store.loadConfig(), store.loadStructure()])

  app.mount('#app')

  return
})()
