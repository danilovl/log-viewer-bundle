import { ref } from 'vue'
import type { LogEntry, AiChat } from '../types'

export function useAiPrompt() {
  const promptEditorVisible = ref(false)
  const currentPrompt = ref('')
  const targetChat = ref<AiChat | null>(null)

  function openAiEditor(chat: AiChat, entry: LogEntry) {
    const context =
      entry.context && Object.keys(entry.context).length > 0 ? '\nContext:  ' + JSON.stringify(entry.context) : ''
    let prompt = `Analyze  this  error:  ${entry.message}${context}`

    if (prompt.length > 1500) {
      prompt = prompt.substring(0, 1500) + '...  [truncated]'
    }

    currentPrompt.value = prompt
    targetChat.value = chat
    promptEditorVisible.value = true
  }

  function sendToAi() {
    if (!targetChat.value) {
      return
    }
    const url = targetChat.value.url + encodeURIComponent(currentPrompt.value)
    window.open(url, '_blank')
    promptEditorVisible.value = false
  }

  return {
    promptEditorVisible,
    currentPrompt,
    targetChat,
    openAiEditor,
    sendToAi,
  }
}
