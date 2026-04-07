import { ref } from 'vue'
import type { Ref } from 'vue'

export function useLogFilters(currentPage: Ref<number>) {
  const filterLevel = ref('')
  const filterChannel = ref('')
  const filterSearch = ref('')
  const filterDateFrom = ref('')
  const filterDateTo = ref('')
  const filterSearchHighlight = ref(true)
  const filterSearchRegex = ref(false)
  const filterSearchCaseSensitive = ref(false)
  const filterBookmarks = ref(false)
  const channels = ref<string[]>([])
  const levels = ref<string[]>([])

  function syncFiltersFromUrl(): void {
    const urlParams = new URLSearchParams(window.location.search)
    const urlLevel = urlParams.get('level') || ''
    const urlChannel = urlParams.get('channel') || ''
    const urlSearch = urlParams.get('search') || ''
    const urlDateFrom = urlParams.get('dateFrom') || ''
    const urlDateTo = urlParams.get('dateTo') || ''
    const urlSearchHighlight = urlParams.get('searchHighlight') !== '0'
    const urlSearchRegex = urlParams.get('searchRegex') === '1'
    const urlSearchCaseSensitive = urlParams.get('searchCaseSensitive') === '1'
    const urlBookmarks = urlParams.get('bookmarks') === '1'
    const page = urlParams.get('page')

    if (urlLevel !== filterLevel.value) {
      filterLevel.value = urlLevel
      if (urlLevel && !levels.value.includes(urlLevel)) {
        levels.value.push(urlLevel)
      }
    }

    if (urlChannel !== filterChannel.value) {
      filterChannel.value = urlChannel
      if (urlChannel && !channels.value.includes(urlChannel)) {
        channels.value.push(urlChannel)
      }
    }

    if (urlSearch !== filterSearch.value) {
      filterSearch.value = urlSearch
    }
    if (urlDateFrom !== filterDateFrom.value) {
      filterDateFrom.value = urlDateFrom
    }
    if (urlDateTo !== filterDateTo.value) {
      filterDateTo.value = urlDateTo
    }
    filterSearchHighlight.value = urlSearchHighlight
    filterSearchRegex.value = urlSearchRegex
    filterSearchCaseSensitive.value = urlSearchCaseSensitive
    filterBookmarks.value = urlBookmarks

    currentPage.value = page ? parseInt(page) : 1
  }

  function syncFiltersToUrl(): void {
    const url = new URL(window.location.href)

    if (filterLevel.value) {
      url.searchParams.set('level', filterLevel.value)
    } else {
      url.searchParams.delete('level')
    }

    if (filterChannel.value) {
      url.searchParams.set('channel', filterChannel.value)
    } else {
      url.searchParams.delete('channel')
    }

    if (filterSearch.value) {
      url.searchParams.set('search', filterSearch.value)
    } else {
      url.searchParams.delete('search')
    }

    if (filterDateFrom.value) {
      url.searchParams.set('dateFrom', filterDateFrom.value)
    } else {
      url.searchParams.delete('dateFrom')
    }

    if (filterDateTo.value) {
      url.searchParams.set('dateTo', filterDateTo.value)
    } else {
      url.searchParams.delete('dateTo')
    }

    if (!filterSearchHighlight.value) {
      url.searchParams.set('searchHighlight', '0')
    } else {
      url.searchParams.delete('searchHighlight')
    }

    if (filterSearchRegex.value) {
      url.searchParams.set('searchRegex', '1')
    } else {
      url.searchParams.delete('searchRegex')
    }

    if (filterSearchCaseSensitive.value) {
      url.searchParams.set('searchCaseSensitive', '1')
    } else {
      url.searchParams.delete('searchCaseSensitive')
    }

    if (filterBookmarks.value) {
      url.searchParams.set('bookmarks', '1')
    } else {
      url.searchParams.delete('bookmarks')
    }

    if (currentPage.value > 1) {
      url.searchParams.set('page', String(currentPage.value))
    } else {
      url.searchParams.delete('page')
    }

    window.history.pushState({}, '', url.toString())
  }

  function resetFilters(): void {
    filterLevel.value = ''
    filterChannel.value = ''
    filterSearch.value = ''
    filterDateFrom.value = ''
    filterDateTo.value = ''
    filterSearchRegex.value = false
    filterSearchCaseSensitive.value = false
    filterSearchHighlight.value = true
    currentPage.value = 1
  }

  return {
    filterLevel,
    filterChannel,
    filterSearch,
    filterDateFrom,
    filterDateTo,
    filterSearchHighlight,
    filterSearchRegex,
    filterSearchCaseSensitive,
    filterBookmarks,
    channels,
    levels,
    syncFiltersFromUrl,
    syncFiltersToUrl,
    resetFilters,
  }
}
