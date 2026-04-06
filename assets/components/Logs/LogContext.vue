<template>
  <div v-if="context && Object.keys(context).length > 0" class="json-context" :class="{ collapsed: !isExpanded }">
    <div class="json-context-header">
      <strong>{{ t('context') }}:</strong>
      <button class="json-toggle-btn" @click="isExpanded = !isExpanded">
        {{ isExpanded ? t('collapse') : t('expand') }}
      </button>
    </div>
    <div class="json-body">
      <span v-if="!isExpanded" class="json-summary">{{ JSON.stringify(context) }}</span>
      <pre v-else class="json-context-expanded">{{ JSON.stringify(context, null, 2) }}</pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from '@/i18n/useI18n'

defineProps<{
  context: Record<string, unknown>
}>()

const { t } = useI18n()
const isExpanded = ref(false)
</script>
