<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal-content">
      <div class="modal-header">
        <h3>{{ t('settings') }}</h3>
        <button class="modal-close" @click="$emit('close')">
          <IconClose :width="20" :height="20" />
        </button>
      </div>

      <div class="modal-body">
        <div class="settings-section">
          <h4>{{ t('menuSection') }}</h4>
          <div class="settings-group">
            <label class="checkbox-label">
              <input v-model="menuShowFileSizeLocal" type="checkbox" />
              {{ t('showFileSize') }}
            </label>
          </div>
        </div>

        <div class="settings-section">
          <h4>{{ t('dashboardSection') }}</h4>
          <div class="settings-group">
            <label class="checkbox-label">
              <input v-model="dashboardPageStatisticEnabledLocal" type="checkbox" />
              {{ t('statisticEnabled') }}
            </label>
          </div>
          <div class="settings-group">
            <label class="checkbox-label">
              <input v-model="dashboardPageAutoRefreshEnabledLocal" type="checkbox" />
              {{ t('autoRefresh') }}
            </label>
          </div>
          <div class="settings-group">
            <label for="dashboard-interval">{{ t('dashboardInterval') }}</label>
            <input
              id="dashboard-interval"
              v-model.number="dashboardPageAutoRefreshIntervalLocal"
              type="number"
              min="0"
              :placeholder="t('default')"
            />
            <span class="help-text">{{ t('seconds') }}</span>
          </div>
          <div class="settings-group">
            <label class="checkbox-label">
              <input v-model="dashboardPageAutoRefreshShowCountdownLocal" type="checkbox" />
              {{ t('showCountdown') }}
            </label>
          </div>
        </div>

        <div class="settings-section">
          <h4>{{ t('logPageSection') }}</h4>
          <div class="settings-group">
            <label class="checkbox-label">
              <input v-model="logPageStatisticEnabledLocal" type="checkbox" />
              {{ t('statisticEnabled') }}
            </label>
          </div>
          <div class="settings-group">
            <label class="checkbox-label">
              <input v-model="logPageAutoRefreshEnabledLocal" type="checkbox" />
              {{ t('autoRefresh') }}
            </label>
          </div>
          <div class="settings-group">
            <label for="log-page-interval">{{ t('logPageInterval') }}</label>
            <input
              id="log-page-interval"
              v-model.number="logPageAutoRefreshIntervalLocal"
              type="number"
              min="0"
              :placeholder="t('default')"
            />
            <span class="help-text">{{ t('seconds') }}</span>
          </div>
          <div class="settings-group">
            <label class="checkbox-label">
              <input v-model="logPageAutoRefreshShowCountdownLocal" type="checkbox" />
              {{ t('showCountdown') }}
            </label>
          </div>
          <div class="settings-group">
            <label for="log-page-limit">{{ t('entriesLimit') }}</label>
            <input
              id="log-page-limit"
              v-model.number="logPageLimitLocal"
              type="number"
              min="1"
              :placeholder="t('default')"
            />
          </div>
        </div>

        <div class="settings-section">
          <h4>{{ t('liveLogSection') }}</h4>
          <div class="settings-group">
            <label for="live-log-interval">{{ t('liveLogInterval') }}</label>
            <input
              id="live-log-interval"
              v-model.number="liveLogPageIntervalLocal"
              type="number"
              min="0"
              :placeholder="t('default')"
            />
            <span class="help-text">{{ t('seconds') }}</span>
          </div>
          <div class="settings-group">
            <label>{{ t('logLevels') }}</label>
            <div class="levels-grid">
              <label v-for="level in logLevels" :key="level" class="checkbox-label level-checkbox">
                <input v-model="liveLogPageLevelsLocal" type="checkbox" :value="level" />
                <span :class="'level-badge  level-' + level.toLowerCase()">{{ level }}</span>
              </label>
            </div>
          </div>
        </div>

        <div class="settings-section">
          <h4>{{ t('aiAssistantSection') }}</h4>
          <div class="settings-group">
            <label>{{ t('aiButtonLevels') }}</label>
            <div class="levels-grid">
              <label v-for="level in logLevels" :key="level" class="checkbox-label level-checkbox">
                <input v-model="aiButtonLevelsLocal" type="checkbox" :value="level" />
                <span :class="'level-badge  level-' + level.toLowerCase()">{{ level }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="settingsStore.resetSettings()">
          {{ t('reset') }}
        </button>
        <button class="btn btn-primary" @click="$emit('close')">
          {{ t('save') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useI18n } from '@/i18n/useI18n'
import { useSettingsStore } from '@/stores/useSettingsStore'
import { useLogStore } from '@/stores/useLogStore'
import { logLevels } from '@/utils/constants'
import IconClose from '@/components/icons/IconClose.vue'

defineEmits(['close'])

const { t } = useI18n()
const settingsStore = useSettingsStore()
const logStore = useLogStore()

const { liveLogPageLevels, aiButtonLevels } = storeToRefs(settingsStore)

const dashboardPageAutoRefreshEnabledLocal = computed({
  get: () => {
    return settingsStore.dashboardPageAutoRefreshEnabled ?? logStore.config.dashboardPageAutoRefreshEnabled
  },
  set: (val) => {
    settingsStore.dashboardPageAutoRefreshEnabled = val
  },
})

const dashboardPageStatisticEnabledLocal = computed({
  get: () => {
    return settingsStore.dashboardPageStatisticEnabled ?? logStore.config.dashboardPageStatisticEnabled
  },
  set: (val) => {
    settingsStore.dashboardPageStatisticEnabled = val
  },
})

const dashboardPageAutoRefreshIntervalLocal = computed({
  get: () => {
    return settingsStore.dashboardPageAutoRefreshInterval ?? logStore.config.dashboardPageAutoRefreshInterval
  },
  set: (val) => {
    settingsStore.dashboardPageAutoRefreshInterval = val
  },
})

const dashboardPageAutoRefreshShowCountdownLocal = computed({
  get: () => {
    return settingsStore.dashboardPageAutoRefreshShowCountdown ?? logStore.config.dashboardPageAutoRefreshShowCountdown
  },
  set: (val) => {
    settingsStore.dashboardPageAutoRefreshShowCountdown = val
  },
})

const logPageStatisticEnabledLocal = computed({
  get: () => {
    return settingsStore.logPageStatisticEnabled ?? logStore.config.logPageStatisticEnabled
  },
  set: (val) => {
    settingsStore.logPageStatisticEnabled = val
  },
})

const logPageAutoRefreshEnabledLocal = computed({
  get: () => {
    return settingsStore.logPageAutoRefreshEnabled ?? logStore.config.logPageAutoRefreshEnabled
  },
  set: (val) => {
    settingsStore.logPageAutoRefreshEnabled = val
  },
})

const logPageAutoRefreshIntervalLocal = computed({
  get: () => {
    return settingsStore.logPageAutoRefreshInterval ?? logStore.config.logPageAutoRefreshInterval
  },
  set: (val) => {
    settingsStore.logPageAutoRefreshInterval = val
  },
})

const logPageAutoRefreshShowCountdownLocal = computed({
  get: () => {
    return settingsStore.logPageAutoRefreshShowCountdown ?? logStore.config.logPageAutoRefreshShowCountdown
  },
  set: (val) => {
    settingsStore.logPageAutoRefreshShowCountdown = val
  },
})

const logPageLimitLocal = computed({
  get: () => {
    return settingsStore.logPageLimit ?? logStore.config.logPageLimit
  },
  set: (val) => {
    settingsStore.logPageLimit = val
  },
})

const liveLogPageIntervalLocal = computed({
  get: () => {
    return settingsStore.liveLogPageInterval ?? logStore.config.liveLogPageInterval
  },
  set: (val) => {
    settingsStore.liveLogPageInterval = val
  },
})

const liveLogPageLevelsLocal = computed({
  get: () => {
    return liveLogPageLevels.value || logStore.live.selectedLevels
  },
  set: (val) => {
    liveLogPageLevels.value = val
  },
})

const aiButtonLevelsLocal = computed({
  get: () => {
    return aiButtonLevels.value || logStore.config.aiButtonLevels
  },
  set: (val) => {
    aiButtonLevels.value = val
  },
})

const menuShowFileSizeLocal = computed({
  get: () => {
    return settingsStore.menuShowFileSize ?? true
  },
  set: (val) => {
    settingsStore.menuShowFileSize = val
  },
})
</script>
