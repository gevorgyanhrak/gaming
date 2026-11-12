import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, render as baseRender, screen, within } from '@testing-library/react'
import type { ReactNode } from 'react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import BlotBoard, { type BlotView } from './BlotBoard'

// The board reads the player's sound setting, so it needs a query client.
const render = (ui: ReactNode) =>
  baseRender(ui, {
    wrapper: ({ children }) => (
      <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
    ),
  })

const contract = (extra: Partial<NonNullable<BlotView['contract']>> = {}) => ({
  seat: 1,
  team: 1,
  value: 9,
  suit: 'H' as const,
  kaput: false,
  doubled: 0 as const,
  ...extra,
})

function view(extra: Partial<BlotView> = {}): BlotView {
  return {
    seat: 0,
    dealer: 3,
    hand_no: 2,
    phase: 'playing',
    hand: ['7S', 'AH', 'JH', 'KD', '9C', 'TS', 'QH', '8D'],
    hand_sizes: [8, 8, 8, 8],
    bids: [],
    contract: contract(),
    trump: 'H',
    to_act: 0,
    trick: [],
    last_trick: null,
    tricks: [0, 0],
    points: [0, 0],
    my_declarations: [],
    declared: [[], [], [], []],
    declarations_shown: false,
    belote: null,
    opener: null,
    log: [],
    scores: [22, 4],
    target: 301,
    last_hand: null,
    ...extra,
  }
}

const players = { 0: 'alice', 1: 'Aram', 2: 'bob', 3: 'Levon' }

const renderBoard = (v: BlotView, legal: object[], onAction = vi.fn()) =>
  render(
    <BlotBoard
      state={roomStateFixture({ seat: v.seat, view: v as never, legal_actions: legal })}
      onAction={onAction}
      pending={false}
      players={players}
    />,
  )

describe('BlotBoard', () => {
  it('plays only the cards the server allows and shows the match score in sai', async () => {
    const onAction = vi.fn()
    renderBoard(
      view(),
      [
        { type: 'play', card: 'AH' },
        { type: 'play', card: 'JH' },
      ],
      onAction,
    )

    const hand = screen.getByRole('region', { name: 'Your cards' })
    expect(within(hand).getByRole('button', { name: '7 of spades' })).toBeDisabled()
    await userEvent.click(within(hand).getByRole('button', { name: 'Jack of hearts' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'play', card: 'JH' })
    const score = screen.getByLabelText('Score')
    expect(within(score).getByText('22')).toBeInTheDocument()
    expect(screen.getByText(/to 301/)).toBeInTheDocument()
    expect(screen.getByText(/Tricks: us 0 · them 0\s+· 8 left/)).toBeInTheDocument()
    expect(screen.getByText('Koz (trump)')).toBeInTheDocument()
    expect(screen.getByText('×1')).toBeInTheDocument()
  })

  it('bids a number in a suit or no-trump, kaput, and contra', async () => {
    const onAction = vi.fn()
    renderBoard(
      view({
        phase: 'bidding',
        contract: contract({ seat: 3, suit: 'S' }),
        trump: null,
        bids: [{ seat: 3, type: 'bid', value: 9, suit: 'S' }],
        log: [{ type: 'bid', seat: 3, value: 9, suit: 'S' }],
      }),
      [
        { type: 'bid', min: 10, max: 24 },
        ...['S', 'H', 'D', 'C', 'N'].map((suit) => ({ type: 'kaput', suit })),
        { type: 'pass' },
        { type: 'contra' },
      ],
      onAction,
    )

    // Their call shows in a speech bubble at their seat.
    expect(
      within(screen.getByRole('listitem', { name: /Levon/ })).getByText('9'),
    ).toBeInTheDocument()
    // No suit picked yet: nothing to bid.
    expect(screen.getByRole('button', { name: 'Pick a suit or Boy' })).toBeDisabled()
    await userEvent.click(screen.getByRole('radio', { name: 'Boy (no trump)' }))
    await userEvent.click(screen.getByRole('button', { name: 'Higher bid' }))
    await userEvent.click(screen.getByRole('button', { name: 'Bid 11 in Boy (no trump)' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'bid', value: 11, suit: 'N' })

    await userEvent.click(screen.getByRole('radio', { name: 'hearts' }))
    await userEvent.click(screen.getByRole('button', { name: 'Bid kaput…' }))
    expect(screen.getByText(/^Kaput: your pair takes all 8 tricks/)).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Bid kaput in hearts' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'kaput', suit: 'H' })

    await userEvent.click(screen.getByRole('button', { name: 'Quansh' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'contra' })
  })

  it('raises a number past 24, and over a kaput offers only another kaput', async () => {
    const onAction = vi.fn()
    const { unmount } = renderBoard(
      view({
        phase: 'bidding',
        contract: contract({ seat: 1, value: 24, suit: 'H' }),
        trump: null,
        bids: [{ seat: 1, type: 'bid', value: 24, suit: 'H' }],
        log: [{ type: 'bid', seat: 1, value: 24, suit: 'H' }],
      }),
      [
        { type: 'bid', min: 25, max: 99 },
        ...['S', 'H', 'D', 'C', 'N'].map((suit) => ({ type: 'kaput', suit })),
        { type: 'pass' },
        { type: 'contra' },
      ],
      onAction,
    )
    await userEvent.click(screen.getByRole('radio', { name: 'spades' }))
    await userEvent.click(screen.getByRole('button', { name: 'Higher bid' }))
    await userEvent.click(screen.getByRole('button', { name: 'Bid 26 in spades' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'bid', value: 26, suit: 'S' })
    unmount()

    renderBoard(
      view({
        phase: 'bidding',
        contract: contract({ seat: 1, value: 25, suit: 'H', kaput: true }),
        trump: null,
        bids: [{ seat: 1, type: 'kaput', suit: 'H' }],
        log: [{ type: 'kaput', seat: 1, suit: 'H' }],
      }),
      [
        ...['S', 'H', 'D', 'C', 'N'].map((suit) => ({ type: 'kaput', suit })),
        { type: 'pass' },
        { type: 'contra' },
      ],
      onAction,
    )
    expect(screen.getByText(/^Over a Kaput only another Kaput/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Higher bid' })).toBeDisabled()
    await userEvent.click(screen.getByRole('radio', { name: 'diamonds' }))
    await userEvent.click(screen.getByRole('button', { name: 'Bid kaput in diamonds' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'kaput', suit: 'D' })
  })

  it('answers a contra with recontra or pass', async () => {
    const onAction = vi.fn()
    renderBoard(
      view({ phase: 'answer', contract: contract({ seat: 2, team: 0, doubled: 1 }) }),
      [{ type: 'recontra' }, { type: 'pass' }],
      onAction,
    )

    expect(screen.queryByRole('button', { name: 'Higher bid' })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Sur' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'recontra' })
  })

  it('offers your combinations to declare before your first card', async () => {
    const onAction = vi.fn()
    renderBoard(
      view({
        my_declarations: [
          { index: 0, kind: 'terz', points: 20, cards: ['7S', '8S', '9S'], declared: false },
          { index: 1, kind: 'fifty', points: 50, cards: ['JD', 'QD', 'KD', 'AD'], declared: true },
        ],
      }),
      [
        { type: 'declare', index: 0 },
        { type: 'play', card: 'AH' },
      ],
      onAction,
    )

    const panel = screen.getByRole('region', { name: 'Your combinations' })
    // Only what can still be declared (the 50 already was).
    expect(within(panel).queryByText(/Poltinnik/)).not.toBeInTheDocument()
    expect(screen.getByText('Declare your combinations, then play a card')).toBeInTheDocument()
    await userEvent.click(within(panel).getByRole('button', { name: /Declare Terz \+20/ }))
    expect(onAction).toHaveBeenCalledWith({ type: 'declare', index: 0 })
  })

  it('shows the deal in sai, the match total, and that the match goes on', () => {
    renderBoard(
      view({
        seat: 1,
        phase: 'over',
        to_act: null,
        hand: [],
        scores: [4, 22],
        last_hand: {
          number: 1,
          contract: contract(),
          made: true,
          tricks: [2, 6],
          card_points: [38, 124],
          card_sai: [4, 12],
          declarations: [
            { seat: 1, kind: 'terz', points: 20, cards: ['7C', '8C', '9C'], counted: true },
          ],
          declaration_sai: [0, 2],
          belote_sai: [0, 0],
          bid_sai: 9,
          scored: [4, 23],
          totals: [4, 23],
          kaput: false,
        },
      }),
      [],
    )

    const result = screen.getByRole('region', { name: 'Deal result' })
    expect(within(result).getByText(/You made 9/)).toBeInTheDocument()
    const deal = within(result).getByRole('row', { name: /This deal/ })
    expect(
      within(deal)
        .getAllByRole('cell')
        .map((c) => c.textContent),
    ).toEqual(['23', '4'])
    const contractRow = within(result).getByRole('row', { name: /Contract/ })
    expect(
      within(contractRow)
        .getAllByRole('cell')
        .map((c) => c.textContent),
    ).toEqual(['9', '0'])
    expect(within(result).getByText('The match goes on: first to 301.')).toBeInTheDocument()
    expect(within(result).getByText('You: Terz +20')).toBeInTheDocument()
  })

  it('announces a new declaration to the table with its exact cards', async () => {
    const legal = [{ type: 'play', card: 'AH' }]
    const props = { onAction: vi.fn(), pending: false, players }
    const { rerender } = render(
      <BlotBoard
        state={roomStateFixture({ seat: 0, view: view() as never, legal_actions: legal })}
        {...props}
      />,
    )
    expect(screen.queryByRole('status', { name: /Terz/ })).not.toBeInTheDocument()

    const terz = { kind: 'terz' as const, points: 20, cards: ['7C', '8C', '9C'] }
    rerender(
      <BlotBoard
        state={roomStateFixture({
          seat: 0,
          view: view({
            declared: [[], [terz], [], []],
            log: [{ type: 'declare', seat: 1, ...terz }],
          }) as never,
          legal_actions: legal,
        })}
        {...props}
      />,
    )
    const toast = screen.getByRole('status', {
      name: 'Aram · Terz +20 · declared · 7 of clubs, 8 of clubs, 9 of clubs',
    })
    expect(within(toast).getByText('Aram')).toBeInTheDocument()
    // The cards themselves (read out by the announcement's label, so hidden from screen readers here).
    expect(
      [...toast.querySelectorAll('[role="img"]')].map((c) => c.getAttribute('aria-label')),
    ).toEqual(['7 of clubs', '8 of clubs', '9 of clubs'])
    await userEvent.click(within(toast).getByRole('button', { name: 'Close the announcement' }))
    expect(screen.queryByRole('status', { name: /Terz/ })).not.toBeInTheDocument()
    // The cards stay at the player's seat, and the deal's events in the ticker.
    expect(
      screen.getByLabelText('Terz +20: 7 of clubs, 8 of clubs, 9 of clubs'),
    ).toBeInTheDocument()
    expect(screen.getAllByText('Aram: Terz +20 — declared').length).toBeGreaterThan(0)
  })

  it('announces Blot to everyone, but leaves a pass or a trick to the ticker', () => {
    const props = { onAction: vi.fn(), pending: false, players }
    const { rerender } = render(
      <BlotBoard
        state={roomStateFixture({ seat: 0, view: view() as never, legal_actions: [] })}
        {...props}
      />,
    )
    const show = (log: BlotView['log']) =>
      rerender(
        <BlotBoard
          state={roomStateFixture({ seat: 0, view: view({ log }) as never, legal_actions: [] })}
          {...props}
        />,
      )

    show([{ type: 'blot', seat: 3, card: 'KH' }])
    expect(
      screen.getByRole('status', { name: /^Levon · Blot · king and queen of koz/ }),
    ).toBeInTheDocument()

    show([
      { type: 'blot', seat: 3, card: 'KH' },
      { type: 'trick', seat: 2, points: 14, last: false },
      { type: 'pass', seat: 1 },
    ])
    expect(screen.getAllByText('Aram: Pass').length).toBeGreaterThan(0)
    expect(screen.queryByRole('status', { name: /Pass/ })).not.toBeInTheDocument()
  })

  it("doesn't announce again what was declared before the page opened", () => {
    const declared = [
      [],
      [],
      [{ kind: 'hundred' as const, points: 100, cards: ['8D', '9D', 'TD', 'JD', 'QD'] }],
      [],
    ]
    renderBoard(view({ declared }), [])

    expect(screen.queryByRole('status', { name: /Sotni/ })).not.toBeInTheDocument()
    expect(screen.getByLabelText(/^Sotni \+100: 8 of diamonds/)).toBeInTheDocument()
  })

  it('shows declarations going to the defenders when the contract fails', () => {
    renderBoard(
      view({
        phase: 'over',
        to_act: null,
        hand: [],
        last_hand: {
          number: 1,
          contract: contract({ seat: 2, team: 0, value: 10, suit: 'C' }),
          made: false,
          tricks: [4, 4],
          card_points: [73, 89],
          card_sai: [7, 9],
          declarations: [
            { seat: 2, kind: 'terz', points: 20, cards: ['7C', '8C', '9C'], counted: true },
          ],
          declaration_sai: [2, 0],
          belote_sai: [0, 0],
          bid_sai: 10,
          scored: [0, 28],
          totals: [0, 28],
          kaput: false,
        },
      }),
      [],
    )

    const result = screen.getByRole('region', { name: 'Deal result' })
    const decl = within(result).getByRole('row', { name: /Declarations/ })
    expect(
      within(decl)
        .getAllByRole('cell')
        .map((c) => c.textContent),
    ).toEqual(['0', '2'])
    const deal = within(result).getByRole('row', { name: /This deal/ })
    expect(
      within(deal)
        .getAllByRole('cell')
        .map((c) => c.textContent),
    ).toEqual(['0', '28'])
  })

  it('deals card by card from the deck while the server holds the deal, then opens the bazar', () => {
    vi.useFakeTimers()
    try {
      const dealt = view({ phase: 'dealing', contract: null, trump: null, tricks: [0, 0] })
      const { rerender } = renderBoard(dealt, [])
      // The closed deck is in the middle; nothing in hand yet, nothing to press.
      expect(screen.getByRole('status', { name: 'Dealing' })).toBeInTheDocument()
      const hand = () =>
        within(screen.getByRole('region', { name: 'Your cards' })).queryAllByRole('button').length
      expect(hand()).toBe(0)
      // The dealer is seat 3, so you (seat 0) get the first three; they land after their flight.
      void act(() => vi.advanceTimersByTime(300))
      expect(hand()).toBe(0)
      void act(() => vi.advanceTimersByTime(500))
      expect(hand()).toBe(3)
      void act(() => vi.advanceTimersByTime(3000))
      expect(hand()).toBe(8)
      expect(screen.queryByRole('button', { name: 'Pass' })).not.toBeInTheDocument()
      // The server ends the deal: the bazar opens.
      rerender(
        <BlotBoard
          state={roomStateFixture({
            seat: 0,
            view: { ...dealt, phase: 'bidding' },
            legal_actions: [{ type: 'bid', min: 8, max: 24 }, { type: 'pass' }],
          })}
          onAction={vi.fn()}
          pending={false}
          players={players}
        />,
      )
      expect(screen.queryByRole('status', { name: 'Dealing' })).not.toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Pass' })).toBeInTheDocument()
    } finally {
      vi.useRealTimers()
    }
  })

  it('announces when a kaput can no longer be made', () => {
    const props = { onAction: vi.fn(), pending: false, players }
    const { rerender } = render(
      <BlotBoard
        state={roomStateFixture({ seat: 0, view: view() as never, legal_actions: [] })}
        {...props}
      />,
    )
    rerender(
      <BlotBoard
        state={roomStateFixture({
          seat: 0,
          view: view({ log: [{ type: 'kaput_failed', seat: 1 }] }) as never,
          legal_actions: [],
        })}
        {...props}
      />,
    )
    expect(
      screen.getByRole('status', { name: /Kaput failed · Aram took a trick/ }),
    ).toBeInTheDocument()
  })
})
