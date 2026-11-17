/**
 * The lobby's game cards (games and slots alike, Midnight Gold): a night card with a thin light edge that turns
 * gold and glows as it lifts, the art zooming a touch, and a dark name plate under it.
 */
export const posterCard =
  'group hover-shine relative flex flex-1 flex-col overflow-hidden rounded-card border border-white/10 bg-night-800 shadow-raised transition-[transform,border-color,box-shadow] duration-200 ease-soft hover:border-gold/70 hover:shadow-[0_22px_44px_-14px_rgb(0_0_0/0.8),0_0_0_1px_rgb(245_192_79/0.4),0_0_32px_-6px_rgb(245_192_79/0.45)] motion-safe:hover:-translate-y-1.5'

/** The art's box: it zooms slightly on hover. */
export const posterArt =
  'transition-transform duration-500 ease-soft motion-safe:group-hover:scale-[1.06]'

/** The name plate: dark glass over the bottom of the card. */
export const posterPlate =
  'relative flex-1 border-t border-white/8 bg-linear-to-b from-night-700 to-night-800 px-3 pt-2.5 pb-3'
