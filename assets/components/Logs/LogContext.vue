<template>
  <div v-if="context && Object.keys(context).length > 0" class="json-context" :class="{ collapsed: !isExpanded }">
    <div class="json-context-header">
      <strong>{{ t('context') }}:</strong>
      <button class="json-toggle-btn" @click="isExpanded = !isExpanded">
        {{ isExpanded ? t('collapse') : t('expand') }}
      </button>
    </div>
    <div class="json-body">
      <span
        v-if="!isExpanded"
        class="json-summary"
        v-html="
          highlight(JSON.stringify(context), store.filters.filterSearchHighlight ? store.filters.filterSearch : '')
        "
      ></span>
      <pre
        v-else
        class="json-context-expanded"
        v-html="
          highlight(
            JSON.stringify(context, null, 2),
            store.filters.filterSearchHighlight ? store.filters.filterSearch : '',
          )
        "
      ></pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from '@/i18n/useI18n'
import { useLogStore } from '@/stores/useLogStore'
import { useHighlight } from '@/composables/useHighlight'

defineProps<{
  context: Record<string, unknown>
}>()

const store = useLogStore()
const { highlight } = useHighlight()
const { t } = useI18n()
const isExpanded = ref(false)
</script>
