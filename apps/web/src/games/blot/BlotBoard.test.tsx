import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import BlotBoard, { type BlotView } from './BlotBoard'

function view(extra: Partial<BlotView> = {}): BlotView {
  return {
    seat: 0,
    dealer: 3,
    hand_no: 1,
    phase: 'playing',
    hand: ['7S', 'AH', 'JH', 'KD', '9C', 'TS', 'QH', '8D'],
    hand_sizes: [8, 8, 8, 8],
    bids: [],
    contract: { seat: 1, team: 1, value: 9, suit: 'H', doubled: 0 },
    trump: 'H',
    to_act: 0,
    trick: [],
    last_trick: null,
    tricks: [0, 0],
    points: [0, 0],
    declarations: [[], [], [], []],
    declarations_shown: false,
    belote: null,
    scores: [120, 40],
    target: 301,
    last_hand: null,
    ...extra,
  }
}

const players = { 0: 'alice', 1: 'Aram', 2: 'bob', 3: 'Levon' }

describe('BlotBoard', () => {
  it('lets you play only the cards the server allows', async () => {
    const onAction = vi.fn()
    render(
      <BlotBoard
        state={roomStateFixture({
          seat: 0,
          view: view() as never,
          legal_actions: [
            { type: 'play', card: 'AH' },
            { type: 'play', card: 'JH' },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    const hand = screen.getByRole('region', { name: 'Your cards' })
    expect(within(hand).getByRole('button', { name: '7 of spades' })).toBeDisabled()
    await userEvent.click(within(hand).getByRole('button', { name: 'Jack of hearts' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'play', card: 'JH' })
    expect(screen.getByText('Contract')).toBeInTheDocument()
    expect(screen.getByText('120')).toBeInTheDocument()
    expect(screen.getByText('Us')).toBeInTheDocument()
  })

  it('bids a value in a suit, passes and doubles', async () => {
    const onAction = vi.fn()
    render(
      <BlotBoard
        state={roomStateFixture({
          seat: 0,
          view: view({
            phase: 'bidding',
            contract: { seat: 3, team: 1, value: 9, suit: 'S', doubled: 0 },
            trump: null,
            bids: [{ seat: 3, type: 'bid', value: 9, suit: 'S' }],
          }) as never,
          legal_actions: [{ type: 'bid', min: 10, max: 30 }, { type: 'pass' }, { type: 'double' }],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Higher bid' }))
    await userEvent.click(screen.getByRole('button', { name: 'Bid 11 in hearts' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'bid', value: 11, suit: 'H' })
    await userEvent.click(screen.getByRole('button', { name: 'Contra' }))
    expect(onAction).toHaveBeenLastCalledWith({ type: 'double' })
    expect(screen.getByRole('button', { name: 'Pass' })).toBeEnabled()
  })

  it('shows the hand result from your team’s side', () => {
    render(
      <BlotBoard
        state={roomStateFixture({
          seat: 1,
          view: view({
            seat: 1,
            phase: 'over',
            to_act: null,
            hand: [],
            last_hand: {
              number: 1,
              contract: { seat: 1, team: 1, value: 9, suit: 'H', doubled: 0 },
              made: true,
              tricks: [52, 110],
              declarations: [0, 20],
              belote: [0, 20],
              scored: [52, 150],
              kaput: false,
            },
          }) as never,
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )

    const result = screen.getByRole('region', { name: 'Hand result' })
    expect(within(result).getByText('You made 9')).toBeInTheDocument()
    const scored = within(result).getByRole('row', { name: /Scored/ })
    expect(
      within(scored)
        .getAllByRole('cell')
        .map((c) => c.textContent),
    ).toEqual(['150', '52'])
  })
})
