import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import PokerBoard from './PokerBoard'
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
        bots={{ 0: true, 1: false, 2: false }}
      />,
    )

    expect(screen.getByText('Aram')).toBeInTheDocument()
    expect(screen.getByRole('listitem', { name: /^Aram, .*, Bot$/ })).toHaveTextContent('Bot')
    expect(screen.getByRole('listitem', { name: /^Levon, / })).not.toHaveTextContent('Bot')
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
      expect(screen.getByRole('img', { name: 'Jack of clubs' })).toBeInTheDocument()
      expect(screen.queryByText(/^Pot:/)).not.toBeInTheDocument()
      expect(screen.getByRole('status')).toHaveTextContent(
        'Hand 4: You win 150 chips — with a pair',
      )
      expect(screen.getByText(/^Next hand in \d+ seconds?$/)).toBeInTheDocument()
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
})
