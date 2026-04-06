import { ref, watchEffect } from 'vue'

type Theme = 'light' | 'dark'

const currentTheme = ref<Theme>(
  (localStorage.getItem('danilovl.log_viewer.theme') as Theme) || (localStorage.getItem('theme') as Theme) || 'light',
)

watchEffect(() => {
  document.documentElement.classList.toggle('dark', currentTheme.value === 'dark')
  localStorage.setItem('danilovl.log_viewer.theme', currentTheme.value)
})

function toggleTheme(): void {
  currentTheme.value = currentTheme.value === 'light' ? 'dark' : 'light'
}

export function useTheme() {
  return {
    currentTheme,
    toggleTheme,
  }
}
