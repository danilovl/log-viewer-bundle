<template>
  <aside v-if="!store.isZenMode" :class="['sidebar', { 'mobile-open': store.sidebarMobileOpen }]">
    <div class="sidebar-header">
      <div class="logo">
        <IconLogo :width="24" :height="24" />
        <span>{{ t('logViewer') }}</span>
      </div>
      <router-link id="btn-show-dashboard" to="/dashboard" class="source-item" active-class="active">
        <IconDashboard class-name="nav-icon" :width="18" :height="18" />
        <span class="nav-text">{{ t('dashboard') }}</span>
      </router-link>
      <router-link
        v-if="store.config.liveLogPageEnabled"
        id="btn-show-live"
        to="/live"
        class="source-item"
        active-class="active"
      >
        <IconZap class-name="nav-icon" :width="18" :height="18" />
        <span class="nav-text">{{ t('liveUpdate') }}</span>
      </router-link>
      <router-link id="btn-show-global-search" to="/global-search" class="source-item" active-class="active">
        <IconSearch class-name="nav-icon" :width="18" :height="18" />
        <span class="nav-text">{{ t('globalSearch') }}</span>
      </router-link>
      <router-link id="btn-show-bookmarks" to="/bookmarks" class="source-item" active-class="active">
        <div class="nav-icon-container">
          <IconStar class-name="nav-icon" :width="18" :height="18" />
          <span v-if="bookmarksCount > 0" class="nav-badge">{{ bookmarksCount }}</span>
        </div>
        <span class="nav-text">{{ t('bookmarks') }}</span>
      </router-link>
    </div>

    <div class="sidebar-content">
      <div class="sidebar-label">{{ t('logSources') }}</div>
      <nav class="source-nav">
        <ul id="source-tree" class="tree-root">
          <li v-for="rootNode in store.structure" :key="rootNode.name" class="tree-item root">
            <div class="tree-folder" @click.stop="toggleRootFolder(rootNode.name)">
              <IconChevron
                :class-name="'tree-chevron' + (collapsedRoots[rootNode.name] ? '  is-collapsed' : '')"
                :width="12"
                :height="12"
                :stroke-width="3"
              />
              <IconFolder class-name="tree-icon" :width="16" :height="16" />
              <span>{{ rootNode.name }}</span>
            </div>
            <ul v-show="!collapsedRoots[rootNode.name]" class="tree-root">
              <TreeNode :node="rootNode" :current-source-id="store.source.id" :depth="1" />
            </ul>
          </li>
        </ul>
      </nav>
    </div>

    <div class="sidebar-resize-handle" @mousedown.prevent="startResize"></div>
  </aside>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useLogBookmarks } from '@/stores/log/useLogBookmarks'
import { useI18n } from '@/i18n/useI18n'
import { useSidebarResize } from '@/composables/useSidebarResize'
import IconLogo from '@/components/icons/IconLogo.vue'
import IconDashboard from '@/components/icons/IconDashboard.vue'
import IconZap from '@/components/icons/IconZap.vue'
import IconSearch from '@/components/icons/IconSearch.vue'
import IconStar from '@/components/icons/IconStar.vue'
import IconChevron from '@/components/icons/IconChevron.vue'
import IconFolder from '@/components/icons/IconFolder.vue'
import TreeNode from '@/components/Sidebar/TreeNode.vue'

const store = useLogStore()
const { bookmarksCount } = useLogBookmarks()
const { t } = useI18n()
const { startResize } = useSidebarResize()
const collapsedRoots = ref<Record<string, boolean>>({})

function toggleRootFolder(name: string): void {
  collapsedRoots.value[name] = !collapsedRoots.value[name]
}
</script>
