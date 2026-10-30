import { CardBack } from '../cards/PlayingCard'

/** A player's part in the bout, shown as a speech bubble: text, background and text colour. */
export interface Role {
  text: string
  bg: string
  fg: string
}

interface SeatProps {
  seat: number
  name: string
  count: number
  role: Role | null
  acting: boolean
  /** Share of the turn left, for the timer ring of the acting player. */
  share: number | null
  /** "Bot" when a bot plays this seat. */
  bot: string | null
  label: string
}

/** The role bubble; its tail points down at the avatar. */
export function Bubble({ role, small = false }: { role: Role; small?: boolean }) {
  return (
    <span
      className={`relative rounded-lg font-semibold shadow ${role.bg} ${role.fg} ${
        small
          ? 'line-clamp-2 max-w-[calc(100%+0.5rem)] shrink-0 px-[3px] py-px text-[0.6rem] leading-tight break-words'
          : 'inline-block px-2.5 py-0.5 text-xs'
      }`}
    >
      {role.text}
      <span
        aria-hidden="true"
        className={`absolute -bottom-1 left-1/2 size-2 -translate-x-1/2 rotate-45 ${role.bg}`}
      />
    </span>
  )
}

/**
 * Phones and tablets: a narrow column per player in one row across the top of the table, with the
 * bubble, avatar, name and a few card backs with the count.
 */
export function CompactSeat({ seat, name, count, role, acting, share, bot, label }: SeatProps) {
  return (
    <li
      data-seat={seat}
      role="group"
      aria-label={label}
      aria-current={acting ? 'step' : undefined}
      className={`flex min-w-0 flex-col items-center gap-0.5 rounded-xl px-1 pt-1 pb-1.5 text-center ${
        acting ? 'bg-black/30 ring-2 ring-gold' : ''
      }`}
    >
      <span className="flex min-h-5 w-full items-end justify-center">
        {role ? <Bubble role={role} small /> : null}
      </span>
      <span className="relative mt-1">
        <Avatar name={name} acting={acting} share={share} size="sm" />
        {bot ? (
          <span className="absolute -bottom-1.5 left-1/2 -translate-x-1/2 rounded-full bg-black/75 px-1 text-[0.55rem] leading-tight font-semibold tracking-wide text-white/85 uppercase">
            {bot}
          </span>
        ) : null}
      </span>
      <span className="mt-1 max-w-full truncate text-[0.72rem] font-medium text-white/85">
        {name}
      </span>
      <span className="flex items-center gap-1">
        <span aria-hidden="true" className="flex">
          {Array.from({ length: Math.min(count, 6) }, (_, i) => (
            <span
              key={i}
              className={`card-back block h-3.5 w-2.5 rounded-[2px] ${i ? '-ml-1.5' : ''}`}
            />
          ))}
        </span>
        <span className="text-[0.72rem] font-bold tabular-nums">{count}</span>
      </span>
    </li>
  )
}

/** Desktop: a panel with the avatar on its corner, the name, a fan of card backs and the count. */
export function PanelSeat({ seat, name, count, role, acting, share, bot, label }: SeatProps) {
  return (
    <div
      data-seat={seat}
      role="group"
      aria-label={label}
      aria-current={acting ? 'step' : undefined}
      className={`relative w-full max-w-56 rounded-2xl border px-3 pt-6 pb-3 transition-colors ${
        acting
          ? 'border-gold bg-black/45 shadow-[0_0_0_3px_rgb(214_171_78/0.25)]'
          : 'border-gold/20 bg-black/35'
      }`}
    >
      <span className="absolute -top-6 -left-3">
        <Avatar name={name} acting={acting} share={share} size="md" />
      </span>
      {role ? (
        <span className="absolute -top-8 left-11">
          <Bubble role={role} />
        </span>
      ) : null}
      <span className="absolute top-1.5 right-2 left-11 flex items-center gap-1.5 text-sm text-white/80">
        <span className="truncate">{name}</span>
        {bot ? (
          <span className="shrink-0 rounded-full bg-black/60 px-1.5 py-px text-[0.65rem] font-semibold tracking-wide text-white/85 uppercase">
            {bot}
          </span>
        ) : null}
      </span>
      <span className="mt-2 flex items-center gap-2">
        <span className="flex min-h-16" aria-hidden="true">
          {Array.from({ length: Math.min(count, 8) }, (_, i) => (
            <CardBack key={i} size="sm" className={i ? '-ml-8' : ''} />
          ))}
        </span>
        <span className="ml-auto rounded-full bg-black/45 px-2 py-0.5 text-xs font-bold tabular-nums">
          {count}
        </span>
      </span>
    </div>
  )
}

/** A round avatar with the player's initial; the acting player's ring shows the time left. */
export function Avatar({
  name,
  acting,
  share,
  size,
}: {
  name: string
  acting: boolean
  share: number | null
  size: 'sm' | 'md'
}) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : 'rgb(255 255 255 / 0.25)'
  return (
    <span
      className="block rounded-full p-[3px] shadow-md"
      style={{ background: ring }}
      aria-hidden="true"
    >
      <span
        className={`flex items-center justify-center rounded-full bg-[#1d2a24] font-bold uppercase ${
          size === 'md' ? 'size-12 text-xl' : 'size-9 text-base'
        }`}
      >
        {name.slice(0, 1)}
      </span>
    </span>
  )
}
