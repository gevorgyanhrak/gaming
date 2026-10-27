import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { bundledLocales, type Locale } from '.'

/**
 * Lists the languages that are both bundled in the UI and served by the API.
 * Each option is labelled in its own language (e.g. "русский", "հայերեն").
 */
export function LanguageSwitcher({ serverLocales }: { serverLocales: readonly string[] }) {
  const { t, i18n } = useTranslation()
  const id = useId()
  const locales = bundledLocales.filter((locale) => serverLocales.includes(locale))

  if (locales.length < 2) return null

  return (
    <div className="flex items-center gap-2 text-sm">
      <label htmlFor={id} className="text-muted">
        {t('footer.language')}
      </label>
      <select
        id={id}
        value={i18n.language}
        onChange={(event) => void i18n.changeLanguage(event.target.value)}
        className="rounded-lg border border-border bg-surface px-2 py-1 text-fg"
      >
        {locales.map((locale) => (
          <option key={locale} value={locale}>
            {nativeName(locale)}
          </option>
        ))}
      </select>
    </div>
  )
}

function nativeName(locale: Locale): string {
  return new Intl.DisplayNames([locale], { type: 'language' }).of(locale) ?? locale
}
