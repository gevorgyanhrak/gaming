import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render as baseRender, screen, within } from '@testing-library/react'
import type { ReactNode } from 'react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import PokerBoard from './PokerBoard'
import { clearBanners, DURATION, useBanner } from '@/features/play/banners/store'
import { act, renderHook } from '@testing-library/react'

// The board refreshes the wallet after a hand, so it needs a query client.
const render = (ui: ReactNode) =>
  baseRender(ui, {
    wrapper: ({ children }) => (
      <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
    ),
  })
import { summarize } from './result'
import i18n from '@/shared/i18n'

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
    expect(screen.getByRole('listitem', { name: /^Aram, / })).not.toHaveTextContent('Bot')
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

    // More: the slider's presets, then the confirmation.
    await userEvent.click(screen.getByRole('button', { name: 'More' }))
    expect(screen.getByRole('button', { name: 'Less' })).toHaveAttribute('aria-expanded', 'true')
    await userEvent.click(screen.getByRole('button', { name: 'Pot' }))
    await userEvent.click(screen.getByRole('button', { name: 'Confirm 35' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'raise', amount: 35 })

    await userEvent.click(screen.getByRole('button', { name: 'All-in 1,000' }))
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

    // Whose turn it is: the acting player under "Thinking time".
    expect(screen.getByText('Thinking time')).toBeInTheDocument()
    expect(screen.getAllByText('Aram').length).toBeGreaterThan(1)
    expect(screen.getByText(/You won 200 chips — a pair/)).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Queen of diamonds' })).toBeInTheDocument()
  })

  describe('the end of a hand', () => {
    const won = {
      number: 4,
      board: ['2C', '7H', '9D', 'JC', '3S'],
      shown: { 0: ['QS', 'QD'], 2: ['AS', 'AD'] },
      pots: [{ amount: 150, winners: [2], hand: 'pair' }],
      payouts: [0, 0, 150],
    }
    const paused = (lastHand: object) =>
      roomStateFixture({
        seat: 2,
        seats_to_act: [],
        version: 9,
        deadline: '2026-10-27T10:00:04+00:00',
        view: view({ street: 'over', to_act: null, bets: [0, 0, 0], pot: 0, last_hand: lastHand }),
        legal_actions: [],
      })
    const board = (state: ReturnType<typeof roomStateFixture>) => (
      <PokerBoard state={state} onAction={vi.fn()} pending={false} players={players} />
    )

    it('shows who won, how much and with what, when the hand ends at the table', () => {
      const { rerender } = render(
        board(roomStateFixture({ seat: 2, seats_to_act: [0], view: view({ to_act: 0 }) })),
      )
      expect(screen.getByRole('status')).toBeEmptyDOMElement()

      rerender(board(paused(won)))

      expect(screen.getByText('You win 150 chips')).toBeInTheDocument()
      expect(screen.getByText('with a pair')).toBeInTheDocument()
      const me = screen.getByRole('listitem', { name: /^You, / })
      expect(within(me).getByText('+150')).toBeInTheDocument()
      // Cards shown down turn face up at their seats; the board stays.
      expect(
        within(screen.getByRole('listitem', { name: /^Aram, / })).getByRole('img', {
          name: 'Queen of spades',
        }),
      ).toBeInTheDocument()
      expect(
        within(screen.getByRole('region', { name: 'Board' })).getByRole('img', {
          name: 'Jack of clubs',
        }),
      ).toBeInTheDocument()
      expect(screen.queryByText(/^Pot:/)).not.toBeInTheDocument()
      expect(screen.getByRole('status')).toHaveTextContent(
        'Hand 4: You win 150 chips — with a pair',
      )
      expect(document.querySelector('[data-turn="next"]')).toHaveTextContent(/^Next hand/)
    })

    it('names another winner, and a fold win shows no cards', () => {
      render(
        board(
          paused({
            number: 4,
            board: [],
            shown: {},
            pots: [{ amount: 25, winners: [1], hand: null }],
            payouts: [0, 25, 0],
          }),
        ),
      )

      const table = screen.getByRole('region', { name: 'Board' })
      expect(within(table).getByText('Levon won 25 chips')).toBeInTheDocument()
      expect(within(table).getByText('Everyone else folded')).toBeInTheDocument()
      expect(
        within(screen.getByRole('listitem', { name: /^Levon, / })).getByText('+25'),
      ).toBeInTheDocument()
      expect(screen.queryByRole('img', { name: 'Queen of spades' })).not.toBeInTheDocument()
    })

    it('shows a result that was already up on load without announcing it again', () => {
      render(board(paused(won)))

      expect(screen.getByText('You win 150 chips')).toBeInTheDocument()
      expect(screen.getByRole('status')).toBeEmptyDOMElement()
    })

    it('goes back to the pot once the next hand is dealt', () => {
      render(
        board(
          roomStateFixture({
            seat: 2,
            seats_to_act: [0],
            view: view({ to_act: 0, last_hand: won }),
          }),
        ),
      )

      expect(screen.getByText('Pot: 15 chips')).toBeInTheDocument()
      expect(screen.queryByText('You win 150 chips')).not.toBeInTheDocument()
    })
  })
})

describe('summarize', () => {
  const t = i18n.getFixedT('en')
  const name = (seat: number) => ['Aram', 'Levon', 'You'][seat]!

  it('keeps the hands seen at the table, newest first, the earlier ones folded away', () => {
    const hand = (number: number) => ({
      number,
      board: ['2C', '7D', '9H'],
      shown: {},
      pots: [{ amount: 20 * number, winners: [0], hand: null }],
    })
    const at = (number: number) =>
      roomStateFixture({
        seat: 2,
        seats_to_act: [0],
        view: view({ hand: number + 1, to_act: 0, last_hand: hand(number) }),
      })
    const { rerender } = render(
      <PokerBoard state={at(1)} onAction={vi.fn()} pending={false} players={players} />,
    )
    rerender(<PokerBoard state={at(2)} onAction={vi.fn()} pending={false} players={players} />)
    rerender(<PokerBoard state={at(3)} onAction={vi.fn()} pending={false} players={players} />)

    const history = screen.getByRole('region', { name: 'Last hand' })
    expect(
      within(history)
        .getAllByRole('heading')
        .map((h) => h.textContent),
    ).toEqual([
      expect.stringContaining('Hand 3'),
      expect.stringContaining('Hand 2'),
      expect.stringContaining('Hand 1'),
    ])
    expect(within(history).getByText('Earlier hands (2)')).toBeInTheDocument()
    expect(within(history).getByText('Aram won 60 chips')).toBeInTheDocument()
  })

  it('splits a pot, giving each winner a number only when it is exact', () => {
    const split = {
      number: 1,
      board: [],
      shown: {},
      pots: [{ amount: 151, winners: [0, 1], hand: 'flush' }],
    }

    const odd = summarize(split, 2, name, t)
    expect(odd.headline).toBe('Split pot: Aram, Levon — 75 chips each')
    expect([...odd.winners]).toEqual([
      [0, null],
      [1, null],
    ])

    const paid = summarize({ ...split, payouts: [76, 75, 0] }, 2, name, t)
    expect([...paid.winners]).toEqual([
      [0, 76],
      [1, 75],
    ])
    expect(paid.headline).toBe('Split pot: Aram 76, Levon 75')

    const even = summarize(
      { ...split, pots: [{ amount: 150, winners: [0, 1], hand: 'flush' }], payouts: [75, 75, 0] },
      2,
      name,
      t,
    )
    expect(even.headline).toBe('Split pot: Aram, Levon — 75 chips each')
  })

  it('leads with the main pot and lists side pots', () => {
    const result = summarize(
      {
        number: 1,
        board: [],
        shown: {},
        pots: [
          { amount: 300, winners: [0], hand: 'pair' },
          { amount: 400, winners: [1], hand: 'pair' },
        ],
        payouts: [300, 400, 0],
      },
      2,
      name,
      t,
    )

    expect(result.headline).toBe('Aram won 300 chips')
    expect(result.details).toEqual(['with a pair', 'Side pot · Levon · 400 chips'])
    expect(result.mine).toBe(false)
  })

  it('deals a new hand one card at a time round the table, then the flop face up card by card', () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    vi.stubGlobal('matchMedia', (q: string) => ({
      matches: false,
      media: q,
      addEventListener() {},
      removeEventListener() {},
    }))
    const dealt: string[] = []
    const animate = vi.fn(function (
      this: HTMLElement,
      _k: Keyframe[],
      o: KeyframeAnimationOptions,
    ) {
      if (o.id === 'deal') {
        dealt.push(
          this.getAttribute('data-poker-hole') ?? `board-${this.getAttribute('data-poker-board')}`,
        )
      }
      return { id: o.id } as unknown as Animation
    })
    HTMLElement.prototype.animate = animate
    HTMLElement.prototype.getAnimations = () => []
    const board = (extra: object) => (
      <PokerBoard
        state={roomStateFixture({ seat: 2, seats_to_act: [0], view: view(extra) })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />
    )

    const { rerender } = render(board({}))
    vi.advanceTimersByTime(500)
    expect(dealt).toEqual([])

    // Hand 4, the button on seat 2: seat 0 first, twice round.
    rerender(board({ hand: 4 }))
    expect(dealt).toEqual(['0-0', '1-0', '2-0', '0-1', '1-1', '2-1'])

    dealt.length = 0
    rerender(board({ hand: 4, street: 'flop', board: ['2C', '7D', 'JH'] }))
    expect(dealt).toEqual(['board-0', 'board-1', 'board-2'])

    vi.unstubAllGlobals()
    vi.useRealTimers()
  })

  it('calls an all-in, lights the seat, and announces the winner of a hand that ends while watching', () => {
    // The dealing test above leaves its animate() stand-in behind; this one runs without animations.
    Reflect.deleteProperty(HTMLElement.prototype, 'animate')
    clearBanners()
    const banner = renderHook(() => useBanner())
    const legal = { legal_actions: [], seats_to_act: [] }
    const { rerender } = render(
      <PokerBoard
        state={roomStateFixture({ seat: 2, ...legal, view: view({ hand: 3 }) })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )
    // Aram pushes his whole 990.
    rerender(
      <PokerBoard
        state={roomStateFixture({
          seat: 2,
          ...legal,
          view: view({
            hand: 3,
            stacks: [0, 995, 1000],
            bets: [1000, 5, 0],
            current_bet: 1000,
            pot: 1005,
          }),
        })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )
    expect(banner.result.current).toMatchObject({
      kind: 'allIn',
      title: 'All-in',
      detail: 'Aram · 1,000',
    })

    // The hand ends: Levon takes it.
    rerender(
      <PokerBoard
        state={roomStateFixture({
          seat: 2,
          ...legal,
          view: view({
            hand: 3,
            street: 'over',
            stacks: [0, 2000, 1000],
            bets: [0, 0, 0],
            folded: [false, false, true],
            last_hand: {
              number: 3,
              board: ['2C', '3D', '9H', 'JS', 'QD'],
              shown: { 0: ['4C', '5C'], 1: ['QS', 'QH'] },
              pots: [{ amount: 2000, winners: [1], hand: 'three_of_a_kind' }],
              payouts: [0, 2000, 0],
            },
          }),
        })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )
    // The all-in banner is still up; the winner's waits for it (one at a time). Folding gets no "lost" banner.
    expect(banner.result.current?.kind).toBe('allIn')
    clearBanners()
  })

  it('says quietly that you lost a hand you played to the showdown, with what it cost', () => {
    Reflect.deleteProperty(HTMLElement.prototype, 'animate')
    vi.useFakeTimers()
    clearBanners()
    const seen: string[] = []
    const banner = renderHook(() => {
      const current = useBanner()
      if (current && !seen.includes(current.kind)) seen.push(current.kind)
      return current
    })
    const board = (extra: object) => (
      <PokerBoard
        state={roomStateFixture({
          seat: 2,
          legal_actions: [],
          seats_to_act: [],
          view: view(extra),
        })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />
    )
    // Hand 3 opens pre-flop with you on 1,000.
    const { rerender } = render(board({ hand: 3 }))
    rerender(
      board({
        hand: 3,
        street: 'over',
        stacks: [990, 1600, 400],
        bets: [0, 0, 0],
        folded: [true, false, false],
        last_hand: {
          number: 3,
          board: ['2C', '3D', '9H', 'JS', 'QD'],
          shown: { 1: ['QS', 'QH'], 2: ['AS', 'KH'] },
          pots: [{ amount: 1200, winners: [1], hand: 'three_of_a_kind' }],
          payouts: [0, 1200, 0],
        },
      }),
    )
    expect(banner.result.current).toMatchObject({ kind: 'winner', detail: 'Levon · +1,200' })
    // Then, quietly, what it cost you: 1,000 at the start, 400 now.
    act(() => {
      vi.advanceTimersByTime(DURATION.winner)
    })
    expect(banner.result.current).toMatchObject({
      kind: 'lost',
      title: 'Hand lost',
      detail: '−600',
    })
    clearBanners()
    vi.useRealTimers()
    expect(seen).toEqual(['winner', 'lost'])
  })
})
