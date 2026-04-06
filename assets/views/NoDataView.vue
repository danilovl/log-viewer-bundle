<template>
  <div class="no-data-full-page">
    <div class="no-data-content">
      <div class="no-data-icon-wrapper">
        <IconFileX :width="64" :height="64" />
      </div>
      <h1 class="no-data-title">{{ t('noLogFilesSelected') }}</h1>
      <p class="no-data-message">{{ t('noDataAvailable') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '@/i18n/useI18n'
import IconFileX from '@/components/icons/IconFileX.vue'

const { t } = useI18n()
</script>
