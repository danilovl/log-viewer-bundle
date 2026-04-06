<template>
  <div v-if="isOpen" class="modal-overlay" @click.self="modalStore.handleCancel">
    <div class="modal-content confirm-modal">
      <div class="modal-header">
        <h3>{{ title || t('delete') }}</h3>
        <button class="modal-close" @click="modalStore.handleCancel">
          <IconClose :width="20" :height="20" />
        </button>
      </div>

      <div class="modal-body">
        <p>{{ message }}</p>
      </div>

      <div class="modal-footer">
        <button class="btn btn-ghost" @click="modalStore.handleCancel">
          {{ cancelText || t('cancel') }}
        </button>
        <button class="btn" :class="type === 'danger' ? 'btn-danger' : 'btn-primary'" @click="modalStore.handleConfirm">
          {{ confirmText || t('apply') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { useModalStore } from '@/stores/useModalStore'
import { useI18n } from '@/i18n/useI18n'
import IconClose from '@/components/icons/IconClose.vue'

const modalStore = useModalStore()
const { isOpen, title, message, confirmText, cancelText, type } = storeToRefs(modalStore)
const { t } = useI18n()
</script>
