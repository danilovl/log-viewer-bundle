<template>
  <div class="pagination-wrapper">
    <div v-if="store.entries.loadingDelayed || store.entries.countLoadingDelayed" class="pagination-loader-container">
      <div class="spinner-sm"></div>
      <span>{{ t('loadingPagination') }}</span>
    </div>
    <div v-else class="pagination-container">
      <button class="btn btn-ghost btn-sm" :disabled="!store.pagination.hasPrevPage" @click="store.prevPage()">
        {{ t('previous') }}
      </button>
      <div class="pagination-numbers">
        <template v-for="item in pageItems" :key="item.key">
          <span v-if="item.type === 'dots'" class="text-muted">...</span>
          <button
            v-else
            :class="['page-btn', { active: item.page === store.pagination.currentPage }]"
            @click="store.goToPage(item.page!)"
          >
            {{ item.page }}
          </button>
        </template>
      </div>
      <button class="btn btn-ghost btn-sm" :disabled="!store.pagination.hasNextPage" @click="store.nextPage()">
        {{ t('next') }}
      </button>
    </div>
    <div v-if="store.pagination.isCountSlow" class="pagination-slow-info">
      {{ t('slowCount') }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'

interface PageItem {
  key: string
  type: 'page' | 'dots'
  page?: number
}

const store = useLogStore()
const { t } = useI18n()
const maxButtons = 5

const pageItems = computed((): PageItem[] => {
  if (store.pagination.totalEntries === 0) {
    return []
  }

  const totalPages = store.pagination.totalPages
  const items: PageItem[] = []
  let startPage = Math.max(1, store.pagination.currentPage - Math.floor(maxButtons / 2))
  let endPage = Math.min(totalPages, startPage + maxButtons - 1)

  if (endPage - startPage + 1 < maxButtons) {
    startPage = Math.max(1, endPage - maxButtons + 1)
  }

  if (startPage > 1) {
    items.push({ key: 'p-1', type: 'page', page: 1 })
    if (startPage > 2) {
      items.push({ key: 'dots-start', type: 'dots' })
    }
  }

  for (let i = startPage; i <= endPage; i++) {
    items.push({ key: `p-${i}`, type: 'page', page: i })
  }

  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      items.push({ key: 'dots-end', type: 'dots' })
    }
    items.push({ key: `p-${totalPages}`, type: 'page', page: totalPages })
  }

  return items
})
</script>
