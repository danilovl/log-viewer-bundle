<template>
  <div id="global-search-view">
    <div class="search-header">
      <div class="search-title">
        <IconSearch :width="24" :height="24" />
        <h2>{{ t('globalSearch') }}</h2>
      </div>

      <div class="search-actions">
        <button class="btn btn-outline" @click="showFileModal = true">
          <IconFile :width="16" :height="16" />
          {{ t('selectFiles') }}
          <span v-if="store.source.ids.length > 0" class="badge">
            {{ store.source.ids.length }}
          </span>
        </button>
      </div>
    </div>

    <LogFilters />

    <div class="results-container">
      <div v-if="store.isStructureLoaded && store.source.ids.length === 0" class="no-files-selected">
        <IconFile :width="48" :height="48" />
        <p>{{ t('noFilesSelected') }}</p>
        <button class="btn btn-primary" @click="showFileModal = true">
          {{ t('selectFiles') }}
        </button>
      </div>

      <template v-else>
        <div v-if="store.entries.data.length === 0 && !store.entries.loading" class="no-results-found">
          <IconSearch :width="48" :height="48" />
          <p>{{ t('noSearchResults') }}</p>
        </div>
        <template v-else>
          <LogTable show-source />
        </template>
      </template>
    </div>

    <FileSelectionModal
      v-model="showFileModal"
      :title="t('selectFiles')"
      :selected-ids="store.source.ids"
      @toggle="toggleFile"
      @selectAll="selectAllFiles"
      @resetAll="resetAllFiles"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import IconSearch from '@/components/icons/IconSearch.vue'
import IconFile from '@/components/icons/IconFile.vue'
import LogFilters from '@/components/Logs/LogFilters.vue'
import LogTable from '@/components/Logs/LogTable.vue'
import FileSelectionModal from '@/components/UI/FileSelectionModal.vue'

const store = useLogStore()
const { t } = useI18n()
const showFileModal = ref(false)

function toggleFile(id: string): void {
  const index = store.source.ids.indexOf(id)
  if (index === -1) {
    store.source.ids.push(id)
  } else {
    store.source.ids.splice(index, 1)
  }
}

function selectAllFiles(ids: string[]): void {
  store.source.ids = [...ids]
}

function resetAllFiles(): void {
  store.source.ids = []
}

async function performSearch(): Promise<void> {
  const hasLevel = !!store.filters.filterLevel
  const hasSearch = !!store.filters.filterSearch

  if (store.isStructureLoaded && store.source.ids.length > 0 && (hasLevel || hasSearch)) {
    await store.loadGlobalSearchEntries()
  } else if (store.isStructureLoaded) {
    store.entries.data = []
    store.pagination.totalEntries = 0
  }
}

store.applyFilters = performSearch

watch(
  [
    () => {
      return store.pagination.currentPage
    },
    () => {
      return store.entries.sortDir
    },
  ],
  () => {
    performSearch()
  },
)

onMounted(() => {
  store.source.id = ''
  if (store.isStructureLoaded) {
    performSearch()
  }
})

watch(
  () => {
    return store.isStructureLoaded
  },
  (loaded) => {
    if (loaded) {
      performSearch()
    }
  },
)
</script>
