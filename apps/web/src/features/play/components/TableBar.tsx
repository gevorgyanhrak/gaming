import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { FishkaBalance } from '@/features/wallet/components/FishkaBalance'
import { BackIcon } from '@/shared/ui/icons'
import { Skeleton } from '@/shared/ui/Skeleton'
import type { Room } from '../api'
import { GroupBadge } from './GroupBadge'

/**
 * The one bar at a table (TASK-048), in place of the site header: the way back, the game and its mode,
 * the group with what the table plays for, whose turn it is (wide screens), and your Fishkas (the chips
 * that fly to the balance aim at it). Without a room yet (loading, an error): the way back and the balance.
 */
export function TableBar({ room, status = null }: { room?: Room; status?: ReactNode }) {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()

  return (
    <header className="sticky top-0 z-20 flex h-header items-center gap-3 border-b border-gold/20 bg-emerald-900 px-3 sm:px-6">
      <Link
        to={user ? '/dashboard' : '/'}
        className="flex size-11 shrink-0 items-center justify-center rounded-control border border-border-strong/50 bg-emerald-800 text-fg transition-colors hover:border-gold-light"
        aria-label={t('nav.back')}
      >
        <BackIcon />
      </Link>
      {room ? (
        <div className="flex min-w-0 flex-1 items-center gap-2.5">
          <span className="hidden size-9 shrink-0 items-center justify-center rounded-control bg-gold/15 min-[420px]:flex">
            <GameIcon slug={room.game.slug} name={room.game.name} className="size-5 text-gold" />
          </span>
          <div className="min-w-0">
            <h1 className="truncate text-base leading-tight font-bold sm:text-lg">
              {room.game.name}
            </h1>
            <p className="truncate text-xs text-muted">{room.mode.name}</p>
          </div>
          <span className="ml-1 hidden min-w-0 md:inline-flex">
            <GroupBadge room={room} />
          </span>
        </div>
      ) : (
        <div className="flex-1">
          <Skeleton className="h-5 w-32" />
        </div>
      )}
      {status}
      {user ? <FishkaBalance key={user.id} /> : null}
    </header>
  )
}
