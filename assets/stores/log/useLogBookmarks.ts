import { ref, watch, computed } from 'vue'
import type { LogEntry } from '@/types'
import { useLogStore } from '../useLogStore'

const STORAGE_KEY = 'danilovl.log_viewer.bookmarks'

const bookmarks = ref<Record<string, LogEntry[]>>({})

function loadBookmarks(): void {
  try {
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || localStorage.getItem('bookmarks') || '{}')
    if (Array.isArray(Object.values(stored)[0])) {
      bookmarks.value = stored
    } else {
      const migrated: Record<string, LogEntry[]> = {}
      Object.values(stored as Record<string, LogEntry>).forEach((entry) => {
        const sId = entry.sourceId || 'default'
        if (!migrated[sId]) {
          migrated[sId] = []
        }
        migrated[sId].push(entry)
      })
      bookmarks.value = migrated
    }
  } catch (e) {
    bookmarks.value = {}
  }
}

loadBookmarks()

export function useLogBookmarks() {
  const store = useLogStore()

  function getHashCode(str: string): string {
    let hash = 0
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i)
      hash = (hash << 5) - hash + char
      hash |= 0
    }

    return Math.abs(hash).toString(16)
  }

  function getEntryKey(entry: LogEntry): string {
    const compositeString = `${entry.timestamp}-${entry.level}-${entry.message}`
    const messageHash = getHashCode(compositeString)
    const sId = entry.sourceId || store.source.id || 'default'

    return `${sId}-${messageHash}`
  }

  const bookmarksCount = computed(() => {
    return Object.values(bookmarks.value).flat().length
  })

  const bookmarksList = computed(() => {
    return Object.values(bookmarks.value)
      .flat()
      .sort((a, b) => {
        return b.timestamp.localeCompare(a.timestamp)
      })
  })

  function getBookmarksBySource(sourceId: string): LogEntry[] {
    return (bookmarks.value[sourceId] || []).sort((a, b) => {
      return b.timestamp.localeCompare(a.timestamp)
    })
  }

  function getBookmarksBySources(sourceIds: string[]): LogEntry[] {
    const result: LogEntry[] = []
    sourceIds.forEach((sId) => {
      result.push(...(bookmarks.value[sId] || []))
    })

    return result.sort((a, b) => {
      return b.timestamp.localeCompare(a.timestamp)
    })
  }

  const sourceBookmarks = computed(() => {
    if (store.source.id) {
      return getBookmarksBySource(store.source.id)
    }
    if (store.source.ids && store.source.ids.length > 0) {
      return getBookmarksBySources(store.source.ids)
    }
    if (store.source.liveIds && store.source.liveIds.length > 0) {
      return getBookmarksBySources(store.source.liveIds)
    }

    return []
  })

  const sourceBookmarksCount = computed(() => {
    return sourceBookmarks.value.length
  })

  watch(
    bookmarks,
    (newVal) => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(newVal))
    },
    { deep: true },
  )

  function toggleBookmark(entry: LogEntry): void {
    const sId = entry.sourceId || store.source.id || 'default'
    const key = getEntryKey(entry)
    const newBookmarks = { ...bookmarks.value }

    if (!newBookmarks[sId]) {
      newBookmarks[sId] = []
    }

    const index = newBookmarks[sId].findIndex((e) => {
      return getEntryKey(e) === key
    })
    if (index !== -1) {
      newBookmarks[sId].splice(index, 1)
      if (newBookmarks[sId].length === 0) {
        delete newBookmarks[sId]
      }
    } else {
      const bookmarkedEntry = { ...entry }
      if (!bookmarkedEntry.sourceId) {
        bookmarkedEntry.sourceId = sId
      }
      newBookmarks[sId].push(bookmarkedEntry)
    }
    bookmarks.value = newBookmarks
  }

  function isBookmarked(entry: LogEntry): boolean {
    const sId = entry.sourceId || store.source.id || 'default'
    const key = getEntryKey(entry)

    return (bookmarks.value[sId] || []).some((e) => {
      return getEntryKey(e) === key
    })
  }

  return {
    bookmarks,
    bookmarksList,
    bookmarksCount,
    toggleBookmark,
    isBookmarked,
    getBookmarksBySource,
    getBookmarksBySources,
    sourceBookmarks,
    sourceBookmarksCount,
  }
}
