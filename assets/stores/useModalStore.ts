import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useModalStore = defineStore('modal', () => {
  const isOpen = ref(false)
  const title = ref('')
  const message = ref('')
  const confirmText = ref('')
  const cancelText = ref('')
  const type = ref<'primary' | 'danger'>('primary')
  const resolvePromise = ref<((value: boolean) => void) | null>(null)

  function confirm(options: {
    title?: string
    message: string
    confirmText?: string
    cancelText?: string
    type?: 'primary' | 'danger'
  }): Promise<boolean> {
    return new Promise((resolve) => {
      title.value = options.title || ''
      message.value = options.message
      confirmText.value = options.confirmText || ''
      cancelText.value = options.cancelText || ''
      type.value = options.type || 'primary'
      isOpen.value = true
      resolvePromise.value = resolve
    })
  }

  function handleConfirm() {
    isOpen.value = false
    if (resolvePromise.value) {
      resolvePromise.value(true)
      resolvePromise.value = null
    }
  }

  function handleCancel() {
    isOpen.value = false
    if (resolvePromise.value) {
      resolvePromise.value(false)
      resolvePromise.value = null
    }
  }

  return {
    isOpen,
    title,
    message,
    confirmText,
    cancelText,
    type,
    confirm,
    handleConfirm,
    handleCancel,
  }
})
