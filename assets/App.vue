<template>
  <div v-if="store.isStructureLoaded && !store.hasAnyFiles">
    <NoDataView />
    <ToastContainer />
  </div>
  <div v-else class="app-container" @click="handleGlobalClick">
    <SidebarLayout />

    <main :class="['main-content', { 'zen-mode': store.isZenMode }]">
      <header v-if="!store.isZenMode" class="main-header">
        <div class="header-left">
          <button
            id="sidebar-toggle"
            class="btn btn-ghost btn-sm"
            style="display: none; padding: 0.25rem"
            @click.stop="store.sidebarMobileOpen = !store.sidebarMobileOpen"
          >
            <IconMenu :width="24" :height="24" />
          </button>
          <div class="breadcrumb">
            <router-link to="/dashboard" class="breadcrumb-item">
              <IconDashboard class-name="breadcrumb-icon" :width="18" :height="18" />
              <span class="breadcrumb-text">{{ t('dashboard') }}</span>
            </router-link>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-item active">
              <template v-if="route.name === 'logs' && store.source.id">
                <span>{{ store.source.name || t('selectSource') }}</span>
                <span v-if="store.source.path" class="breadcrumb-path">
                  {{ store.source.path }}
                </span>
              </template>
              <template v-else-if="route.name === 'live'">
                {{ t('liveUpdate') }}
              </template>
              <template v-else-if="route.name === 'global-search'">
                {{ t('globalSearch') }}
              </template>
              <template v-else-if="route.name === 'bookmarks'">
                {{ t('bookmarks') }}
              </template>
              <template v-else>{{ t('generalStats') }}</template>
            </span>
          </div>
        </div>
        <div class="header-right">
          <div v-if="store.entries.responseTime !== null" class="header-response-time">
            <IconClock :width="14" :height="14" />
            <span>{{ store.entries.responseTime }}</span>
            ms
          </div>
          <select
            class="header-locale-select"
            :value="currentLocale"
            @change="setLocale(($event.target as HTMLSelectElement).value as Locale)"
          >
            <option v-for="opt in localeOptions" :key="opt.code" :value="opt.code">{{ opt.name }}</option>
          </select>
          <button class="header-btn" :title="t('zenMode')" @click="store.isZenMode = true">
            <IconMaximize :width="18" :height="18" />
          </button>
          <button class="header-btn" :title="t('settings')" @click="isSettingsOpen = true">
            <IconSettings :width="18" :height="18" />
          </button>
          <button class="header-btn" @click="toggleTheme">
            <IconSun v-if="currentTheme === 'dark'" :width="18" :height="18" />
            <IconMoon v-else :width="18" :height="18" />
          </button>
        </div>
      </header>

      <div class="content-body">
        <router-view />
        <button v-if="store.isZenMode" class="btn-exit-zen" :title="t('zenMode')" @click="store.isZenMode = false">
          <IconMaximize :width="20" :height="20" />
        </button>
      </div>
      <SettingsModal v-if="isSettingsOpen" @close="isSettingsOpen = false" />
      <ConfirmModal />
      <Tooltip />
      <ToastContainer />
    </main>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router'
import { ref } from 'vue'
import { useLogStore } from '@/stores/useLogStore'
import { useI18n } from '@/i18n/useI18n'
import { useTheme } from '@/composables/useTheme'
import type { Locale } from '@/i18n/translations'
import SidebarLayout from '@/components/Sidebar/SidebarLayout.vue'
import NoDataView from '@/views/NoDataView.vue'
import SettingsModal from '@/components/UI/SettingsModal.vue'
import ConfirmModal from '@/components/UI/ConfirmModal.vue'
import Tooltip from '@/components/UI/Tooltip.vue'
import ToastContainer from '@/components/UI/ToastContainer.vue'
import IconMenu from '@/components/icons/IconMenu.vue'
import IconDashboard from '@/components/icons/IconDashboard.vue'
import IconClock from '@/components/icons/IconClock.vue'
import IconSun from '@/components/icons/IconSun.vue'
import IconMoon from '@/components/icons/IconMoon.vue'
import IconSettings from '@/components/icons/IconSettings.vue'
import IconMaximize from '@/components/icons/IconMaximize.vue'

const store = useLogStore()
const route = useRoute()
const { t, currentLocale, setLocale, localeOptions } = useI18n()
const { currentTheme, toggleTheme } = useTheme()

const isSettingsOpen = ref(false)

function handleGlobalClick(event: MouseEvent): void {
  const sidebar = document.querySelector('.sidebar')

  if (sidebar && sidebar.classList.contains('mobile-open') && !sidebar.contains(event.target as Node)) {
    store.sidebarMobileOpen = false
  }

  store.activeFileDropdownId = null
}
</script>
