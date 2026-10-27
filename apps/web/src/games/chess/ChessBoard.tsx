import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { BoardProps } from '../registry'

type Piece = 'K' | 'Q' | 'R' | 'B' | 'N' | 'P' | 'k' | 'q' | 'r' | 'b' | 'n' | 'p'
type Promotion = 'q' | 'r' | 'b' | 'n'

interface ChessView {
  board: (Piece | null)[]
  turn: number
  check: boolean
  last: { from: number; to: number } | null
  moves: string[]
  draw_offer?: number | null
}

type Move = { type: 'move'; from: number; to: number; promotion?: Promotion }
type ChessAction = Move | { type: 'offer_draw' | 'accept_draw' }

const glyphs: Record<string, string> = { k: '♚', q: '♛', r: '♜', b: '♝', n: '♞', p: '♟' }

const pieceKeys = {
  K: 'chess.piece.whiteKing',
  Q: 'chess.piece.whiteQueen',
  R: 'chess.piece.whiteRook',
  B: 'chess.piece.whiteBishop',
  N: 'chess.piece.whiteKnight',
  P: 'chess.piece.whitePawn',
  k: 'chess.piece.blackKing',
  q: 'chess.piece.blackQueen',
  r: 'chess.piece.blackRook',
  b: 'chess.piece.blackBishop',
  n: 'chess.piece.blackKnight',
  p: 'chess.piece.blackPawn',
} as const

const promotionKeys = {
  q: 'chess.piece.queen',
  r: 'chess.piece.rook',
  b: 'chess.piece.bishop',
  n: 'chess.piece.knight',
} as const

/** "a8" is square 0, "h1" square 63. */
const squareName = (square: number) =>
  `${'abcdefgh'[square % 8] ?? ''}${8 - Math.floor(square / 8)}`
const isWhite = (piece: Piece) => piece === piece.toUpperCase()

/**
 * Chess board. Renders the server's view from the player's side (black sees the board turned) and
 * offers exactly the server's legal moves: pick a piece, then a target; promotions ask which piece.
 */
export default function ChessBoard({ state, onAction, pending }: BoardProps) {
  const { t } = useTranslation()
  const view = state.view as unknown as ChessView
  const me = state.seat ?? 0
  const actions = state.legal_actions as unknown as ChessAction[]
  const moves = actions.filter((a): a is Move => a.type === 'move')
  const canOffer = actions.some((a) => a.type === 'offer_draw')
  const canAccept = actions.some((a) => a.type === 'accept_draw')
  const offer = view.draw_offer ?? null
  const [selected, setSelected] = useState<number | null>(null)
  const [promoting, setPromoting] = useState<Move[] | null>(null)

  const sources = new Set(moves.map((m) => m.from))
  const targets = new Set(moves.filter((m) => m.from === selected).map((m) => m.to))
  const myTurn = state.seats_to_act.includes(me)
  const order = Array.from({ length: 64 }, (_, i) => (me === 0 ? i : 63 - i))
  const checkedKing = view.check ? view.board.indexOf(view.turn === 0 ? 'K' : 'k') : -1

  const play = (move: Move) => {
    setSelected(null)
    setPromoting(null)
    onAction(move)
  }

  const click = (square: number) => {
    if (pending) return
    const options = moves.filter((m) => m.from === selected && m.to === square)
    if (options.length > 1) return setPromoting(options)
    if (options[0]) return play(options[0])
    setPromoting(null)
    setSelected(sources.has(square) && selected !== square ? square : null)
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,32rem)_1fr]">
      <div className="space-y-3">
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
          <p className="font-medium">{me === 0 ? t('chess.youWhite') : t('chess.youBlack')}</p>
          <p className="text-muted" aria-live="polite">
            {view.check ? `${t('chess.check')} ` : ''}
            {!myTurn
              ? t('chess.waiting')
              : selected === null
                ? t('chess.pickPiece')
                : t('chess.pickTarget')}
          </p>
        </div>

        <div className="grid aspect-square w-full grid-cols-8 overflow-hidden rounded-2xl border border-border">
          {order.map((square) => {
            const dark = (Math.floor(square / 8) + (square % 8)) % 2 === 1
            const piece = view.board[square] ?? null
            const isTarget = targets.has(square)
            const isLast =
              view.last !== null && (view.last.from === square || view.last.to === square)

            return (
              <button
                key={square}
                type="button"
                onClick={() => click(square)}
                disabled={pending || (!isTarget && !sources.has(square))}
                aria-pressed={selected === square}
                aria-label={`${squareName(square)}: ${piece ? t(pieceKeys[piece]) : t('chess.empty')}`}
                className={`relative flex items-center justify-center disabled:cursor-default ${
                  dark ? 'bg-[#7c6a9e]' : 'bg-[#d9d2e9]'
                } ${isLast ? 'after:absolute after:inset-0 after:bg-warning/30' : ''} ${
                  selected === square ? 'ring-4 ring-accent ring-inset' : ''
                } ${square === checkedKing ? 'bg-danger/70' : ''}`}
              >
                {piece ? (
                  <span
                    aria-hidden="true"
                    className={`relative z-10 text-[min(9vw,3.2rem)] leading-none ${
                      isWhite(piece)
                        ? 'text-white [text-shadow:0_0_2px_#000,0_0_1px_#000]'
                        : 'text-neutral-900 [text-shadow:0_0_1px_#fff]'
                    }`}
                  >
                    {glyphs[piece.toLowerCase()]}
                  </span>
                ) : null}
                {isTarget ? (
                  <span
                    aria-hidden="true"
                    className={`absolute z-20 rounded-full ${piece ? 'inset-1 border-4 border-accent/70' : 'size-1/4 bg-accent/70'}`}
                  />
                ) : null}
              </button>
            )
          })}
        </div>

        {offer !== null || canOffer ? (
          <div className="flex flex-wrap items-center gap-3 text-sm">
            {offer === 1 - me ? <p className="font-medium">{t('chess.drawOffered')}</p> : null}
            {offer === me ? <p className="text-muted">{t('chess.drawOfferSent')}</p> : null}
            {canAccept ? (
              <button
                type="button"
                disabled={pending}
                onClick={() => onAction({ type: 'accept_draw' })}
                className="rounded-lg bg-accent px-3 py-1.5 font-semibold text-white hover:bg-accent-hover"
              >
                {t('chess.acceptDraw')}
              </button>
            ) : null}
            {canOffer ? (
              <button
                type="button"
                disabled={pending}
                onClick={() => onAction({ type: 'offer_draw' })}
                className="rounded-lg border border-border px-3 py-1.5 font-medium hover:border-accent-text"
              >
                {t('chess.offerDraw')}
              </button>
            ) : null}
          </div>
        ) : null}

        {promoting ? (
          <div
            role="group"
            aria-label={t('chess.promoteTo')}
            className="flex flex-wrap items-center gap-2"
          >
            <span className="text-sm text-muted">{t('chess.promoteTo')}</span>
            {promoting.map((move) =>
              move.promotion ? (
                <button
                  key={move.promotion}
                  type="button"
                  onClick={() => play(move)}
                  className="rounded-lg border border-border bg-surface-raised px-3 py-1 text-sm font-medium hover:border-accent-text"
                >
                  {t(promotionKeys[move.promotion])}
                </button>
              ) : null,
            )}
          </div>
        ) : null}
      </div>

      <section
        className="rounded-2xl border border-border bg-surface p-4"
        aria-label={t('chess.moves')}
      >
        <h2 className="mb-2 text-sm font-semibold text-muted">{t('chess.moves')}</h2>
        {view.moves.length === 0 ? (
          <p className="text-sm text-muted">{t('chess.noMoves')}</p>
        ) : (
          <ol className="grid max-h-80 grid-cols-[auto_1fr_1fr] gap-x-3 gap-y-1 overflow-y-auto font-mono text-sm">
            {Array.from({ length: Math.ceil(view.moves.length / 2) }, (_, i) => (
              <li key={i} className="contents">
                <span className="text-muted">{i + 1}.</span>
                <span>{view.moves[i * 2]}</span>
                <span>{view.moves[i * 2 + 1] ?? ''}</span>
              </li>
            ))}
          </ol>
        )}
      </section>
    </div>
  )
}
