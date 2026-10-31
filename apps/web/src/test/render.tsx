import { QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { createQueryClient } from '@/app/queryClient'
import { routes } from '@/app/routes'
import { closeOutOfFishkas } from '@/features/wallet/outOfFishkas'

/** Renders the real route tree at `path`, with the app's query client (fresh, non-retrying). */
export function renderRoute(path: string) {
  const queryClient = createQueryClient()
  queryClient.setDefaultOptions({ queries: { retry: false } })
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
