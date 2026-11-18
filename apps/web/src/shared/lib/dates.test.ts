import { afterEach, describe, expect, it, vi } from 'vitest'
import { formatAgo, formatDate } from './dates'

const when = new Date(2026, 10, 18, 9, 5)

describe('Dates in the UI language', () => {
  afterEach(() => vi.restoreAllMocks())

  // Chrome ships without Armenian: Intl would answer in English.
  it('formats Armenian itself when the browser has no Armenian data', () => {
    vi.spyOn(Intl.DateTimeFormat, 'supportedLocalesOf').mockReturnValue([])

    expect(formatDate(when, 'hy', 'long')).toBe('18 նոյեմբերի, 2026 թ.')
    expect(formatDate(when, 'hy', 'medium')).toBe('18 նոյ, 2026 թ.')
    expect(formatDate(when, 'hy', 'mediumTime')).toBe('18 նոյ, 2026 թ., 09:05')
    const now = when.getTime()
    expect(formatAgo(new Date(now - 17 * 3600_000), 'hy', now)).toBe('17 ժ առաջ')
    expect(formatAgo(new Date(now - 5 * 60_000), 'hy', now)).toBe('5 ր առաջ')
    expect(formatAgo(new Date(now - 86400_000), 'hy', now)).toBe('երեկ')
    expect(formatAgo(new Date(now + 5000), 'hy', now)).toBe('հիմա')
  })

  it('uses Intl for languages the browser knows', () => {
    expect(formatDate(when, 'en', 'long')).toBe('November 18, 2026')
    expect(formatDate(when, 'ru', 'long')).toBe('18 ноября 2026 г.')
    expect(formatAgo(new Date(when.getTime() - 17 * 3600_000), 'en', when.getTime())).toBe(
      '17 hr. ago',
    )
  })
})
