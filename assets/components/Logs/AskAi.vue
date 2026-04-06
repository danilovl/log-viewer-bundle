<template>
  <div v-if="isErrorLevel(entry.level)" class="log-actions">
    <div ref="containerRef" class="ask-ai-container" :class="{ 'is-open': isOpen }">
      <button class="ask-ai-btn" :title="t('askAI')" :class="{ 'is-active': isOpen }" @click.stop="toggleMenu">
        <IconRobot :width="14" :height="14" />
        <span>{{ t('askAI') }}</span>
      </button>
      <div v-if="isOpen" :class="['ai-menu', direction]">
        <div class="ai-menu-header">{{ t('askWithPrompt') }}</div>
        <button
          v-for="chat in aiChatsWithPrompt"
          :key="chat.name"
          type="button"
          class="ai-menu-item"
          @click="openAiEditor(chat)"
        >
          <span class="ai-menu-chat-name">{{ chat.name }}</span>
        </button>

        <div class="ai-menu-divider"></div>
        <div class="ai-menu-header">{{ t('justOpenChat') }}</div>

        <a
          v-for="chat in aiChatsWithoutPrompt"
          :key="chat.name"
          :href="getAiUrl(chat)"
          target="_blank"
          class="ai-menu-item"
          @click="isOpen = false"
        >
          <span class="ai-menu-chat-name">{{ chat.name }}</span>
        </a>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import type { LogEntry, AiChat } from '@/types'
import IconRobot from '@/components/icons/IconRobot.vue'

const props = defineProps<{
  entry: LogEntry
}>()

const emit = defineEmits<{
  (e: 'open-editor', chat: AiChat, entry: LogEntry): void
}>()

const { t } = useI18n()
const store = useLogStore()

const aiChats = computed(() => {
  return store.effective.aiChats
})

const aiChatsWithPrompt = computed(() => {
  return aiChats.value.filter((c: AiChat) => {
    return c.hasPrompt
  })
})

const aiChatsWithoutPrompt = computed(() => {
  return aiChats.value.filter((c: AiChat) => {
    return !c.hasPrompt
  })
})

const entryKey = computed(() => {
  return props.entry.timestamp + props.entry.message + props.entry.level + (props.entry.channel || '')
})

const isOpen = computed({
  get: () => {
    return store.openedAiEntryKey === entryKey.value
  },
  set: (val) => {
    store.setOpenedAiEntryKey(val ? entryKey.value : null)

    return
  },
})

const direction = ref<'up' | 'down'>('down')
const containerRef = ref<HTMLElement | null>(null)

function isErrorLevel(level: string): boolean {
  if (!store.effective.aiButtonLevels || store.effective.aiButtonLevels.length === 0) {
    return false
  }

  return store.effective.aiButtonLevels.includes(level.toUpperCase())
}

function toggleMenu(event: Event): void {
  if (!isOpen.value) {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
    const spaceBelow = window.innerHeight - rect.bottom
    direction.value = spaceBelow < 220 ? 'up' : 'down'
    isOpen.value = true
  } else {
    isOpen.value = false
  }
}

function openAiEditor(chat: AiChat): void {
  isOpen.value = false
  emit('open-editor', chat, props.entry)
}

function getAiUrl(chat: AiChat): string {
  if (!chat.hasPrompt) {
    return chat.url
  }
  const context =
    props.entry.context && Object.keys(props.entry.context).length > 0
      ? '\nContext:  ' + JSON.stringify(props.entry.context)
      : ''
  let prompt = `Analyze  this  error:  ${props.entry.message}${context}`

  if (prompt.length > 1500) {
    prompt = prompt.substring(0, 1500) + '...  [truncated]'
  }

  return chat.url + encodeURIComponent(prompt)
}

function handleOutsideClick(event: Event): void {
  if (isOpen.value && containerRef.value && !containerRef.value.contains(event.target as Node)) {
    isOpen.value = false
  }
}

onMounted(() => {
  window.addEventListener('click', handleOutsideClick)
})

onUnmounted(() => {
  window.removeEventListener('click', handleOutsideClick)
})
</script>
