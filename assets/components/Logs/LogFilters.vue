<template>
  <div class="filters-card">
    <div class="filters-body">
      <div class="filter-group filter-group-level">
        <select id="filter-level" v-model="store.filters.filterLevel" class="filter-input">
          <option value="">{{ t('selectLevel') }}</option>
          <option
            v-for="level in store.filters.levels.length > 0 ? store.filters.levels : logLevels"
            :key="level"
            :value="level"
          >
            {{ level }}
            {{ store.entries.stats?.levels?.[level] !== undefined ? `(${store.entries.stats?.levels?.[level]})` : '' }}
          </option>
        </select>
      </div>
      <div v-if="store.effective.logPageStatisticEnabled" class="filter-group filter-group-channel">
        <select id="filter-channel" v-model="store.filters.filterChannel" class="filter-input">
          <option value="">{{ t('allChannels') }}</option>
          <option v-for="ch in store.filters.channels" :key="ch" :value="ch">
            {{ ch }}
          </option>
        </select>
      </div>
      <div class="filter-group filter-group-date">
        <VueDatePicker
          v-model="store.filters.filterDateFrom"
          format="yyyy-MM-dd HH:mm:ss"
          model-type="yyyy-MM-dd HH:mm:ss"
          text-input
          :enable-time-picker="true"
          :is-24="true"
          :placeholder="t('dateFrom')"
          class="filter-input-datepicker"
          @update:model-value="onDateFromChange"
        />
      </div>
      <div class="filter-group filter-group-date">
        <VueDatePicker
          v-model="store.filters.filterDateTo"
          format="yyyy-MM-dd HH:mm:ss"
          model-type="yyyy-MM-dd HH:mm:ss"
          text-input
          :enable-time-picker="true"
          :is-24="true"
          :placeholder="t('dateTo')"
          class="filter-input-datepicker"
          @update:model-value="onDateToChange"
        />
      </div>
      <div class="filter-group filter-group-search">
        <div class="search-input-wrapper">
          <input
            id="filter-search"
            v-model="store.filters.filterSearch"
            type="text"
            class="filter-input"
            :placeholder="t('searchPlaceholder')"
            @keyup.enter="store.applyFilters()"
          />
          <div class="search-options">
            <button
              class="btn-icon"
              :class="{ active: store.filters.filterSearchRegex }"
              :title="t('searchRegex')"
              @click="
                () => {
                  store.filters.filterSearchRegex = !store.filters.filterSearchRegex
                  store.filters.syncFiltersToUrl()
                }
              "
            >
              .*
            </button>
            <div class="templates-dropdown">
              <button
                class="btn-icon"
                :class="{ active: showTemplates }"
                :title="t('searchTemplates')"
                @click.stop="showTemplates = !showTemplates"
              >
                <IconZap :width="14" :height="14" />
              </button>
              <div v-if="showTemplates" class="templates-menu">
                <div
                  v-for="template in store.effective.regexTemplates"
                  :key="template.label"
                  class="templates-item"
                  @click="selectTemplate(template.value)"
                >
                  {{ template.label }}
                </div>
              </div>
            </div>
            <button
              class="btn-icon"
              :class="{ active: store.filters.filterSearchCaseSensitive }"
              :title="t('searchCaseSensitive')"
              @click="
                () => {
                  store.filters.filterSearchCaseSensitive = !store.filters.filterSearchCaseSensitive
                  store.filters.syncFiltersToUrl()
                }
              "
            >
              Aa
            </button>
            <button
              class="btn-icon"
              :class="{ active: store.filters.filterSearchHighlight }"
              :title="t('highlightSearch')"
              @click="
                () => {
                  store.filters.filterSearchHighlight = !store.filters.filterSearchHighlight
                  store.filters.syncFiltersToUrl()
                }
              "
            >
              <IconHighlight :width="14" :height="14" />
            </button>
          </div>
        </div>
      </div>
      <div class="filter-actions">
        <RefreshCountdown
          v-if="store.effective.showLogRefreshCountdown"
          :countdown="store.entries.refreshCountdown"
          :tooltip="t('nextRefresh')"
        />
        <button
          class="btn btn-primary"
          style="min-width: 120px"
          :disabled="store.entries.loading"
          @click="store.applyFilters()"
        >
          <span
            v-if="store.entries.loadingDelayed"
            class="spinner spinner-sm spinner-light"
            style="vertical-align: middle; margin-right: 0.5rem"
          ></span>
          {{ t('search') }}
        </button>
        <button v-tooltip="t('reset')" class="btn btn-ghost" @click="store.resetFilters()">
          <IconDelete :width="16" :height="16" />
        </button>
        <button
          v-tooltip="t('refreshTitle')"
          class="btn btn-ghost"
          :disabled="store.entries.loading"
          @click="store.refresh()"
        >
          <IconRefresh :width="16" :height="16" :style="{ opacity: store.entries.loading ? 0.5 : 1 }" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { VueDatePicker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import { logLevels } from '@/utils/constants'
import IconRefresh from '@/components/icons/IconRefresh.vue'
import IconHighlight from '@/components/icons/IconHighlight.vue'
import IconZap from '@/components/icons/IconZap.vue'
import IconDelete from '@/components/icons/IconDelete.vue'
import RefreshCountdown from '@/components/UI/RefreshCountdown.vue'
import { onMounted, onUnmounted, ref } from 'vue'

const store = useLogStore()
const { t } = useI18n()
const showTemplates = ref(false)

function selectTemplate(value: string): void {
  store.filters.filterSearch = value
  store.filters.filterSearchRegex = true
  showTemplates.value = false

  return
}

function handleClickOutside(event: MouseEvent): void {
  const target = event.target as HTMLElement
  if (!target.closest('.templates-dropdown')) {
    showTemplates.value = false
  }

  return
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)

  return
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)

  return
})

function onDateFromChange(value: string | null): void {
  if (value && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    store.filters.filterDateFrom = `${value} 00:00:00`
  }

  return
}

function onDateToChange(value: string | null): void {
  if (value && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
    store.filters.filterDateTo = `${value} 23:59:59`
  }

  return
}
</script>
