import { useTranslation } from 'react-i18next'
import type { BoardProps } from '../registry'

type Suit = 'S' | 'H' | 'D' | 'C'

interface DurakView {
  hand: string[]
  hand_counts: number[]
  deck_count: number
  trump: Suit
  trump_card: string | null
  table: { attack: string; defense: string | null }[]
  attacker: number
  defender: number
  taking: boolean
}

type DurakAction = { type: 'attack' | 'defend'; card: string } | { type: 'take' | 'done' }

const suitSymbols: Record<Suit, string> = { S: '♠', H: '♥', D: '♦', C: '♣' }
const suitKeys = {
  S: 'durak.suit.spades',
  H: 'durak.suit.hearts',
  D: 'durak.suit.diamonds',
  C: 'durak.suit.clubs',
} as const
const rankKeys = {
  J: 'durak.rank.jack',
  Q: 'durak.rank.queen',
  K: 'durak.rank.king',
  A: 'durak.rank.ace',
} as const

const rankText = (card: string) => (card[0] === 'T' ? '10' : (card[0] ?? ''))
const suitOf = (card: string) => (card[1] ?? 'S') as Suit

function Card({ card, label, small = false }: { card: string; label: string; small?: boolean }) {
  const red = suitOf(card) === 'H' || suitOf(card) === 'D'
  return (
    <span
      role="img"
      aria-label={label}
      className={`flex flex-col items-center justify-center rounded-lg border border-black/20 bg-white font-bold shadow ${
        small ? 'h-16 w-11 text-base' : 'h-24 w-16 text-xl'
      } ${red ? 'text-red-600' : 'text-neutral-900'}`}
    >
      <span>{rankText(card)}</span>
      <span aria-hidden="true">{suitSymbols[suitOf(card)]}</span>
    </span>
  )
}

/**
 * Durak table: the opponent's card count, deck and trump, the bout on the table and your hand.
 * Offers exactly the server's legal actions: cards to lead, throw in or beat with, "take" and "done".
 */
export default function DurakBoard({ state, onAction, pending }: BoardProps) {
  const { t } = useTranslation()
  const view = state.view as unknown as DurakView
  const me = state.seat ?? 0
  const actions = state.legal_actions as unknown as DurakAction[]
  const myTurn = state.seats_to_act.includes(me)

  const cardLabel = (card: string) => {
    const rank = card[0] ?? ''
    const rankName = rank in rankKeys ? t(rankKeys[rank as keyof typeof rankKeys]) : rankText(card)
    return t('durak.card', { rank: rankName, suit: t(suitKeys[suitOf(card)]) })
  }
  const actionFor = (card: string) => actions.find((a) => 'card' in a && a.card === card)
  const canTake = actions.some((a) => a.type === 'take')
  const canFinish = actions.some((a) => a.type === 'done')

  const status = !myTurn
    ? view.taking && view.defender === me
      ? t('durak.youTake')
      : t('durak.waiting')
    : view.defender === me
      ? t('durak.defend')
      : view.table.length === 0
        ? t('durak.lead')
        : view.taking
          ? t('durak.throwInBeforeTake')
          : t('durak.throwIn')

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div
          className="flex items-center gap-2"
          aria-label={t('durak.opponentCards', { count: view.hand_counts[1 - me] ?? 0 })}
        >
          {Array.from({ length: Math.min(view.hand_counts[1 - me] ?? 0, 12) }, (_, i) => (
            <span
              key={i}
              aria-hidden="true"
              className="-ml-6 h-16 w-11 rounded-lg border border-white/20 bg-accent/60 first:ml-0"
            />
          ))}
          <span className="ml-2 text-sm text-muted">{view.hand_counts[1 - me] ?? 0}</span>
        </div>
        <div className="flex items-center gap-3">
          {view.trump_card ? (
            <Card
              card={view.trump_card}
              label={t('durak.trumpCard', { card: cardLabel(view.trump_card) })}
              small
            />
          ) : null}
          <div className="text-sm">
            <p>{t('durak.deck', { count: view.deck_count })}</p>
            <p className="text-muted">
              {t('durak.trump')}:{' '}
              <span className={view.trump === 'H' || view.trump === 'D' ? 'text-red-500' : ''}>
                {suitSymbols[view.trump]} {t(suitKeys[view.trump])}
              </span>
            </p>
          </div>
        </div>
      </div>

      <section
        aria-label={t('durak.table')}
        className="flex min-h-36 flex-wrap items-center justify-center gap-4 rounded-2xl border border-border bg-[#1f5c3d]/60 p-4"
      >
        {view.table.length === 0 ? (
          <p className="text-sm text-white/70">{t('durak.emptyTable')}</p>
        ) : (
          view.table.map((pair, i) => (
            <div key={i} className="relative h-28 w-20">
              <Card card={pair.attack} label={cardLabel(pair.attack)} />
              {pair.defense ? (
                <span className="absolute top-4 left-4 rotate-12">
                  <Card
                    card={pair.defense}
                    label={t('durak.beatenBy', { card: cardLabel(pair.defense) })}
                  />
                </span>
              ) : null}
            </div>
          ))
        )}
      </section>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted" aria-live="polite">
          {status}
        </p>
        <div className="flex gap-2">
          {canTake ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => onAction({ type: 'take' })}
              className="rounded-lg border border-border bg-surface-raised px-4 py-2 text-sm font-semibold hover:border-accent-text"
            >
              {t('durak.take')}
            </button>
          ) : null}
          {canFinish ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => onAction({ type: 'done' })}
              className="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-white hover:bg-accent-hover"
            >
              {view.taking ? t('durak.letTake') : t('durak.done')}
            </button>
          ) : null}
        </div>
      </div>

      <ul aria-label={t('durak.yourHand')} className="flex flex-wrap justify-center gap-2">
        {view.hand.map((card) => {
          const action = actionFor(card)
          return (
            <li key={card}>
              <button
                type="button"
                disabled={pending || !action}
                onClick={() => action && onAction(action)}
                aria-label={cardLabel(card)}
                className={`rounded-lg transition ${action ? '-translate-y-0 ring-2 ring-accent-text hover:-translate-y-2' : 'opacity-60'} disabled:cursor-default`}
              >
                <Card card={card} label={cardLabel(card)} />
              </button>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
