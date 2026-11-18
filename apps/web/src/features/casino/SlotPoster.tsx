import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { posterArt, posterCard, posterPlate } from '@/features/catalog/components/posterStyles'
import { FavoriteButton } from '@/features/favorites/FavoriteButton'
import type { Slot } from './slots'

/**
 * A slot's card in the lobby, the same shape as a game poster: its tile art and an ivory plate. Opens the slot's
 * page (the server deals every round; played with Fishkas, play money only).
 */
export function SlotPoster({ slot }: { slot: Slot }) {
  const { t } = useTranslation()

  return (
    <div className={posterCard}>
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
              className={`size-full object-cover ${posterArt}`}
            />
          ) : null}
          <span className="absolute top-2.5 left-2.5 rounded-full bg-black/60 px-2.5 py-1 text-xs font-bold text-gold-light shadow-sm">
            {t('slots.badge')}
          </span>
        </div>
        <div className={posterPlate}>
          <h3 className="text-base leading-[1.375rem] font-bold text-fg">{slot.name}</h3>
          <p className="text-xs text-muted">{t('slots.tag')}</p>
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
