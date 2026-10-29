import { useTranslation } from 'react-i18next'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { isApiError } from '@/shared/api/errors'

/**
 * Puts API validation errors on the matching form fields.
 *
 * Returns a message for the whole form (a translation key or a translated API message)
 * when the error is not about specific known fields, otherwise null.
 */
export function applyServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: readonly Path<T>[],
): string | null {
  if (!isApiError(error)) return 'errors.generic'
  if (error.isNetworkError) return 'errors.network'
  if (error.status >= 500) return 'errors.generic'

  let unmatched = false

  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    const message = messages[0]
    if (message && (fields as readonly string[]).includes(field)) {
      setError(field as Path<T>, { type: 'server', message })
    } else {
      unmatched = true
    }
  }

  return Object.keys(error.fieldErrors).length === 0 || unmatched ? error.message : null
}

/** Translates a message if it is a translation key; returns API messages unchanged. */
export function useMessage() {
  const { t, i18n } = useTranslation()

  return (message: string) => (i18n.exists(message) ? t(message as never) : message)
}

/** Only same-app paths are allowed as post-login redirects (no open redirects). */
/** `path` with the page to continue to afterwards, if there is one. */
export function withNext(path: string, next: string | null | undefined): string {
  if (!next) return path
  return `${path}${path.includes('?') ? '&' : '?'}next=${encodeURIComponent(next)}`
}

export function safeRedirect(target: string | null | undefined, fallback = '/dashboard'): string {
  return target && target.startsWith('/') && !target.startsWith('//') && !target.startsWith('/\\')
    ? target
    : fallback
}
