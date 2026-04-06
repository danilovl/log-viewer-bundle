import { ref, onMounted, onUnmounted } from 'vue'

const MIN_WIDTH = 200
const MAX_WIDTH = 600
const DEFAULT_WIDTH = 320

const sidebarWidth = ref<number>(
  parseInt(
    localStorage.getItem('danilovl.log_viewer.sidebarWidth') || localStorage.getItem('sidebarWidth') || '',
    10,
  ) || DEFAULT_WIDTH,
)

let isResizing = false

function applySidebarWidth(width: number): void {
  document.documentElement.style.setProperty('--sidebar-width', width + 'px')
}

function onMouseMove(event: MouseEvent): void {
  if (!isResizing) {
    return
  }

  const newWidth = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, event.clientX))
  sidebarWidth.value = newWidth
  applySidebarWidth(newWidth)
}

function onMouseUp(): void {
  if (!isResizing) {
    return
  }

  isResizing = false
  document.body.style.cursor = ''
  document.body.style.userSelect = ''
  localStorage.setItem('danilovl.log_viewer.sidebarWidth', String(sidebarWidth.value))
}

function startResize(): void {
  isResizing = true
  document.body.style.cursor = 'col-resize'
  document.body.style.userSelect = 'none'
}

export function useSidebarResize() {
  onMounted(() => {
    applySidebarWidth(sidebarWidth.value)
    document.addEventListener('mousemove', onMouseMove)
    document.addEventListener('mouseup', onMouseUp)
  })

  onUnmounted(() => {
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
  })

  return {
    sidebarWidth,
    startResize,
  }
}
