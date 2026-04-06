<template>
  <div v-if="modelValue" class="modal-overlay" @click="close">
    <div class="modal-content" @click.stop>
      <div class="modal-header">
        <div class="header-left">
          <h3>{{ title }}</h3>
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
        <div class="file-tree-container">
          <ul class="tree-root">
            <template v-for="root in store.structure" :key="'root-' + root.name">
              <li class="tree-root-item">
                <div class="tree-root-header">
                  <IconPackage :width="16" :height="16" class-name="tree-icon" />
                  <span>{{ root.name }}</span>
                </div>
                <ul class="tree-root-children">
                  <FileSelectionNode :node="root" :selected-ids="selectedIds" @toggle="toggleFile" />
                </ul>
              </li>
            </template>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <div class="selected-count">{{ t('selectedFiles') }}: {{ selectedIds.length }}</div>
        <button class="btn btn-primary" @click="close">
          {{ t('apply') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import IconClose from '@/components/icons/IconClose.vue'
import IconPackage from '@/components/icons/IconPackage.vue'
import FileSelectionNode from './FileSelectionNode.vue'

defineProps<{
  modelValue: boolean
  title: string
  selectedIds: string[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'toggle', id: string): void
  (e: 'selectAll', ids: string[]): void
  (e: 'resetAll'): void
}>()

const store = useLogStore()
const { t } = useI18n()

function close(): void {
  emit('update:modelValue', false)
}

function toggleFile(id: string): void {
  emit('toggle', id)
}

function selectAll(): void {
  const allIds = store.getAllFileIds()
  emit('selectAll', allIds)
}

function resetAll(): void {
  emit('resetAll')
}
</script>
