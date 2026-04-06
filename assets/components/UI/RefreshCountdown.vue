<template>
  <div v-if="countdown !== null" v-tooltip="tooltip" class="refresh-countdown">
    <span v-if="showLabel" class="countdown-label">{{ t('refreshIn') }}:</span>
    <span class="countdown-value">{{ countdown }}s</span>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n'

defineProps<{
  countdown: number | null
  showLabel?: boolean
  tooltip?: string
}>()

const { t } = useI18n()
</script>
