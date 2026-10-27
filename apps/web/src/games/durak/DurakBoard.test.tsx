import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import DurakBoard from './DurakBoard'

function view(extra: object = {}) {
  return {
    hand: ['9H', 'TS', 'QC'],
    hand_counts: [3, 5],
    deck_count: 12,
    trump: 'S',
    trump_card: '6S',
    table: [],
    attacker: 0,
    defender: 1,
    taking: false,
    ...extra,
  }
}

describe('DurakBoard', () => {
  it('leads with a legal card', async () => {
    const onAction = vi.fn()
    render(
      <DurakBoard
        state={roomStateFixture({
          view: view(),
          legal_actions: ['9H', 'TS', 'QC'].map((card) => ({ type: 'attack', card })),
        })}
        onAction={onAction}
        pending={false}
      />,
    )

    expect(screen.getByText('12 cards in the deck')).toBeInTheDocument()
    expect(screen.getByLabelText('Seat 2 has 5 cards')).toBeInTheDocument()
    expect(screen.getByText('Your attack: lead a card.')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Queen of clubs' }))

    expect(onAction).toHaveBeenCalledWith({ type: 'attack', card: 'QC' })
  })

  it('defends with a card that beats the attack, or takes', async () => {
    const onAction = vi.fn()
    render(
      <DurakBoard
        state={roomStateFixture({
          view: view({ attacker: 1, defender: 0, table: [{ attack: '7H', defense: null }] }),
          legal_actions: [
            { type: 'defend', card: '9H' },
            { type: 'defend', card: 'TS' },
            { type: 'take' },
          ],
        })}
        onAction={onAction}
        pending={false}
      />,
    )

    expect(screen.getByText('Beat the card or take.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Queen of clubs' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'Take' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'take' })
  })

  it('shows every other player at a bigger table with their role', () => {
    render(
      <DurakBoard
        state={roomStateFixture({
          seat: 0,
          seats_to_act: [2],
          view: view({
            hand_counts: [3, 4, 6, 0],
            deck_count: 0,
            attacker: 0,
            defender: 1,
            passed: [],
            left: [false, false, false, false],
            table: [{ attack: '9C', defense: 'KC' }],
          }),
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
        players={{ 1: 'Levon', 2: 'Aram', 3: 'Ani' }}
      />,
    )

    expect(screen.getByLabelText('Levon has 4 cards')).toHaveTextContent('defending')
    expect(screen.getByLabelText('Aram has 6 cards')).toHaveAttribute('aria-current', 'step')
    expect(screen.getByLabelText('Ani has 0 cards')).toHaveTextContent('out')
  })

  it('lets the attacker finish the bout', async () => {
    const onAction = vi.fn()
    render(
      <DurakBoard
        state={roomStateFixture({
          view: view({ table: [{ attack: '9C', defense: 'KC' }] }),
          legal_actions: [{ type: 'done' }],
        })}
        onAction={onAction}
        pending={false}
      />,
    )

    expect(screen.getByRole('img', { name: 'beaten by King of clubs' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Done' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'done' })
  })
})
