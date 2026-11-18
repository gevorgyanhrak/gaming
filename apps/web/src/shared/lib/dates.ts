/**
 * Dates and "time ago" in the UI language. `Intl` does it where the browser has the language's data; Chrome ships
 * without Armenian, so there `Intl` silently answers in English ("November 18, 2026"). For such a language we
 * format from the CLDR patterns below instead.
 */

export type DateStyle = 'long' | 'medium' | 'mediumTime'

type Fallback = {
  /** Month names as used after a day ("18 նոյեմբերի"), January first. */
  months: readonly string[]
  monthsShort: readonly string[]
  date: (day: number, month: string, year: number) => string
  /** "17 h ago" for a unit and a count of whole units in the past (0 = now). */
  ago: (count: number, unit: Unit) => string
}

type Unit = 'second' | 'minute' | 'hour' | 'day'

// CLDR (hy): dateStyle long "18 նոյեմբերի, 2026 թ.", medium "18 նոյ, 2026 թ.", relative short "17 ժ առաջ".
const HY_AGO: Record<Unit, [now: string, unit: string]> = {
  second: ['հիմա', 'վրկ'],
  minute: ['այս րոպեին', 'ր'],
  hour: ['այս ժամին', 'ժ'],
  day: ['այսօր', 'օր'],
}

const FALLBACKS: Record<string, Fallback> = {
  hy: {
    months: [
      'հունվարի',
      'փետրվարի',
      'մարտի',
      'ապրիլի',
      'մայիսի',
      'հունիսի',
      'հուլիսի',
      'օգոստոսի',
      'սեպտեմբերի',
      'հոկտեմբերի',
      'նոյեմբերի',
      'դեկտեմբերի',
    ],
    monthsShort: [
      'հնվ',
      'փտվ',
      'մրտ',
      'ապր',
      'մյս',
      'հնս',
      'հլս',
      'օգս',
      'սեպ',
      'հոկ',
      'նոյ',
      'դեկ',
    ],
    date: (day, month, year) => `${day} ${month}, ${year} թ.`,
    ago: (count, unit) => {
      if (count === 0) return HY_AGO[unit][0]
      if (unit === 'day' && count === 1) return 'երեկ'
      return `${count} ${HY_AGO[unit][1]} առաջ`
    },
  },
}

/** The fallback for a language `Intl` can't format in this browser, if we have one (the check is cheap: no cache). */
function fallbackFor(lang: string): Fallback | null {
  const fallback = FALLBACKS[lang]
  if (!fallback) return null
  return Intl.DateTimeFormat.supportedLocalesOf([lang]).length > 0 ? null : fallback
}

const OPTIONS: Record<DateStyle, Intl.DateTimeFormatOptions> = {
  long: { dateStyle: 'long' },
  medium: { dateStyle: 'medium' },
  mediumTime: { dateStyle: 'medium', timeStyle: 'short' },
}

/** A date (and for `mediumTime` the time) in `lang`, in the visitor's time zone. */
export function formatDate(value: string | Date, lang: string, style: DateStyle = 'long'): string {
  const date = typeof value === 'string' ? new Date(value) : value
  const fallback = fallbackFor(lang)
  if (!fallback) return new Intl.DateTimeFormat(lang, OPTIONS[style]).format(date)

  const month = (style === 'long' ? fallback.months : fallback.monthsShort)[date.getMonth()] ?? ''
  const day = fallback.date(date.getDate(), month, date.getFullYear())
  if (style !== 'mediumTime') return day
  const time = `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
  return `${day}, ${time}`
}

/** How long ago, short ("17 hr. ago", "17 ч назад", "17 ժ առաջ"); a time in the future counts as now. */
export function formatAgo(value: string | Date, lang: string, now: number = Date.now()): string {
  const time = typeof value === 'string' ? new Date(value).getTime() : value.getTime()
  const seconds = Math.min(0, Math.round((time - now) / 1000))
  const abs = Math.abs(seconds)
  const [count, unit]: [number, Unit] =
    abs < 60
      ? [abs, 'second']
      : abs < 3600
        ? [Math.round(abs / 60), 'minute']
        : abs < 86400
          ? [Math.round(abs / 3600), 'hour']
          : [Math.round(abs / 86400), 'day']

  const fallback = fallbackFor(lang)
  if (fallback) return fallback.ago(count, unit)
  return new Intl.RelativeTimeFormat(lang, { numeric: 'auto', style: 'short' }).format(-count, unit)
}
