import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { posterArt, posterCard, posterPlate } from '@/features/catalog/components/posterStyles'
import { FavoriteButton } from '@/features/favorites/FavoriteButton'
import { symbolLook } from './engine/symbols'
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
          ) : (
            <EngineArt slot={slot} />
          )}
          <span className="absolute top-2.5 left-2.5 rounded-full bg-black/60 px-2.5 py-1 text-xs font-bold text-gold-light shadow-sm">
            {t('slots.badge')}
          </span>
        </div>
        <div className={posterPlate}>
          <h3 className="text-base leading-[1.375rem] font-bold text-fg">{slot.name}</h3>
          <p className="text-xs text-muted">{slot.studio ?? t('slots.tag')}</p>
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

function hash(text: string): number {
  let h = 2166136261
  for (const ch of text) {
    h ^= ch.codePointAt(0) ?? 0
    h = Math.imul(h, 16777619)
  }
  return h >>> 0
}

const SCENES = [
  ['#3b0764', '#0a0d19'],
  ['#7c2d12', '#120a05'],
  ['#064e3b', '#04130d'],
  ['#1e3a8a', '#070b1a'],
  ['#831843', '#14050d'],
  ['#713f12', '#140d04'],
  ['#155e75', '#041317'],
  ['#4c1d95', '#0d0619'],
] as const

/**
 * Art for a slot without a picture (engine slots bring none, ADR 0026): a colour scene and three of our symbols picked
 * from its name, its maker and its name in large letters. The same slot always looks the same.
 */
function EngineArt({ slot }: { slot: Slot }) {
  const h = hash(slot.slug)
  const [from, to] = SCENES[h % SCENES.length]!
  return (
    <div
      aria-hidden="true"
      className={`flex size-full flex-col justify-between p-3 ${posterArt}`}
      style={{ background: `radial-gradient(circle at 50% 20%, ${from}, ${to} 75%)` }}
    >
      <div className="mt-6 flex justify-center gap-1.5">
        {[0, 1, 2].map((i) => {
          const look = symbolLook(((h >>> (i * 5)) % 18) + 1)
          return (
            <span
              key={i}
              className="flex size-10 items-center justify-center rounded-lg border border-white/20 text-lg font-black shadow-lg sm:size-12 sm:text-xl"
              style={{
                background: `linear-gradient(160deg, ${look.from}, ${look.to})`,
                color: look.ink,
              }}
            >
              {look.glyph}
            </span>
          )
        })}
      </div>
      <p className="text-center font-display text-lg leading-tight font-bold text-balance text-white drop-shadow-[0_2px_4px_rgb(0_0_0/0.8)] sm:text-xl">
        {slot.name}
      </p>
    </div>
  )
}
