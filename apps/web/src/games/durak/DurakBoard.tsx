import { useTranslation } from 'react-i18next'
import { suitSymbols, useCardLabel, type Suit } from '../cards/cards'
import { PlayingCard as Card } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'

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

  const labels = useCardLabel()
  const cardLabel = labels.card
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
                {suitSymbols[view.trump]} {labels.suit(view.trump)}
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
