import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/shared/ui/Button'
import { waitingCaption } from '@/features/play/turnCaption'
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

// The solid glyphs for both sides, coloured by CSS; U+FE0E keeps the pawn from turning into an emoji.
const glyphs: Record<string, string> = {
  k: '♚\uFE0E',
  q: '♛\uFE0E',
  r: '♜\uFE0E',
  b: '♝\uFE0E',
  n: '♞\uFE0E',
  p: '♟\uFE0E',
}

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
export default function ChessBoard({ state, onAction, pending, players }: BoardProps) {
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
    <div className="flex flex-col items-center gap-6 lg:flex-row lg:items-start lg:justify-center">
      <div className="w-full max-w-[max(20rem,min(38rem,calc(100dvh-24rem)))] space-y-3">
        {state.seats_to_act.length > 0 ? (
          <p className="text-center text-2xl font-semibold sm:text-3xl">
            {view.turn === 0 ? t('chess.moveWhite') : t('chess.moveBlack')}
          </p>
        ) : null}
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
          <p className="font-medium">{me === 0 ? t('chess.youWhite') : t('chess.youBlack')}</p>
          <p className={view.check ? 'font-semibold text-take' : 'text-muted'} aria-live="polite">
            {view.check ? `${t('chess.check')} ` : ''}
            {!myTurn
              ? (waitingCaption(state, players, t) ?? '')
              : selected === null
                ? t('chess.pickPiece')
                : t('chess.pickTarget')}
          </p>
        </div>

        <div className="table-rim rounded-xl p-2 sm:p-3">
          <div className="grid aspect-square w-full grid-cols-8 grid-rows-8 overflow-hidden rounded-sm text-[clamp(0.55rem,1.6vw,0.75rem)] shadow-[inset_0_0_0_1px_rgb(0_0_0/0.35)]">
            {order.map((square, index) => {
              const dark = (Math.floor(square / 8) + (square % 8)) % 2 === 1
              const piece = view.board[square] ?? null
              const isTarget = targets.has(square)
              const isLast =
                view.last !== null && (view.last.from === square || view.last.to === square)
              // Files along the bottom row and ranks down the left edge, as seen from this side.
              const file = index >= 56 ? 'abcdefgh'[square % 8] : null
              const rank = index % 8 === 0 ? 8 - Math.floor(square / 8) : null
              const ink = dark ? 'text-[#e3d0ac]' : 'text-[#8b7355]'

              return (
                <button
                  key={square}
                  type="button"
                  onClick={() => click(square)}
                  disabled={pending || (!isTarget && !sources.has(square))}
                  aria-pressed={selected === square}
                  aria-label={`${squareName(square)}: ${piece ? t(pieceKeys[piece]) : t('chess.empty')}`}
                  className={`relative flex items-center justify-center disabled:cursor-default ${
                    dark ? 'bg-[#8b7355]' : 'bg-[#e3d0ac]'
                  }`}
                >
                  {isLast ? (
                    <span aria-hidden="true" className="absolute inset-0 bg-gold/30" />
                  ) : null}
                  {selected === square ? (
                    <span
                      aria-hidden="true"
                      className="absolute inset-0 bg-gold/35 shadow-[inset_0_0_0_3px_var(--color-gold)]"
                    />
                  ) : null}
                  {square === checkedKing ? (
                    <span
                      aria-hidden="true"
                      className="absolute inset-0 bg-[radial-gradient(circle,rgb(217_72_59/0.95)_0,rgb(217_72_59/0.5)_45%,transparent_72%)]"
                    />
                  ) : null}
                  {rank !== null ? (
                    <span
                      aria-hidden="true"
                      className={`absolute top-0.5 left-1 font-semibold ${ink}`}
                    >
                      {rank}
                    </span>
                  ) : null}
                  {file !== null ? (
                    <span
                      aria-hidden="true"
                      className={`absolute right-1 bottom-0.5 font-semibold ${ink}`}
                    >
                      {file}
                    </span>
                  ) : null}
                  {piece ? (
                    <span
                      aria-hidden="true"
                      className={`relative text-[min(9.5vw,3.3rem)] leading-none transition-transform duration-150 select-none ${
                        isWhite(piece)
                          ? 'text-[#f7f3e3] [-webkit-text-stroke:1.2px_#3a2c1c] [text-shadow:0_2px_3px_rgb(0_0_0/0.45)]'
                          : 'text-[#2a2522] [-webkit-text-stroke:0.6px_#0d0b0a] [text-shadow:0_1px_0_rgb(255_255_255/0.25),0_2px_3px_rgb(0_0_0/0.45)]'
                      } ${selected === square ? '-translate-y-0.5 scale-110' : ''}`}
                    >
                      {glyphs[piece.toLowerCase()]}
                    </span>
                  ) : null}
                  {isTarget ? (
                    <span
                      aria-hidden="true"
                      className={`absolute rounded-full ${
                        piece
                          ? 'inset-[6%] border-[0.3rem] border-gold/80'
                          : 'size-[30%] bg-gold/70 shadow-[0_0_0_4px_rgb(214_171_78/0.25)]'
                      }`}
                    />
                  ) : null}
                </button>
              )
            })}
          </div>
        </div>

        {offer !== null || canOffer ? (
          <div className="flex flex-wrap items-center gap-3 text-sm">
            {offer === 1 - me ? <p className="font-medium">{t('chess.drawOffered')}</p> : null}
            {offer === me ? <p className="text-muted">{t('chess.drawOfferSent')}</p> : null}
            {canAccept ? (
              <Button
                variant="gold"
                disabled={pending}
                onClick={() => onAction({ type: 'accept_draw' })}
              >
                {t('chess.acceptDraw')}
              </Button>
            ) : null}
            {canOffer ? (
              <Button
                variant="secondary"
                disabled={pending}
                onClick={() => onAction({ type: 'offer_draw' })}
              >
                {t('chess.offerDraw')}
              </Button>
            ) : null}
          </div>
        ) : null}

        {promoting ? (
          <div
            role="group"
            aria-label={t('chess.promoteTo')}
            className="club-panel flex flex-wrap items-center gap-2 rounded-2xl p-3"
          >
            <span className="text-sm text-muted">{t('chess.promoteTo')}</span>
            {promoting.map((move) =>
              move.promotion ? (
                <Button key={move.promotion} variant="gold" onClick={() => play(move)}>
                  {t(promotionKeys[move.promotion])}
                </Button>
              ) : null,
            )}
          </div>
        ) : null}
      </div>

      <section
        className="club-panel w-full max-w-[max(20rem,min(38rem,calc(100dvh-24rem)))] rounded-2xl p-4 lg:mt-20 lg:w-60"
        aria-label={t('chess.moves')}
      >
        <h2 className="mb-2 text-xs font-semibold tracking-wider text-muted uppercase">
          {t('chess.moves')}
        </h2>
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
