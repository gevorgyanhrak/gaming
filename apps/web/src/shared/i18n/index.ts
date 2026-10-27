import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import en from './locales/en.json'
import hy from './locales/hy.json'
import ru from './locales/ru.json'

/** Languages the UI ships translations for. The API announces which ones it serves. */
export const bundledLocales = ['en', 'ru', 'hy'] as const
export type Locale = (typeof bundledLocales)[number]

const STORAGE_KEY = 'locale'
const FALLBACK: Locale = 'en'

function isLocale(value: string | null | undefined): value is Locale {
  return bundledLocales.includes(value as Locale)
}

function detectLocale(): Locale {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (isLocale(stored)) return stored
  } catch {
    // Storage can be unavailable (private mode, blocked cookies); fall through to the browser language.
  }

  const preferred = navigator.languages.map((tag) => tag.split('-')[0]).find(isLocale)
  return preferred ?? FALLBACK
}

void i18n.use(initReactI18next).init({
  resources: {
    en: { translation: en },
    ru: { translation: ru },
    hy: { translation: hy },
  },
  lng: detectLocale(),
  fallbackLng: FALLBACK,
  supportedLngs: bundledLocales,
  interpolation: { escapeValue: false },
})

document.documentElement.lang = i18n.language

i18n.on('languageChanged', (language) => {
  document.documentElement.lang = language
  try {
    localStorage.setItem(STORAGE_KEY, language)
  } catch {
    // Not persisting the choice is acceptable.
  }
})

export default i18n
