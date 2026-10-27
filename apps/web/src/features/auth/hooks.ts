import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { bundledLocales, type Locale } from '@/shared/i18n'
import { fetchCurrentUser, logout, type CurrentUser } from './api'

export const authKeys = {
  me: ['me'] as const,
}

export function useCurrentUser() {
  return useQuery({
    queryKey: authKeys.me,
    queryFn: ({ signal }) => fetchCurrentUser(signal),
    staleTime: 60_000,
  })
}

let redirectAfterSignIn: string | null = null

/** Where RequireGuest sends a user who just signed in, if the signing-in page chose a destination. */
export function getRedirectAfterSignIn(): string | null {
  return redirectAfterSignIn
}

/**
 * Stores the account returned by a mutation as the current user, and switches the UI to
 * the account's language when it has one.
 *
 * Pages under RequireGuest (login, registration) don't navigate themselves after signing in:
 * the guard redirects as soon as the user is set, to `redirectTo` or the `next` parameter.
 */
export function useSetCurrentUser() {
  const queryClient = useQueryClient()
  const { i18n } = useTranslation()

  return (user: CurrentUser, redirectTo: string | null = null) => {
    redirectAfterSignIn = redirectTo
    queryClient.setQueryData(authKeys.me, user)

    if (
      user.locale &&
      user.locale !== i18n.language &&
      bundledLocales.includes(user.locale as Locale)
    ) {
      void i18n.changeLanguage(user.locale)
    }
  }
}

export function useLogout() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: logout,
    // Even if the request fails (e.g. the session already expired), forget everything user-specific.
    // Not queryClient.clear(): it detaches queries that mounted components still observe.
    onSettled: () => {
      const notMe = (query: { queryKey: readonly unknown[] }) =>
        query.queryKey[0] !== authKeys.me[0]
      queryClient.setQueryData(authKeys.me, null)
      queryClient.removeQueries({ type: 'inactive', predicate: notMe })
      void queryClient.invalidateQueries({ predicate: notMe })
    },
  })
}
