import type { RouteObject } from 'react-router'
import { GamePage } from '@/pages/GamePage'
import { GamesPage } from '@/pages/GamesPage'
import { LandingPage } from '@/pages/LandingPage'
import { NotFoundPage } from '@/pages/NotFoundPage'
import { RouteErrorPage } from '@/pages/RouteErrorPage'
import { PublicLayout } from './layouts/PublicLayout'

export const routes: RouteObject[] = [
  {
    element: <PublicLayout />,
    errorElement: <RouteErrorPage />,
    children: [
      { index: true, element: <LandingPage /> },
      { path: 'games', element: <GamesPage /> },
      { path: 'games/:slug', element: <GamePage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
