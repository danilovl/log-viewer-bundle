import { useLogStore } from '@/stores/useLogStore'

export function useHighlight() {
  const store = useLogStore()

  const highlight = (text: string, highlightText: string): string => {
    if (!text) {
      return ''
    }

    const escapedText = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;')

    if (!highlightText) {
      return escapedText
    }

    let regex: RegExp
    try {
      const pattern = store.filters.filterSearchRegex
        ? highlightText
        : highlightText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

      regex = new RegExp(`(${pattern})`, store.filters.filterSearchCaseSensitive ? 'g' : 'gi')
    } catch (e) {
      return escapedText
    }

    return escapedText.replace(regex, '<mark>$1</mark>')
  }

  return {
    highlight,
  }
}
