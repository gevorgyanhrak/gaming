import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Skeleton } from '@/shared/ui/Skeleton'
import { fetchSlots, slotsKey } from './slots'

/**
 * The slot games the server offers (none until an admin has one switched on), as tiles that open the slot's page.
 * Played with Fishkas, play money only; the server deals every round.
 */
export function SlotShelf() {
  const { t } = useTranslation()
  const slots = useQuery({ queryKey: slotsKey, queryFn: ({ signal }) => fetchSlots(signal) })

  if (slots.isPending) {
    return (
      <div className="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="aspect-square rounded-card" />
        ))}
      </div>
    )
  }
  // Slots are extra: a failed list or none at all just leaves the shelf out.
  const playable = (slots.data ?? []).filter((slot) => slot.play_path !== null)
  if (playable.length === 0) return null

  return (
    <section aria-labelledby="slots-heading" className="mt-10">
      <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="slots-heading" className="font-display text-2xl font-bold text-fg sm:text-3xl">
          {t('slots.title')}
        </h2>
        <p className="text-sm text-muted">{t('slots.playMoney')}</p>
      </div>
      <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4 lg:grid-cols-6">
        {playable.map((slot, i) => (
          <li
            key={slot.slug}
            className="motion-safe:animate-rise"
            style={{ animationDelay: `${i * 60}ms` }}
          >
            <Link
              to={`/casino/slots/${slot.slug}`}
              className="group hover-shine relative block overflow-hidden rounded-card border border-gold/25 bg-black/40 shadow-[0_10px_24px_rgb(0_0_0/0.45)] transition hover:border-gold/70 focus-visible:outline-2 focus-visible:outline-accent-text motion-safe:hover:-translate-y-1"
            >
              {slot.image_url ? (
                <img
                  src={slot.image_url}
                  alt=""
                  loading="lazy"
                  className="aspect-square w-full object-cover transition duration-500 group-hover:brightness-110"
                />
              ) : (
                <span aria-hidden="true" className="block aspect-square w-full bg-black/50" />
              )}
              <span className="block truncate px-3 py-2 text-sm font-bold text-fg">
                {slot.name}
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  )
}
