import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import RummyBoard, { type RummyView } from './RummyBoard'

function view(extra: Partial<RummyView> = {}): RummyView {
  return {
    seat: 0,
    target: 100,
    scores: [10, 25],
    out: [false, false],
    dealer: 1,
    round: 2,
    phase: 'play',
    hand: ['7S', 'KD', '7H', '5H', '7D'],
    hand_sizes: [5, 8],
    stock_size: 20,
    discard_top: '9C',
    discard_size: 3,
    melds: [{ kind: 'run', cards: ['2H', '3H', '4H'], seat: 1 }],
    to_act: 0,
    taken: null,
    last_round: null,
    ...extra,
  }
}

const players = { 0: 'alice', 1: 'Levon' }

describe('RummyBoard', () => {
  it('melds the selected cards when the server lists that meld', async () => {
    const onAction = vi.fn()
    render(
      <RummyBoard
        state={roomStateFixture({
          seat: 0,
          view: view() as never,
          legal_actions: [
            { type: 'meld', cards: '7S,7H,7D' },
            { type: 'layoff', card: '5H', meld: 0 },
            { type: 'discard', card: 'KD' },
            { type: 'discard', card: '5H' },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    const hand = screen.getByRole('region', { name: 'Your cards' })
    const meld = screen.getByRole('button', { name: 'Meld' })
    expect(meld).toBeDisabled()
    for (const card of ['7 of spades', '7 of hearts', '7 of diamonds']) {
      await userEvent.click(within(hand).getByRole('button', { name: card }))
    }
    await userEvent.click(meld)
    expect(onAction).toHaveBeenCalledWith({ type: 'meld', cards: '7S,7H,7D' })
  })

  it('lays a card off on a meld it extends, or discards it', async () => {
    const onAction = vi.fn()
    render(
      <RummyBoard
        state={roomStateFixture({
          seat: 0,
          view: view() as never,
          legal_actions: [
            { type: 'layoff', card: '5H', meld: 0 },
            { type: 'discard', card: '5H' },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: '5 of hearts' }))
    expect(screen.getByRole('button', { name: 'Discard' })).toBeEnabled()
    await userEvent.click(screen.getByRole('button', { name: /Lay off the 5 of hearts/ }))
    expect(onAction).toHaveBeenCalledWith({ type: 'layoff', card: '5H', meld: 0 })
  })

  it('draws from the stock or the discard pile', async () => {
    const onAction = vi.fn()
    render(
      <RummyBoard
        state={roomStateFixture({
          seat: 0,
          view: view({ phase: 'draw' }) as never,
          legal_actions: [
            { type: 'draw', from: 'stock' },
            { type: 'draw', from: 'discard' },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    await userEvent.click(
      screen.getByRole('button', { name: 'Take the 9 of clubs from the discard pile' }),
    )
    expect(onAction).toHaveBeenCalledWith({ type: 'draw', from: 'discard' })
    expect(
      screen.getByText('Your turn: draw from the stock or the discard pile'),
    ).toBeInTheDocument()
  })
})
