import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import CheckersBoard from './CheckersBoard'

/** Square index of an algebraic name, as the API numbers them (a8 = 0). */
const sq = (name: string) => (8 - Number(name[1])) * 8 + name.charCodeAt(0) - 97

function view(pieces: Record<string, string>, extra: object = {}) {
  const board = Array<string | null>(64).fill(null)
  for (const [name, piece] of Object.entries(pieces)) board[sq(name)] = piece
  return { board, turn: 0, capturing: null, captured: [], last: null, ...extra }
}

describe('CheckersBoard', () => {
  it('offers the server’s legal steps: pick a piece, then a target', async () => {
    const onAction = vi.fn()
    render(
      <CheckersBoard
        state={roomStateFixture({
          view: view({ c3: 'w', e3: 'w', f6: 'b' }),
          legal_actions: [
            { type: 'move', from: sq('c3'), to: sq('b4') },
            { type: 'move', from: sq('c3'), to: sq('d4') },
            { type: 'move', from: sq('e3'), to: sq('f4') },
          ],
        })}
        onAction={onAction}
        pending={false}
      />,
    )

    expect(screen.getByRole('button', { name: 'f6: black man' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'c3: white man' }))
    expect(screen.getByRole('button', { name: 'f4: empty' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'd4: empty' }))

    expect(onAction).toHaveBeenCalledWith({ type: 'move', from: sq('c3'), to: sq('d4') })
  })

  it('keeps the capturing piece selected during a capture series', async () => {
    const onAction = vi.fn()
    render(
      <CheckersBoard
        state={roomStateFixture({
          view: view({ e5: 'W', d4: 'b', f6: 'b' }, { capturing: sq('e5'), captured: [sq('d4')] }),
          legal_actions: [{ type: 'move', from: sq('e5'), to: sq('g7') }],
        })}
        onAction={onAction}
        pending={false}
      />,
    )

    expect(screen.getByText('Keep capturing with the same piece.')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'g7: empty' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'move', from: sq('e5'), to: sq('g7') })
  })

  it('turns the board around for black', () => {
    render(
      <CheckersBoard
        state={roomStateFixture({
          seat: 1,
          seats_to_act: [0],
          view: view({ a1: 'w', h8: 'b' }),
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
      />,
    )

    // Black's home row (rank 8) is at the bottom, starting from h8 on the left.
    const names = screen.getAllByRole('button').map((b) => b.getAttribute('aria-label'))
    expect(names.slice(0, 4)).toEqual(['g1: empty', 'e1: empty', 'c1: empty', 'a1: white man'])
    expect(names.slice(-4)).toEqual(['h8: black man', 'f8: empty', 'd8: empty', 'b8: empty'])
    expect(screen.getByText('You play black')).toBeInTheDocument()
  })
})
