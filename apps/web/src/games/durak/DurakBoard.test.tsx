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

    expect(
      screen.getByRole('group', { name: /^12 cards in the deck\. Trump: ♠/ }),
    ).toBeInTheDocument()
    expect(screen.getByLabelText('Seat 2 has 5 cards, defending')).toHaveTextContent('defending')
    // Nothing to take or finish yet: no action button, and no visible "empty table" text.
    expect(screen.queryByRole('button', { name: /Take|Done|Pass/ })).not.toBeInTheDocument()
    expect(screen.getByText('The table is empty.')).toHaveClass('sr-only')
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
        bots={{ 0: false, 1: false, 2: true, 3: false }}
      />,
    )

    expect(screen.getByRole('list', { name: 'Opponents' })).toBeInTheDocument()
    expect(screen.getByLabelText('Levon has 4 cards, defending')).toHaveTextContent('defending')
    expect(screen.getByLabelText('Levon has 4 cards, defending')).not.toHaveTextContent('Bot')
    // Bots are marked at their seat, since the room page doesn't list the players at card tables.
    expect(screen.getByLabelText('Aram has 6 cards, Bot')).toHaveAttribute('aria-current', 'step')
    expect(screen.getByLabelText('Aram has 6 cards, Bot')).toHaveTextContent('Bot')
    expect(screen.getByLabelText('Ani has 0 cards, out')).toHaveTextContent('out')
    expect(
      screen.getByRole('listitem', { name: '9 of clubs, beaten by King of clubs' }),
    ).toBeInTheDocument()
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

  it('marks only the loser once the game is over, with no status or action', () => {
    render(
      <DurakBoard
        state={roomStateFixture({
          seat: 0,
          seats_to_act: [],
          result: { end_reason: 'completed', seats: ['win', 'loss', 'win'] },
          view: view({ hand: [], hand_counts: [0, 4, 0], deck_count: 0, passed: [2] }),
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
        players={{ 1: 'Levon', 2: 'Aram' }}
      />,
    )

    expect(screen.getByLabelText('Levon has 4 cards, durak')).toHaveTextContent('durak')
    expect(screen.getByLabelText('Aram has 0 cards')).not.toHaveTextContent(/out|passed/)
    expect(screen.queryByText('Your attack: lead a card.')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Take|Done|Pass/ })).not.toBeInTheDocument()
  })

  it('lets the attacker throw in before the take, or let them take', async () => {
    const onAction = vi.fn()
    render(
      <DurakBoard
        state={roomStateFixture({
          view: view({ taking: true, table: [{ attack: '9C', defense: null }] }),
          legal_actions: [{ type: 'attack', card: '9H' }, { type: 'done' }],
        })}
        onAction={onAction}
        pending={false}
        players={{ 1: 'Levon' }}
      />,
    )

    expect(screen.getByLabelText('Levon has 5 cards, taking')).toBeInTheDocument()
    expect(screen.getByRole('listitem', { name: '9 of clubs, not beaten' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '9 of hearts' })).toBeEnabled()
    await userEvent.click(screen.getByRole('button', { name: 'Let them take' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'done' })
  })
})
