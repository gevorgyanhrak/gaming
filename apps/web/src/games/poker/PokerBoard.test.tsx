import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import PokerBoard from './PokerBoard'

function view(extra: object = {}) {
  return {
    stacks: [990, 995, 1000],
    out: [false, false, false],
    button: 2,
    hand: 3,
    blinds: [5, 10],
    board: [],
    street: 'preflop',
    bets: [10, 5, 0],
    folded: [false, false, false],
    current_bet: 10,
    to_act: 2,
    hand_cards: ['AS', 'KH'],
    pot: 15,
    last_hand: null,
    ...extra,
  }
}

const players = { 0: 'Aram', 1: 'Levon', 2: 'alice' }

describe('PokerBoard', () => {
  it('shows the table, your cards and calls', async () => {
    const onAction = vi.fn()
    render(
      <PokerBoard
        state={roomStateFixture({
          seat: 2,
          seats_to_act: [2],
          view: view(),
          legal_actions: [
            { type: 'fold' },
            { type: 'call' },
            { type: 'raise', min: 20, max: 1000 },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText('Aram')).toBeInTheDocument()
    expect(screen.getByText('Pot: 15 chips')).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Ace of spades' })).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'King of hearts' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Call 10' }))

    expect(onAction).toHaveBeenCalledWith({ type: 'call' })
  })

  it('raises any amount in the allowed range', async () => {
    const onAction = vi.fn()
    render(
      <PokerBoard
        state={roomStateFixture({
          seat: 2,
          seats_to_act: [2],
          view: view(),
          legal_actions: [
            { type: 'fold' },
            { type: 'call' },
            { type: 'raise', min: 20, max: 1000 },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Pot' }))
    await userEvent.click(screen.getByRole('button', { name: 'Raise to 35' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'raise', amount: 35 })

    await userEvent.click(screen.getByRole('button', { name: 'All-in' }))
    await userEvent.click(screen.getByRole('button', { name: 'All-in 1000' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'raise', amount: 1000 })
  })

  it('shows the last hand’s winners and the cards shown down', () => {
    render(
      <PokerBoard
        state={roomStateFixture({
          seat: 2,
          seats_to_act: [0],
          view: view({
            to_act: 0,
            last_hand: {
              number: 2,
              board: ['2C', '7H', '9D', 'JC', '3S'],
              shown: { 0: ['QS', 'QD'], 2: ['AS', 'AD'] },
              pots: [{ amount: 200, winners: [2], hand: 'pair' }],
            },
          }),
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText('Waiting for Aram.')).toBeInTheDocument()
    expect(screen.getByText(/You won 200 chips — a pair/)).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Queen of diamonds' })).toBeInTheDocument()
  })
})
