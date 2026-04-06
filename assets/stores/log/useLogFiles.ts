import { useI18n } from '@/i18n/useI18n'
import router from '@/router'
import { deleteLogFile as apiDeleteLogFile, downloadLogFile } from '@/services/api'
import { useModalStore } from '@/stores/useModalStore'

export function useLogFiles(source: { id: string; path: string }, loadStructure: () => Promise<void>) {
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
      if (source.id === id) {
        router.push({ name: 'dashboard' })
      } else {
        await loadStructure()
      }
    } catch (e: any) {
      console.error('Error:', e)
    }
  }

  async function downloadFile(id: string): Promise<void> {
    try {
      const blob = await downloadLogFile(id)
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
    downloadFile,
  }
}
