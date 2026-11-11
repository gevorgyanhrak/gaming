import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import BlotBoard, { type BlotView } from './BlotBoard'

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
    expect(within(score).getByText('301')).toBeInTheDocument()
    expect(screen.getByText('Tricks: us 0 · them 0')).toBeInTheDocument()
  })

  it('bids a number in a suit or no-trump, kaput, and contra', async () => {
    const onAction = vi.fn()
    renderBoard(
      view({
        phase: 'bidding',
        contract: contract({ seat: 3, suit: 'S' }),
        trump: null,
        bids: [{ seat: 3, type: 'bid', value: 9, suit: 'S' }],
      }),
      [
        { type: 'bid', min: 10, max: 24 },
        ...['S', 'H', 'D', 'C', 'N'].map((suit) => ({ type: 'kaput', suit })),
        { type: 'pass' },
        { type: 'contra' },
      ],
      onAction,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Higher bid' }))
    await userEvent.click(screen.getByRole('button', { name: 'Bid 11 in no-trump' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'bid', value: 11, suit: 'N' })

    await userEvent.click(screen.getByRole('button', { name: 'Bid kaput…' }))
    await userEvent.click(screen.getByRole('button', { name: 'Bid kaput in hearts' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'kaput', suit: 'H' })

    await userEvent.click(screen.getByRole('button', { name: 'Contra' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'contra' })
  })

  it('answers a contra with recontra or pass', async () => {
    const onAction = vi.fn()
    renderBoard(
      view({ phase: 'answer', contract: contract({ seat: 2, team: 0, doubled: 1 }) }),
      [{ type: 'recontra' }, { type: 'pass' }],
      onAction,
    )

    expect(screen.queryByRole('button', { name: 'Higher bid' })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Recontra' }))
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
    expect(within(panel).getByText('✓ 50 50')).toBeInTheDocument()
    expect(screen.getByText('Declare your combinations, then play a card')).toBeInTheDocument()
    await userEvent.click(within(panel).getByRole('button', { name: /Declare Terz 20/ }))
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
    expect(within(result).getByText('You: Terz 20')).toBeInTheDocument()
  })
})
