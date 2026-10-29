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

  const coord = (square: number, index: number) => ({
    // Files along the bottom row and ranks down the left edge, as seen from this side.
    file: index >= 56 ? 'abcdefgh'[square % 8] : null,
    rank: index % 8 === 0 ? 8 - Math.floor(square / 8) : null,
  })

  return (
    <div className="mx-auto max-w-[max(20rem,min(38rem,calc(100dvh-24rem)))] space-y-3">
      {state.seats_to_act.length > 0 ? (
        <p className="text-center text-2xl font-semibold sm:text-3xl">
          {view.turn === 0 ? t('checkers.moveWhite') : t('checkers.moveBlack')}
        </p>
      ) : null}
      <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
        <p className="font-medium">{me === 0 ? t('checkers.youWhite') : t('checkers.youBlack')}</p>
        <p className="text-muted" aria-live="polite">
          {status}
        </p>
      </div>

      <div className="table-rim rounded-xl p-2 sm:p-3">
        <div className="grid aspect-square w-full grid-cols-8 grid-rows-8 overflow-hidden rounded-sm text-[clamp(0.55rem,1.6vw,0.75rem)] shadow-[inset_0_0_0_1px_rgb(0_0_0/0.35)]">
          {order.map((square, index) => {
            const dark = (Math.floor(square / 8) + (square % 8)) % 2 === 1
            const piece = view.board[square] ?? null
            const isSource = sources.has(square)
            const isTarget = targets.has(square)
            const isLast =
              view.last !== null && (view.last.from === square || view.last.to === square)
            const jumped = view.captured.includes(square)
            const { file, rank } = coord(square, index)
            const labels = (
              <>
                {rank !== null ? (
                  <span
                    aria-hidden="true"
                    className={`absolute top-0.5 left-1 font-semibold ${dark ? 'text-[#e3d0ac]' : 'text-[#8b7355]'}`}
                  >
                    {rank}
                  </span>
                ) : null}
                {file !== null ? (
                  <span
                    aria-hidden="true"
                    className={`absolute right-1 bottom-0.5 font-semibold ${dark ? 'text-[#e3d0ac]' : 'text-[#8b7355]'}`}
                  >
                    {file}
                  </span>
                ) : null}
              </>
            )

            if (!dark) {
              return (
                <div key={square} aria-hidden="true" className="relative bg-[#e3d0ac]">
                  {labels}
                </div>
              )
            }

            return (
              <button
                key={square}
                type="button"
                onClick={() => click(square)}
                disabled={pending || (!isTarget && (!isSource || view.capturing !== null))}
                aria-pressed={selected === square}
                aria-label={`${squareName(square)}: ${piece ? t(pieceKeys[piece]) : t('checkers.empty')}`}
                className={`relative flex items-center justify-center bg-[#8b7355] shadow-[inset_0_0_0_0.5px_rgb(0_0_0/0.25)] disabled:cursor-default ${
                  isTarget ? 'cursor-pointer hover:bg-[#7d6649]' : ''
                }`}
              >
                {isLast ? (
                  <span aria-hidden="true" className="absolute inset-0 bg-gold/25" />
                ) : null}
                {selected === square ? (
                  <span
                    aria-hidden="true"
                    className="absolute inset-0 bg-gold/30 shadow-[inset_0_0_0_3px_var(--color-gold)]"
                  />
                ) : null}
                {labels}
                {piece ? (
                  <span
                    aria-hidden="true"
                    className={`relative flex size-[80%] items-center justify-center rounded-full text-[min(5vw,1.9rem)] leading-none transition-transform duration-150 ${
                      piece === 'w' || piece === 'W'
                        ? 'checker-white text-gold-dark'
                        : 'checker-black text-gold'
                    } ${jumped ? 'opacity-40' : ''} ${selected === square ? '-translate-y-0.5 scale-105' : ''} ${
                      isSource && myTurn && selected !== square
                        ? 'outline-2 outline-offset-2 outline-gold/80'
                        : ''
                    }`}
                  >
                    {piece === 'W' || piece === 'B' ? '♛' : null}
                  </span>
                ) : isTarget ? (
                  <span
                    aria-hidden="true"
                    className="relative size-[30%] rounded-full bg-gold/70 shadow-[0_0_0_4px_rgb(214_171_78/0.25)]"
                  />
                ) : null}
              </button>
            )
          })}
        </div>
      </div>
    </div>
  )
}
