import { useI18n } from '@/i18n/useI18n'
import router from '@/router'
import { deleteLogFile as apiDeleteLogFile, clearLogFile as apiClearLogFile, downloadLogFile } from '@/services/api'
import { useModalStore } from '@/stores/useModalStore'

export function useLogFiles(
  source: { id: string; path: string },
  loadStructure: () => Promise<void>,
  onClear?: () => void,
) {
  const { t } = useI18n()
  const modalStore = useModalStore()

  async function deleteFile(id: string, name: string): Promise<void> {
    const confirmed = await modalStore.confirm({
      title: t('delete'),
      message: t('deleteConfirm', { name }),
      type: 'danger',
      confirmText: t('delete'),
    })

    if (!confirmed) {
      return
    }

    try {
      await apiDeleteLogFile(id)
      await loadStructure()
      if (source.id === id) {
        router.push({ name: 'dashboard' })
      }
    } catch (e: any) {
      console.error('Error:', e)
    }
  }

  async function clearFile(id: string, name: string): Promise<void> {
    const confirmed = await modalStore.confirm({
      title: t('clear'),
      message: t('clearConfirm', { name }),
      type: 'danger',
      confirmText: t('clear'),
    })

    if (!confirmed) {
      return
    }

    try {
      await apiClearLogFile(id)
      await loadStructure()
      if (source.id === id) {
        if (onClear) {
          onClear()
        }

        router.push({ name: 'logs', params: { sourceId: id } })
      }
    } catch (e: any) {
      console.error('Error:', e)
    }
  }

  async function downloadFile(id: string): Promise<void> {
    try {
      const blob = await downloadLogFile(id)
      if (!blob) {
        return
      }

      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')

      const fileName = source.path.split('/').pop() || 'log.log'

      link.href = url
      link.setAttribute('download', fileName)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      window.URL.revokeObjectURL(url)
    } catch (e: any) {
      console.error('Download  failed:', e)
    }
  }

  return {
    deleteFile,
    clearFile,
    downloadFile,
  }
}
