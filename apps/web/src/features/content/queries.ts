import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'

export function useContentPage(slug: string) {
  const { i18n } = useTranslation()

  return useQuery({
    queryKey: ['pages', i18n.language, slug],
    queryFn: ({ signal }) =>
      apiRequest<ApiResponse<'v1.pages.show'>>(`/api/v1/pages/${encodeURIComponent(slug)}`, {
        signal,
      }).then((r) => r.data),
  })
}
