import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { BoardProps } from '../registry'

type Piece = 'w' | 'W' | 'b' | 'B'

interface CheckersView {
  board: (Piece | null)[]
  turn: number
  capturing: number | null
  captured: number[]
  last: { from: number; to: number } | null
}

type Step = { type: 'move'; from: number; to: number }

const pieceKeys = {
  w: 'checkers.whiteMan',
  W: 'checkers.whiteKing',
  b: 'checkers.blackMan',
  B: 'checkers.blackKing',
} as const

/** "a1".."h8"; square 0 is a8 (white plays from the bottom rows). */
const squareName = (square: number) =>
  `${'abcdefgh'[square % 8] ?? ''}${8 - Math.floor(square / 8)}`

/**
 * Russian draughts board. Renders the server's view from the player's side (black is turned
 * around) and offers exactly the server's legal steps: pick a piece, then one of its targets.
 * During a capture series the capturing piece stays selected.
 */
export default function CheckersBoard({ state, onAction, pending }: BoardProps) {
  const { t } = useTranslation()
  const view = state.view as unknown as CheckersView
  const me = state.seat ?? 0
  const steps = state.legal_actions as unknown as Step[]
  const [picked, setPicked] = useState<number | null>(null)

  const selected = view.capturing ?? picked
  const sources = new Set(steps.map((s) => s.from))
  const targets = new Set(steps.filter((s) => s.from === selected).map((s) => s.to))
  const myTurn = state.seats_to_act.includes(me)
  // Seat 1 (black) sees its own pieces at the bottom.
  const order = Array.from({ length: 64 }, (_, i) => (me === 0 ? i : 63 - i))

  const click = (square: number) => {
    if (pending) return
    const step = steps.find((s) => s.from === selected && s.to === square)
    if (step) {
      setPicked(null)
      onAction(step)
      return
    }
    if (view.capturing === null) setPicked(sources.has(square) && picked !== square ? square : null)
  }

  const status = !myTurn
    ? t('checkers.waiting')
    : view.capturing !== null
      ? t('checkers.continueCapture')
      : selected === null
        ? t('checkers.pickPiece')
        : t('checkers.pickTarget')

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p className="font-medium">{me === 0 ? t('checkers.youWhite') : t('checkers.youBlack')}</p>
        <p className="text-muted" aria-live="polite">
          {status}
        </p>
      </div>

      <div className="mx-auto grid aspect-square w-full max-w-[32rem] grid-cols-8 overflow-hidden rounded-2xl border border-border">
        {order.map((square) => {
          const dark = (Math.floor(square / 8) + (square % 8)) % 2 === 1
          const piece = view.board[square] ?? null
          const isSource = sources.has(square)
          const isTarget = targets.has(square)
          const isLast =
            view.last !== null && (view.last.from === square || view.last.to === square)
          const jumped = view.captured.includes(square)

          if (!dark) return <div key={square} aria-hidden="true" className="bg-border/40" />

          return (
            <button
              key={square}
              type="button"
              onClick={() => click(square)}
              disabled={pending || (!isTarget && (!isSource || view.capturing !== null))}
              aria-pressed={selected === square}
              aria-label={`${squareName(square)}: ${piece ? t(pieceKeys[piece]) : t('checkers.empty')}`}
              className={`relative flex items-center justify-center bg-surface-raised disabled:cursor-default ${
                isLast ? 'bg-accent/15' : ''
              } ${isTarget ? 'bg-accent/30' : ''} ${selected === square ? 'ring-2 ring-accent-text ring-inset' : ''}`}
            >
              {piece ? (
                <span
                  aria-hidden="true"
                  className={`flex size-4/5 items-center justify-center rounded-full border-2 text-xs font-bold sm:text-base ${
                    piece === 'w' || piece === 'W'
                      ? 'border-black/30 bg-white text-black'
                      : 'border-white/30 bg-neutral-900 text-white'
                  } ${jumped ? 'opacity-40' : ''} ${isSource && myTurn ? 'ring-2 ring-warning' : ''}`}
                >
                  {piece === 'W' || piece === 'B' ? '♛' : null}
                </span>
              ) : isTarget ? (
                <span aria-hidden="true" className="size-1/4 rounded-full bg-accent" />
              ) : null}
            </button>
          )
        })}
      </div>
    </div>
  )
}
