<template>
  <div class="file-reader-container">
    <div v-if="loading && lines.length === 0" class="reader-loading">
      <div class="reader-spinner"></div>
      <span>{{ t('loading') }}...</span>
    </div>

    <div v-else-if="lines.length === 0" class="reader-empty">
      {{ t('noEntries') }}
    </div>

    <div v-else ref="scrollContainer" class="reader-content" @scroll="handleScroll">
      <div class="reader-lines" :style="{ fontSize: fontSize + 'px' }">
        <div v-for="(line, index) in lines" :key="index" class="reader-line">
          <span class="line-number">{{ startLine + index + 1 }}</span>
          <pre class="line-text">{{ line }}</pre>
        </div>
      </div>
      <div v-if="loading && lines.length > 0" class="reader-loading-more">
        <div class="reader-spinner"></div>
      </div>
    </div>

    <div class="reader-footer">
      <div class="footer-info">
        <div class="footer-left">
          <span>{{ sourceName }}</span>
          <span>{{ lines.length }} / {{ totalLines }} {{ t('lines') }}</span>
        </div>
        <div class="footer-right">
          <div class="footer-jump">
            <input v-model.number="jumpLine" type="number" class="jump-input" @keyup.enter="handleJump" />
            <button class="jump-btn" @click="handleJump">{{ t('jump') }}</button>
          </div>
          <div class="font-controls">
            <button class="font-btn" :title="t('decreaseFontSize')" @click="decreaseFontSize">-</button>
            <span class="font-size-label">{{ fontSize }}px</span>
            <button class="font-btn" :title="t('increaseFontSize')" @click="increaseFontSize">+</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from '@/i18n/useI18n'
import { fetchFileContent, fetchStructure } from '@/services/api'
import type { TreeFile, TreeFolder, TreeRootNode } from '@/types'

const props = defineProps<{
  sourceId: string
}>()

const { t } = useI18n()
const route = useRoute()
const lines = ref<string[]>([])
const page = ref(1)
const limit = 100
const totalLines = ref(0)
const loading = ref(false)
const hasMore = ref(true)
const scrollContainer = ref<HTMLElement | null>(null)
const sourceName = ref('')
const fontSize = ref(13)
const startLine = ref(0)
const jumpLine = ref<number | null>(null)

async function loadLines() {
  if (loading.value || !hasMore.value) {
    return
  }

  loading.value = true
  try {
    const data = await fetchFileContent(props.sourceId, page.value, limit)
    if (data.lines.length < limit) {
      hasMore.value = false
    }
    lines.value.push(...data.lines)
    totalLines.value = data.totalLines
    page.value++
  } finally {
    loading.value = false
  }
}

async function handleJump() {
  if (jumpLine.value === null || jumpLine.value < 1) {
    return
  }

  const line = jumpLine.value - 1
  loading.value = true
  try {
    const data = await fetchFileContent(props.sourceId, 1, limit, line)
    lines.value = data.lines
    totalLines.value = data.totalLines
    page.value = data.page
    startLine.value = line
    hasMore.value = data.lines.length >= limit

    if (scrollContainer.value) {
      scrollContainer.value.scrollTop = 0
    }
  } finally {
    loading.value = false
    jumpLine.value = null
  }
}

function handleScroll() {
  if (!scrollContainer.value) {
    return
  }

  const { scrollTop, scrollHeight, clientHeight } = scrollContainer.value
  if (scrollTop + clientHeight >= scrollHeight - 200) {
    loadLines()
  }
}

function findFileName(nodes: (TreeRootNode | TreeFolder)[], id: string): string | null {
  for (const node of nodes) {
    if ('files' in node && node.files) {
      const file = node.files.find((f: TreeFile) => {
        return f.id === id
      })
      if (file) {
        return file.name
      }
    }
    if ('folders' in node && node.folders) {
      const name = findFileName(node.folders, id)
      if (name) {
        return name
      }
    }
  }

  return null
}

function increaseFontSize() {
  fontSize.value = Math.min(fontSize.value + 1, 30)
}

function decreaseFontSize() {
  fontSize.value = Math.max(fontSize.value - 1, 8)
}

onMounted(async () => {
  const queryLine = route.query.line ? parseInt(route.query.line as string) : null
  if (queryLine !== null && !isNaN(queryLine) && queryLine >= 0) {
    jumpLine.value = queryLine + 1
    await handleJump()
  } else {
    await loadLines()
  }

  const structure = await fetchStructure()
  sourceName.value = findFileName(structure, props.sourceId) || ''
})
</script>
