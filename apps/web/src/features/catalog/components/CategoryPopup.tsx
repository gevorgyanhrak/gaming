import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { SlotPoster } from '@/features/casino/SlotPoster'
import type { Slot } from '@/features/casino/slots'
import { CloseIcon } from '@/shared/ui/icons'
import { Modal } from '@/shared/ui/Modal'
import type { GameSummary } from '../queries'
import { GamePoster } from './GamePoster'

/**
 * A category's games in a popup over the home page (owner, 2026-11-16); the whole screen on phones. Its title
 * bar stays put while the posters scroll under it.
 */
export function CategoryPopup({
  name,
  games,
  slots,
  onClose,
}: {
  name: string
  games: GameSummary[]
  slots: Slot[]
  onClose: () => void
}) {
  const { t } = useTranslation()
  const titleId = useId()
  const count = games.length + slots.length

  return (
    <Modal
      onClose={onClose}
      labelledBy={titleId}
      size="xl"
      fullScreenOnPhone
      scrolls={false}
      className="flex flex-col"
    >
      <header className="flex shrink-0 items-center gap-3 border-b border-gold/20 px-4 py-3 sm:px-6 sm:py-4">
        <div className="min-w-0 flex-1">
          <h2
            id={titleId}
            className="truncate font-display text-xl leading-tight font-bold text-fg sm:text-2xl"
          >
            {name}
          </h2>
          <p className="text-xs font-semibold text-gold-light">
            {t('casino.gameCount', { count })}
          </p>
        </div>
        <button
          type="button"
          onClick={onClose}
          aria-label={t('lobby.close')}
          className="grid size-11 shrink-0 place-items-center rounded-full border border-gold/30 text-fg transition-colors hover:border-gold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-text"
        >
          <CloseIcon />
        </button>
      </header>
      <ul className="grid min-h-0 flex-1 grid-cols-2 content-start gap-3 overflow-y-auto overscroll-contain p-4 sm:grid-cols-3 sm:gap-4 sm:p-6 lg:grid-cols-4">
        {games.map((game, i) => (
          <li
            key={game.slug}
            className="flex motion-safe:animate-rise"
            style={{ animationDelay: `${Math.min(i, 8) * 40}ms` }}
          >
            <div className="flex w-full flex-col">
              <GamePoster game={game} />
            </div>
          </li>
        ))}
        {slots.map((slot, i) => (
          <li
            key={`slot-${slot.slug}`}
            className="flex motion-safe:animate-rise"
            style={{ animationDelay: `${Math.min(games.length + i, 8) * 40}ms` }}
          >
            <div className="flex w-full flex-col">
              <SlotPoster slot={slot} />
            </div>
          </li>
        ))}
      </ul>
    </Modal>
  )
}
