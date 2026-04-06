<template>
  <div v-if="store.source.id" class="logs-view-container">
    <LogFilters />

    <LogSkeleton v-if="store.entries.loadingDelayed && !store.entries.data.length" />

    <template v-else-if="store.entries.data.length || store.entries.stats || store.filters.filterBookmarks">
      <div class="dashboard-stats-wrapper">
        <div v-if="store.entries.loadingDelayed" class="dashboard-loader-overlay">
          <div class="dashboard-loader">
            <div class="spinner"></div>
            <span class="dashboard-loader-text">{{ t('loading') }}</span>
          </div>
        </div>

        <LogStats />
        <LogTable />
      </div>
    </template>
  </div>

  <div v-else class="welcome-screen">
    <div class="welcome-icon">
      <IconLogo :width="64" :height="64" :stroke-width="1" />
    </div>
    <h2>{{ t('welcomeTitle') }}</h2>
    <p>{{ t('welcomeText') }}</p>
  </div>
</template>

<script setup lang="ts">
import { watch, onMounted, onUnmounted } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import LogFilters from '@/components/Logs/LogFilters.vue'
import LogStats from '@/components/Logs/LogStats.vue'
import LogTable from '@/components/Logs/LogTable.vue'
import LogSkeleton from '@/components/Logs/LogSkeleton.vue'
import IconLogo from '@/components/icons/IconLogo.vue'

const props = defineProps<{
  sourceId: string
}>()

const store = useLogStore()
const { t } = useI18n()

watch(
  () => {
    return props.sourceId
  },
  (newId) => {
    store.source.id = newId
    store.loadEntries(true, true)
    store.startAutoRefresh()
  },
  { immediate: true },
)

onMounted(() => {
  store.startAutoRefresh()
})

onUnmounted(() => {
  store.stopAutoRefresh()
})
</script>
