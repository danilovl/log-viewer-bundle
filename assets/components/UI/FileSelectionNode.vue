<template>
  <template v-if="node.folders">
    <template v-for="folder in node.folders" :key="'f-' + folder.name">
      <li class="tree-item">
        <div class="tree-folder" :style="{ paddingLeft: depth * 16 + 'px' }" @click="toggleFolder(folder.name)">
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
          <FileSelectionNode
            :node="folder"
            :selected-ids="selectedIds"
            :depth="depth + 1"
            @toggle="emit('toggle', $event)"
          />
        </ul>
      </li>
    </template>
  </template>

  <template v-if="node.files">
    <template v-for="file in node.files" :key="'file-' + file.id">
      <li class="tree-item">
        <div class="tree-file" :style="{ paddingLeft: depth * 16 + 'px' }">
          <label class="checkbox-wrapper" @click.stop>
            <input
              type="checkbox"
              class="source-checkbox"
              :checked="selectedIds.includes(file.id)"
              @change="emit('toggle', file.id)"
            />
          </label>
          <div class="tree-file-info" @click="emit('toggle', file.id)">
            <IconFile class-name="tree-icon" :width="16" :height="16" />
            <div class="tree-file-name-wrapper">
              <span :class="{ 'text-muted': !file.isValid || file.isEmpty || file.isTooLarge }">
                {{ file.name }}
              </span>
              <span class="tree-file-size">{{ formatBytes(file.size) }}</span>
            </div>
          </div>
        </div>
      </li>
    </template>
  </template>
</template>

<script setup lang="ts">
import { ref, withDefaults } from 'vue'
import type { TreeFolder } from '@/types'
import { formatBytes } from '@/utils/format'
import IconChevron from '@/components/icons/IconChevron.vue'
import IconFolder from '@/components/icons/IconFolder.vue'
import IconFile from '@/components/icons/IconFile.vue'

withDefaults(
  defineProps<{
    node: TreeFolder
    selectedIds: string[]
    depth?: number
  }>(),
  {
    depth: 0,
  },
)

const emit = defineEmits<{
  (e: 'toggle', id: string): void
}>()

const collapsedFolders = ref<Record<string, boolean>>({})

function toggleFolder(folderName: string): void {
  collapsedFolders.value[folderName] = !collapsedFolders.value[folderName]
}
</script>
