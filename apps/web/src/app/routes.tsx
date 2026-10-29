import type { RouteObject } from 'react-router'
import { AuthRoute, GuestRoute } from '@/features/auth/guards'
import { GamePage } from '@/pages/GamePage'
import { GamesPage } from '@/pages/GamesPage'
import { LandingPage } from '@/pages/LandingPage'
import { NotFoundPage } from '@/pages/NotFoundPage'
import { RouteErrorPage } from '@/pages/RouteErrorPage'
import { PublicLayout } from './layouts/PublicLayout'

// Sign-in and account pages (with the form libraries they use) are loaded on demand;
// the landing page and catalog stay in the main bundle.
export const routes: RouteObject[] = [
  {
    element: <PublicLayout />,
    errorElement: <RouteErrorPage />,
    hydrateFallbackElement: null,
    children: [
      { index: true, element: <LandingPage /> },
      { path: 'games', element: <GamesPage /> },
      { path: 'games/:slug', element: <GamePage /> },
      {
        path: 'pages/:slug',
        lazy: () => import('@/pages/ContentPage').then((m) => ({ Component: m.ContentPage })),
      },
      {
        path: 'u/:username',
        lazy: () =>
          import('@/pages/PublicProfilePage').then((m) => ({ Component: m.PublicProfilePage })),
      },
      {
        element: <GuestRoute />,
        children: [
          {
            path: 'login',
            lazy: () => import('@/pages/auth/LoginPage').then((m) => ({ Component: m.LoginPage })),
          },
          {
            path: 'register',
            lazy: () =>
              import('@/pages/auth/RegisterPage').then((m) => ({ Component: m.RegisterPage })),
          },
          {
            path: 'forgot-password',
            lazy: () =>
              import('@/pages/auth/ForgotPasswordPage').then((m) => ({
                Component: m.ForgotPasswordPage,
              })),
          },
          {
            path: 'reset-password',
            lazy: () =>
              import('@/pages/auth/ResetPasswordPage').then((m) => ({
                Component: m.ResetPasswordPage,
              })),
          },
        ],
      },
      {
        element: <AuthRoute />,
        children: [
          {
            path: 'verify',
            lazy: () =>
              import('@/pages/auth/VerifyPage').then((m) => ({ Component: m.VerifyPage })),
          },
          {
            path: 'dashboard',
            lazy: () =>
              import('@/pages/DashboardPage').then((m) => ({ Component: m.DashboardPage })),
          },
          {
            path: 'profile',
            lazy: () => import('@/pages/ProfilePage').then((m) => ({ Component: m.ProfilePage })),
          },
          {
            path: 'join/:code',
            lazy: () => import('@/pages/JoinPage').then((m) => ({ Component: m.JoinPage })),
          },
          {
            path: 'rooms/:id',
            lazy: () => import('@/pages/RoomPage').then((m) => ({ Component: m.RoomPage })),
          },
          {
            path: 'history',
            lazy: () => import('@/pages/HistoryPage').then((m) => ({ Component: m.HistoryPage })),
          },
          {
            path: 'settings',
            lazy: () => import('@/pages/SettingsPage').then((m) => ({ Component: m.SettingsPage })),
          },
        ],
      },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
