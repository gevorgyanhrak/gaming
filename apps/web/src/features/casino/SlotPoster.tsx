import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { FavoriteButton } from '@/features/favorites/FavoriteButton'
import type { Slot } from './slots'

/**
 * A slot's card in the lobby, the same shape as a game poster: its tile art and an ivory plate. Opens the slot's
 * page (the server deals every round; played with Fishkas, play money only).
 */
export function SlotPoster({ slot }: { slot: Slot }) {
  const { t } = useTranslation()

  return (
    <div className="group hover-shine relative flex flex-1 flex-col overflow-hidden rounded-card border border-gold/30 bg-emerald-800 shadow-raised transition-[transform,border-color,box-shadow] duration-150 ease-soft hover:border-gold hover:shadow-[var(--shadow-raised),0_0_0_1px_var(--color-gold)] motion-safe:hover:-translate-y-1.5">
      <Link
        to={`/casino/slots/${slot.slug}`}
        className="flex flex-1 flex-col focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent-text"
      >
        <div className="relative aspect-[3/3.4] overflow-hidden bg-black">
          {slot.image_url ? (
            <img
              src={slot.image_url}
              alt=""
              loading="lazy"
              className="size-full object-cover transition duration-500 group-hover:brightness-110"
            />
          ) : null}
          <span className="absolute top-2.5 left-2.5 rounded-full bg-black/60 px-2.5 py-1 text-xs font-bold text-gold-light shadow-sm">
            {t('slots.badge')}
          </span>
        </div>
        <div className="surface-ivory flex-1 rounded-none px-3 pt-2.5 pb-3 shadow-none">
          <h3 className="text-base leading-[1.375rem] font-bold text-ink">{slot.name}</h3>
          <p className="text-xs text-ink-soft">{t('slots.tag')}</p>
        </div>
      </Link>
      <FavoriteButton
        kind="slot"
        slug={slot.slug}
        name={slot.name}
        className="absolute top-2 right-2"
      />
    </div>
  )
}
