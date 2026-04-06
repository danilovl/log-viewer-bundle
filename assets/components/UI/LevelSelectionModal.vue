<template>
  <div v-if="modelValue" class="modal-overlay" @click="close">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <div class="header-left">
          <h3>{{ t('selectLevels') }}</h3>
          <div class="header-actions">
            <button class="btn btn-sm btn-outline" @click="selectAll">
              {{ t('selectAll') }}
            </button>
            <button class="btn btn-sm btn-outline" @click="resetAll">
              {{ t('resetAll') }}
            </button>
          </div>
        </div>
        <button class="close-btn" @click="close">
          <IconClose :width="20" :height="20" />
        </button>
      </div>
      <div class="modal-body">
        <div class="level-selection-list">
          <div v-for="level in logLevels" :key="level" class="level-selection-item">
            <label :for="'level-' + level" class="checkbox-label">
              <input
                :id="'level-' + level"
                type="checkbox"
                :checked="selectedLevels.includes(level)"
                @change="toggleLevel(level)"
              />
              <span :class="'level-badge level-' + level.toLowerCase()">{{ level }}</span>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="selected-count">{{ t('selectedLevels') }}: {{ selectedLevels.length }}</div>
        <button class="btn btn-primary" @click="close">
          {{ t('apply') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n'
import { logLevels } from '@/utils/constants'
import IconClose from '@/components/icons/IconClose.vue'

const { t } = useI18n()

withDefaults(
  defineProps<{
    modelValue: boolean
    selectedLevels: string[]
  }>(),
  {
    modelValue: false,
    selectedLevels: () => {
      return []
    },
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'toggle', level: string): void
  (e: 'selectAll'): void
  (e: 'resetAll'): void
}>()

function close(): void {
  emit('update:modelValue', false)
}

function toggleLevel(level: string): void {
  emit('toggle', level)
}

function selectAll(): void {
  emit('selectAll')
}

function resetAll(): void {
  emit('resetAll')
}
</script>
