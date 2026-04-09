<template>
  <template v-if="node.folders">
    <template v-for="folder in node.folders" :key="'f-' + folder.name">
      <li class="tree-item">
        <div class="tree-folder" :style="{ paddingLeft: depth * 12 + 12 + 'px' }" @click="toggleFolder(folder.name)">
          <IconChevron
            :class-name="'tree-chevron' + (collapsedFolders[folder.name] ? '  is-collapsed' : '')"
            :width="12"
            :height="12"
            :stroke-width="3"
          />
          <IconFolder class-name="tree-icon" :width="16" :height="16" />
          <span>{{ folder.name }}</span>
        </div>
        <ul v-show="!collapsedFolders[folder.name]" class="tree-root">
          <TreeNode :node="folder" :current-source-id="currentSourceId" :depth="depth + 1" />
        </ul>
      </li>
    </template>
  </template>

  <template v-if="node.files">
    <template v-for="file in node.files" :key="'file-' + file.id">
      <li class="tree-item" @click="handleFileClick(file, $event)">
        <div
          :class="fileClasses(file)"
          :data-id="file.id"
          :data-name="file.name"
          :style="{ paddingLeft: depth * 12 + 12 + 'px' }"
        >
          <IconFile class-name="tree-icon" :width="16" :height="16" :title="fileTitle(file)" />
          <div class="tree-file-name-wrapper" :title="fileTitle(file)">
            <span class="tree-file-name">{{ file.name }}</span>
            <span v-if="settingsStore.menuShowFileSize ?? true" class="tree-file-size">{{
              formatBytes(file.size)
            }}</span>
          </div>
          <div
            class="tree-file-settings"
            :class="{ active: store.activeFileDropdownId === file.id }"
            @click.stop="toggleDropdown(file.id, $event)"
          >
            <IconSettings class-name="settings-icon" :width="14" :height="14" />
            <div :class="['settings-dropdown', { show: store.activeFileDropdownId === file.id }]" :title="''">
              <span :title="file.isReadable ? t('viewContent') : t('noReadPermission')">
                <button class="view-action" :disabled="!file.isReadable" @click.stop="handleViewContent(file)">
                  <IconEye :width="14" :height="14" />
                </button>
              </span>
              <span v-if="file.canDownload" :title="file.isDownloadable ? t('download') : t('noDownloadPermission')">
                <button class="download-action" :disabled="!file.isDownloadable" @click.stop="handleDownload(file)">
                  <IconDownload :width="14" :height="14" />
                </button>
              </span>
              <span v-if="file.canDelete" :title="file.isDeletable ? t('delete') : t('noDeletePermission')">
                <button class="delete-action" :disabled="!file.isDeletable" @click.stop="handleDelete(file)">
                  <IconDelete :width="14" :height="14" />
                </button>
              </span>
            </div>
          </div>
        </div>
      </li>
    </template>
  </template>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import type { TreeFolder, TreeFile } from '@/types'
import { formatBytes } from '@/utils/format'
import { useLogStore } from '@/stores/useLogStore'
import { useSettingsStore } from '@/stores/useSettingsStore'
import { useI18n } from '@/i18n/useI18n'
import IconChevron from '@/components/icons/IconChevron.vue'
import IconFolder from '@/components/icons/IconFolder.vue'
import IconFile from '@/components/icons/IconFile.vue'
import IconSettings from '@/components/icons/IconSettings.vue'
import IconDelete from '@/components/icons/IconDelete.vue'
import IconDownload from '@/components/icons/IconDownload.vue'
import IconEye from '@/components/icons/IconEye.vue'

const props = defineProps<{
  node: TreeFolder
  currentSourceId: string
  depth: number
}>()

const store = useLogStore()
const settingsStore = useSettingsStore()
const router = useRouter()
const { t } = useI18n()
const collapsedFolders = ref<Record<string, boolean>>({})

function toggleFolder(folderName: string): void {
  collapsedFolders.value[folderName] = !collapsedFolders.value[folderName]
}

function fileClasses(file: TreeFile): Record<string, boolean> {
  return {
    'tree-file': true,
    active: file.id === props.currentSourceId,
    invalid: !file.isValid,
    empty: file.isEmpty,
    'too-large': file.isTooLarge,
  }
}

function fileTitle(file: TreeFile): string {
  if (file.isTooLarge) {
    return t('fileSizeExceeds')
  }
  if (!file.isValid) {
    return t('couldNotDetect')
  }
  if (file.isEmpty) {
    return t('fileIsEmpty')
  }

  return ''
}

function handleFileClick(file: TreeFile, event: MouseEvent): void {
  if ((event.target as HTMLElement).closest('.tree-file-settings')) {
    return
  }
  if (file.isValid && !file.isEmpty && !file.isTooLarge) {
    router.push({ name: 'logs', params: { sourceId: file.id } })
  }
}

function toggleDropdown(fileId: string, event: Event): void {
  event.stopPropagation()
  if (store.activeFileDropdownId === fileId) {
    store.activeFileDropdownId = null
  } else {
    store.activeFileDropdownId = fileId
  }
}

function handleDelete(file: TreeFile): void {
  store.activeFileDropdownId = null
  store.deleteFile(file.id, file.name)
}

function handleDownload(file: TreeFile): void {
  store.activeFileDropdownId = null
  store.downloadFile(file.id)
}

function handleViewContent(file: TreeFile): void {
  store.activeFileDropdownId = null
  const url = router.resolve({
    name: 'file-reader',
    params: { sourceId: file.id },
  }).href
  window.open(url, '_blank')
}
</script>
