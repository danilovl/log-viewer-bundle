<template>
  <div class="toast-container">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="toast-item"
        :class="`toast-${toast.type}`"
        @click="toastStore.remove(toast.id)"
      >
        <div class="toast-icon">
          <IconAlert v-if="toast.type === 'error' || toast.type === 'warning'" :width="20" :height="20" />
          <IconInfo v-else :width="20" :height="20" />
        </div>
        <div class="toast-message">{{ toast.message }}</div>
        <button class="toast-close">
          <IconClose :width="16" :height="16" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
import { useToastStore } from '@/stores/useToastStore'
import IconAlert from '@/components/icons/IconAlert.vue'
import IconInfo from '@/components/icons/IconInfo.vue'
import IconClose from '@/components/icons/IconClose.vue'

const toastStore = useToastStore()
</script>
