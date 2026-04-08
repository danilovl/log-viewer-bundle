import { createRouter, createWebHistory } from 'vue-router'
const DashboardView = () => {
  return import('@/views/DashboardView.vue')
}
const LogsView = () => {
  return import('@/views/LogsView.vue')
}
const LiveView = () => {
  return import('@/views/LiveView.vue')
}
const GlobalSearchView = () => {
  return import('@/views/GlobalSearchView.vue')
}
const BookmarksView = () => {
  return import('@/views/BookmarksView.vue')
}
const FileReaderView = () => {
  return import('@/views/FileReaderView.vue')
}

const router = createRouter({
  history: createWebHistory('/danilovl/log-viewer'),
  routes: [
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
    },
    {
      path: '/live',
      name: 'live',
      component: LiveView,
    },
    {
      path: '/global-search',
      name: 'global-search',
      component: GlobalSearchView,
    },
    {
      path: '/bookmarks',
      name: 'bookmarks',
      component: BookmarksView,
    },
    {
      path: '/logs/:sourceId',
      name: 'logs',
      component: LogsView,
      props: true,
    },
    {
      path: '/file-reader/:sourceId',
      name: 'file-reader',
      component: FileReaderView,
      props: true,
    },
    {
      path: '/',
      redirect: '/dashboard',
    },
  ],
})

export default router
