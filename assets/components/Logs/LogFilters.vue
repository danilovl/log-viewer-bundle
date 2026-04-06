<template>
  <div class="filters-card">
    <div class="filters-body">
      <div class="filter-group filter-group-level">
        <label for="filter-level">{{ t('level') }}</label>
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
        <label for="filter-channel">{{ t('channel') }}</label>
        <select id="filter-channel" v-model="store.filters.filterChannel" class="filter-input">
          <option value="">{{ t('allChannels') }}</option>
          <option v-for="ch in store.filters.channels" :key="ch" :value="ch">
            {{ ch }}
          </option>
        </select>
      </div>
      <div class="filter-group filter-group-search">
        <label for="filter-search">{{ t('search') }}</label>
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
              @click="store.filters.filterSearchRegex = !store.filters.filterSearchRegex"
            >
              .*
            </button>
            <button
              class="btn-icon"
              :class="{ active: store.filters.filterSearchCaseSensitive }"
              :title="t('searchCaseSensitive')"
              @click="store.filters.filterSearchCaseSensitive = !store.filters.filterSearchCaseSensitive"
            >
              Aa
            </button>
            <button
              class="btn-icon"
              :class="{ active: store.filters.filterSearchHighlight }"
              :title="t('highlightSearch')"
              @click="store.filters.filterSearchHighlight = !store.filters.filterSearchHighlight"
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
        <button class="btn btn-ghost" @click="store.resetFilters()">{{ t('reset') }}</button>
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
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import { logLevels } from '@/utils/constants'
import IconRefresh from '@/components/icons/IconRefresh.vue'
import IconHighlight from '@/components/icons/IconHighlight.vue'
import RefreshCountdown from '@/components/UI/RefreshCountdown.vue'

const store = useLogStore()
const { t } = useI18n()
</script>
