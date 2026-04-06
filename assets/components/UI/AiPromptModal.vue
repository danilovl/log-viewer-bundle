<template>
  <div v-if="modelValue" class="ai-modal-overlay" @click.self="emit('update:modelValue', false)">
    <div class="ai-modal">
      <div class="ai-modal-header">
        <h3>{{ t('editPrompt') }} - {{ chatName }}</h3>
        <button class="ai-modal-close" @click="emit('update:modelValue', false)">
          <IconClose :width="16" :height="16" />
        </button>
      </div>
      <div class="ai-modal-body">
        <textarea
          :value="prompt"
          class="ai-modal-textarea"
          @input="emit('update:prompt', ($event.target as HTMLTextAreaElement).value)"
        ></textarea>
      </div>
      <div class="ai-modal-footer">
        <button class="btn btn-ghost" @click="emit('update:modelValue', false)">{{ t('cancel') }}</button>
        <button class="btn btn-primary" @click="emit('send')">{{ t('sendToAI') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n'
import IconClose from '@/components/icons/IconClose.vue'

defineProps<{
  modelValue: boolean
  chatName?: string
  prompt: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'update:prompt', value: string): void
  (e: 'send'): void
}>()

const { t } = useI18n()
</script>
