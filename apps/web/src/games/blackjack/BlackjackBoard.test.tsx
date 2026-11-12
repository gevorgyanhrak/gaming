import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render as baseRender, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import BlackjackBoard, { type BlackjackView } from './BlackjackBoard'

// The board refreshes the wallet after a round, so it needs a query client.
const render = (ui: ReactNode) =>
  baseRender(ui, {
    wrapper: ({ children }) => (
      <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
    ),
  })

type Hand = BlackjackView['hands'][number][number]

const hand = (cards: string[], extra: Partial<Hand> = {}): Hand => ({
  cards,
  bet: 50,
  doubled: false,
  split: false,
  status: 'playing' as const,
  result: null,
  payout: 0,
  total: 16,
  soft: false,
  ...extra,
})

function view(extra: Partial<BlackjackView> = {}): BlackjackView {
  return {
    stacks: [950, 980],
    start: [1000, 1000],
    out: [false, false],
    round: 2,
    rounds: 10,
    limits: [10, 1000],
    h17: false,
    phase: 'playing',
    bets: [0, 0],
    hands: [[hand(['TS', '6D'])], [hand(['9H', '9C'], { bet: 20, total: 18 })]],
    dealer: ['7C', null],
    dealer_total: 7,
    to_act: 0,
    active: 0,
    last_round: null,
    ...extra,
  }
}

const players = { 0: 'alice', 1: 'Levon' }

describe('BlackjackBoard', () => {
  it('shows the dealer with the hole card down and offers the server’s actions', async () => {
    const onAction = vi.fn()
    render(
      <BlackjackBoard
        state={roomStateFixture({
          seat: 0,
          view: view() as never,
          legal_actions: [{ type: 'hit' }, { type: 'stand' }, { type: 'double' }],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    const dealer = screen.getByRole('region', { name: 'Dealer' })
    expect(within(dealer).getByLabelText('7 of clubs')).toBeInTheDocument()
    expect(within(dealer).getByLabelText('Dealer has 7')).toBeInTheDocument()
    expect(screen.getByText('Your total: 16')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Split' })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Double +50' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'double' })
    await userEvent.click(screen.getByRole('button', { name: 'Hit' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'hit' })
  })

  it('builds a bet from chips within the server’s range', async () => {
    const onAction = vi.fn()
    render(
      <BlackjackBoard
        state={roomStateFixture({
          seat: 0,
          view: view({
            phase: 'betting',
            hands: [[], []],
            dealer: [],
            dealer_total: null,
          }) as never,
          legal_actions: [{ type: 'bet', min: 10, max: 300 }],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    // Chips: 10, 50, 250 (1,000 is over the maximum).
    const chips = screen.getByRole('group', { name: 'Chips' })
    expect(
      within(chips)
        .getAllByRole('button')
        .map((b) => b.textContent),
    ).toEqual(['10', '50', '250'])
    await userEvent.click(within(chips).getByRole('button', { name: '250' }))
    await userEvent.click(screen.getByRole('button', { name: 'Deal · 250' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'bet', amount: 250 })
  })

  it('shows each player’s result after the round', () => {
    render(
      <BlackjackBoard
        state={roomStateFixture({
          seat: 0,
          view: view({
            phase: 'over',
            to_act: null,
            dealer: ['7C', 'TD'],
            dealer_total: 17,
            hands: [
              [hand(['TS', '6D', 'KH'], { status: 'bust', result: 'lose', total: 26 })],
              [hand(['9H', '9C'], { bet: 20, status: 'stood', result: 'win', total: 18 })],
            ],
            last_round: { number: 2, dealer: ['7C', 'TD'], dealer_total: 17, net: [-50, 20] },
          }) as never,
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText('Bust')).toBeInTheDocument()
    expect(screen.getByText('−50')).toBeInTheDocument()
    expect(screen.getByText('+20')).toBeInTheDocument()
    expect(screen.getByText('Next round')).toBeInTheDocument()
  })

  it('waits for the player whose turn it is', () => {
    render(
      <BlackjackBoard
        state={roomStateFixture({ seat: 0, view: view({ to_act: 1 }) as never, legal_actions: [] })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText('Waiting for Levon…')).toBeInTheDocument()
    expect(screen.getByRole('listitem', { current: 'step' })).toHaveAccessibleName(/Levon/)
  })
})
