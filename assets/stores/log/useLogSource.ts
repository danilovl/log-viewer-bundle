import { reactive, watch } from 'vue'
import { STORAGE_KEY_LIVE, STORAGE_KEY_GLOBAL } from '@/utils/constants'

export function useLogSource() {
  const source = reactive({
    id: '',
    ids: [] as string[],
    liveIds: [] as string[],
    name: '',
    path: '',
    host: '',
    canDelete: false,
    isDeletable: false,
    canDownload: false,
    isDownloadable: false,
    canRead: false,
    isReadable: false,
    parserType: '',
    size: 0,
  })

  const savedGlobal = localStorage.getItem(STORAGE_KEY_GLOBAL) || localStorage.getItem('global_selected_sources')
  if (savedGlobal) {
    try {
      const parsed = JSON.parse(savedGlobal)
      if (Array.isArray(parsed)) {
        source.ids = parsed
      }
    } catch (e) {}
  }

  const savedLive = localStorage.getItem(STORAGE_KEY_LIVE) || localStorage.getItem('live_selected_sources')
  if (savedLive) {
    try {
      const parsed = JSON.parse(savedLive)
      if (Array.isArray(parsed)) {
        source.liveIds = parsed
      }
    } catch (e) {}
  }

  watch(
    () => {
      return source.liveIds
    },
    (newVal) => {
      localStorage.setItem(STORAGE_KEY_LIVE, JSON.stringify(newVal))
    },
    { deep: true },
  )

  watch(
    () => {
      return source.ids
    },
    (newVal) => {
      localStorage.setItem(STORAGE_KEY_GLOBAL, JSON.stringify(newVal))
    },
    { deep: true },
  )

  return { source }
}
