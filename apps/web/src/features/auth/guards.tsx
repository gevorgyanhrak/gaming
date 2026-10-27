import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation, useSearchParams } from 'react-router'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { QueryErrorState } from '@/shared/ui/States'
import { safeRedirect } from './forms'
import { getRedirectAfterSignIn, useCurrentUser } from './hooks'

function PageLoading() {
  return (
    <div data-testid="page-loading">
      <Container className="space-y-4 py-12">
        <Skeleton className="h-9 w-1/3" />
        <Skeleton className="h-40 w-full" />
      </Container>
    </div>
  )
}

/** Renders children for signed-in users; sends guests to the login page and back afterwards. */
export function RequireAuth({ children }: { children: ReactNode }) {
  const location = useLocation()
  const user = useCurrentUser()

  if (user.isPending) return <PageLoading />
  if (user.isError) {
    return (
      <Container className="py-12">
        <QueryErrorState error={user.error} onRetry={() => void user.refetch()} />
      </Container>
    )
  }
  if (!user.data) {
    const next = encodeURIComponent(location.pathname + location.search)
    return <Navigate to={`/login?next=${next}`} replace />
  }

  return children
}

/**
 * Renders children for guests only (login, registration). Signed-in users, including ones who
 * just signed in on this page, continue to the page's chosen destination, the validated `next`
 * parameter, or the dashboard.
 */
export function RequireGuest({ children }: { children: ReactNode }) {
  const user = useCurrentUser()
  const [params] = useSearchParams()

  if (user.isPending) return <PageLoading />
  if (user.data) {
    return <Navigate to={getRedirectAfterSignIn() ?? safeRedirect(params.get('next'))} replace />
  }

  return children
}

/** Layout route: child routes are for signed-in users only. */
export function AuthRoute() {
  return (
    <RequireAuth>
      <Outlet />
    </RequireAuth>
  )
}

/** Layout route: child routes are for guests only. */
export function GuestRoute() {
  return (
    <RequireGuest>
      <Outlet />
    </RequireGuest>
  )
}
