import { ref } from 'vue'
import { translations, localeOptions, type Locale, type TranslationMessages } from './translations'

const currentLocale = ref<Locale>(
  (localStorage.getItem('danilovl.log_viewer.locale') as Locale) || (localStorage.getItem('locale') as Locale) || 'en',
)

function t(key: keyof TranslationMessages, params?: Record<string, string>): string {
  const messages = translations[currentLocale.value] || translations['en']
  let text = messages[key] || translations['en'][key] || key

  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      text = text.replace(`{${k}}`, v)
    })
  }

  return text
}

function setLocale(locale: Locale): void {
  currentLocale.value = locale
  localStorage.setItem('danilovl.log_viewer.locale', locale)
}

export function useI18n() {
  return {
    t,
    currentLocale,
    setLocale,
    localeOptions,
  }
}
