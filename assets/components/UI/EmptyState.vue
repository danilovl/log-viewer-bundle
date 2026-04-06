<template>
  <div class="empty-state">
    <div class="empty-state-icon">
      <slot name="icon">
        <IconInbox :width="48" :height="48" :stroke-width="1.5" />
      </slot>
    </div>
    <h3 class="empty-state-title">
      <slot name="title">{{ title }}</slot>
    </h3>
    <p v-if="description || $slots.description" class="empty-state-description">
      <slot name="description">{{ description }}</slot>
    </p>
    <div v-if="$slots.action" class="empty-state-action">
      <slot name="action"></slot>
    </div>
  </div>
</template>

<script setup lang="ts">
import IconInbox from '@/components/icons/IconInbox.vue'

interface Props {
  title?: string
  description?: string
}

defineProps<Props>()
</script>
