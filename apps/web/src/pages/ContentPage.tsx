import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router'
import { useContentPage } from '@/features/content/queries'
import { isApiError } from '@/shared/api/errors'
import { splitParagraphs } from '@/shared/lib/paragraphs'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

/** Admin-managed pages such as the privacy policy and the terms of service. */
export function ContentPage() {
  const { t, i18n } = useTranslation()
  const { slug = '' } = useParams()
  const page = useContentPage(slug)

  return (
    <Container className="py-12">
      <div className="mx-auto max-w-3xl">
        {page.isPending ? (
          <div className="space-y-4" data-testid="page-loading">
            <Skeleton className="h-10 w-2/3" />
            <Skeleton className="h-64 w-full" />
          </div>
        ) : page.isError ? (
          isApiError(page.error) && page.error.isNotFound ? (
            <EmptyState title={t('errors.notFoundTitle')}>{t('errors.notFoundBody')}</EmptyState>
          ) : (
            <QueryErrorState error={page.error} onRetry={() => void page.refetch()} />
          )
        ) : (
          <article>
            <title>{page.data.title}</title>
            <h1 className="text-3xl font-bold">{page.data.title}</h1>
            {page.data.updated_at ? (
              <p className="mt-2 text-sm text-muted">
                {t('content.updated', {
                  date: new Intl.DateTimeFormat(i18n.language, { dateStyle: 'long' }).format(
                    new Date(page.data.updated_at),
                  ),
                })}
              </p>
            ) : null}
            <div className="mt-8 space-y-4 leading-relaxed text-fg/90">
              {splitParagraphs(page.data.body).map((paragraph, index) => (
                <p key={index} className="whitespace-pre-line">
                  {paragraph}
                </p>
              ))}
            </div>
          </article>
        )}
      </div>
    </Container>
  )
}
