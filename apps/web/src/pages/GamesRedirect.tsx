import { Navigate, useLocation } from 'react-router'

/** `/games` moved to `/dashboard` (HayBet brief item 2): old links and bookmarks keep their filters (?category=, ?q=). */
export function GamesRedirect() {
  const { search, hash } = useLocation()
  return <Navigate to={{ pathname: '/dashboard', search, hash }} replace />
}
