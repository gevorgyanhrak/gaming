import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import ChessBoard from './ChessBoard'

/** a8 = 0, h1 = 63, as the API numbers squares. */
const sq = (name: string) => (8 - Number(name[1])) * 8 + name.charCodeAt(0) - 97

function view(pieces: Record<string, string>, extra: object = {}) {
  const board = Array<string | null>(64).fill(null)
  for (const [name, piece] of Object.entries(pieces)) board[sq(name)] = piece
  return { board, turn: 0, check: false, last: null, moves: [], ...extra }
}

describe('ChessBoard', () => {
  it('offers the legal moves of the picked piece', async () => {
    const onAction = vi.fn()
    render(
      <ChessBoard
        state={roomStateFixture({
          view: view({ e1: 'K', e2: 'P', e8: 'k' }),
          legal_actions: [
            { type: 'move', from: sq('e2'), to: sq('e3') },
            { type: 'move', from: sq('e2'), to: sq('e4') },
            { type: 'move', from: sq('e1'), to: sq('d1') },
          ],
        })}
        onAction={onAction}
        pending={false}
      />,
    )

    expect(screen.getByRole('button', { name: 'e8: black king' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'e2: white pawn' }))
    expect(screen.getByRole('button', { name: 'd1: empty' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'e4: empty' }))

    expect(onAction).toHaveBeenCalledWith({ type: 'move', from: sq('e2'), to: sq('e4') })
  })

  it('asks which piece to promote to', async () => {
    const onAction = vi.fn()
    render(
      <ChessBoard
        state={roomStateFixture({
          view: view({ e7: 'P', e1: 'K', a2: 'k' }),
          legal_actions: (['q', 'r', 'b', 'n'] as const).map((promotion) => ({
            type: 'move',
            from: sq('e7'),
            to: sq('e8'),
            promotion,
          })),
        })}
        onAction={onAction}
        pending={false}
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'e7: white pawn' }))
    await userEvent.click(screen.getByRole('button', { name: 'e8: empty' }))
    expect(onAction).not.toHaveBeenCalled()
    await userEvent.click(screen.getByRole('button', { name: 'Knight' }))

    expect(onAction).toHaveBeenCalledWith({
      type: 'move',
      from: sq('e7'),
      to: sq('e8'),
      promotion: 'n',
    })
  })

  it('shows check, the move list and the board from black’s side', () => {
    render(
      <ChessBoard
        state={roomStateFixture({
          seat: 1,
          seats_to_act: [1],
          view: view(
            { e1: 'K', e8: 'k', h5: 'Q' },
            { turn: 1, check: true, moves: ['e4', 'f6', 'Qh5+'] },
          ),
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
      />,
    )

    expect(screen.getByText(/Check!/)).toBeInTheDocument()
    expect(screen.getByText('Qh5+')).toBeInTheDocument()
    const names = screen.getAllByRole('button').map((b) => b.getAttribute('aria-label'))
    expect(names[0]).toBe('h1: empty')
    expect(names.at(-1)).toBe('a8: empty')
  })
})
