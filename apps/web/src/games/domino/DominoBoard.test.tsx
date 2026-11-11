import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import DominoBoard, { type DominoView } from './DominoBoard'

function view(extra: Partial<DominoView> = {}): DominoView {
  return {
    seat: 0,
    target: 100,
    scores: [12, 30],
    out: [false, false],
    round: 2,
    phase: 'playing',
    hand: ['3-5', '4-4', '1-6'],
    hand_sizes: [3, 5],
    line: [{ tile: [3, 4], seat: 1 }],
    ends: [3, 4],
    to_act: 0,
    last_round: null,
    ...extra,
  }
}

const players = { 0: 'alice', 1: 'Levon' }

describe('DominoBoard', () => {
  it('plays a tile that fits one end at once', async () => {
    const onAction = vi.fn()
    render(
      <DominoBoard
        state={roomStateFixture({
          seat: 0,
          view: view() as never,
          legal_actions: [
            { type: 'play', tile: '3-5', end: 'left' },
            { type: 'play', tile: '4-4', end: 'right' },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    const tiles = screen.getByRole('region', { name: 'Your tiles' })
    expect(within(tiles).getByRole('button', { name: '1–6 tile' })).toBeDisabled()
    await userEvent.click(within(tiles).getByRole('button', { name: '4–4 tile' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'play', tile: '4-4', end: 'right' })
  })

  it('asks for the end when a tile fits both', async () => {
    const onAction = vi.fn()
    render(
      <DominoBoard
        state={roomStateFixture({
          seat: 0,
          view: view({ hand: ['3-4'], ends: [3, 4] }) as never,
          legal_actions: [
            { type: 'play', tile: '3-4', end: 'left' },
            { type: 'play', tile: '3-4', end: 'right' },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: '3–4 tile' }))
    expect(onAction).not.toHaveBeenCalled()
    await userEvent.click(screen.getByRole('button', { name: 'Here ▶' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'play', tile: '3-4', end: 'right' })
  })

  it('shows who scored and the tiles left after a round', () => {
    render(
      <DominoBoard
        state={roomStateFixture({
          seat: 0,
          view: view({
            phase: 'over',
            to_act: null,
            hand: [],
            last_round: {
              number: 2,
              winner: 1,
              points: 9,
              blocked: true,
              pips: { 0: 9, 1: 2 },
              hands: [['4-5'], ['1-1']],
            },
          }) as never,
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText('Blocked — Levon has the fewest pips: +9')).toBeInTheDocument()
    expect(screen.getByText('9 pips')).toBeInTheDocument()
    expect(screen.getByText('Next round')).toBeInTheDocument()
  })
})
