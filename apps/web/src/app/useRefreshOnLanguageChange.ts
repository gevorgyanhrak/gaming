import { useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { translatedQuery } from '@/features/play/api'

/**
 * Refetches what the API translated (game and mode names in rooms, history, statistics, profiles)
 * when the player switches language. The cached data stays on screen meanwhile, so only the words
 * change; a room's live state is left alone.
 */
export function useRefreshOnLanguageChange() {
  const queryClient = useQueryClient()
  const { i18n } = useTranslation()

  useEffect(() => {
    let current = i18n.language
    const changed = (language: string) => {
      if (language === current) return
      current = language
      void queryClient.invalidateQueries({ predicate: (query) => translatedQuery(query.queryKey) })
    }
    i18n.on('languageChanged', changed)
    return () => i18n.off('languageChanged', changed)
  }, [i18n, queryClient])
}
