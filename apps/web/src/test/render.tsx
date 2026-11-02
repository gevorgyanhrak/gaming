import { QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { createQueryClient } from '@/app/queryClient'
import { routes } from '@/app/routes'
import { closeOutOfFishkas } from '@/features/wallet/outOfFishkas'

/**
 * Renders the real route tree at `path`, with the app's query client (fresh, non-retrying unless
 * `retries` asks for the app's real retry policy).
 */
export function renderRoute(path: string, { retries = false }: { retries?: boolean } = {}) {
  const queryClient = createQueryClient()
  if (!retries) queryClient.setDefaultOptions({ queries: { retry: false } })
  closeOutOfFishkas()
  const router = createMemoryRouter(routes, { initialEntries: [path] })
  // Mount into #root like index.html, so code that addresses the app container (modals) works.
  document.getElementById('root')?.remove()
  const container = document.body.appendChild(document.createElement('div'))
  container.id = 'root'

  return {
    router,
    ...render(
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>,
      { container },
    ),
  }
}
