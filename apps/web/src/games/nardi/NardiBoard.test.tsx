import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import NardiBoard from './NardiBoard'

const start = [-2, 0, 0, 0, 0, 5, 0, 3, 0, 0, 0, -5, 5, 0, 0, 0, -3, 0, -5, 0, 0, 0, 0, 2]

function state(seat: number, legal: object[], toAct = seat) {
  return roomStateFixture({
    seat,
    seats_to_act: [toAct],
    view: {
      points: start,
      bar: [0, 0],
      off: [0, 0],
      turn: seat,
      dice: [6, 1],
      rolled: [6, 1],
      winner: null,
    },
    legal_actions: legal,
  })
}

describe('NardiBoard', () => {
  it('offers only the server’s legal steps: pick a checker, then a highlighted target', async () => {
    const onAction = vi.fn()
    const legal = [
      { type: 'move', from: 24, to: 18, die: 6 },
      { type: 'move', from: 13, to: 7, die: 6 },
      { type: 'move', from: 8, to: 7, die: 1 },
    ]
    render(<NardiBoard state={state(0, legal)} onAction={onAction} pending={false} />)

    expect(screen.getByRole('button', { name: /Point 6: 5 checkers, yours/ })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: /Point 13: 5 checkers, yours/ }))
    expect(screen.getByText('Pick where it goes.')).toBeInTheDocument()

    // Point 18 is a target of 24, not of 13.
    expect(screen.getByRole('button', { name: /Point 18:/ })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: /Point 7:/ }))
    expect(onAction).toHaveBeenCalledWith({ type: 'move', from: 13, to: 7, die: 6 })
  })

  it('numbers points from the player’s own side for the second seat', () => {
    render(<NardiBoard state={state(1, [], 0)} onAction={vi.fn()} pending={false} />)

    // Absolute point 1 holds seat 1's two back checkers; seat 1 sees it as their point 24.
    expect(screen.getByRole('button', { name: 'Point 24: 2 checkers, yours' })).toBeInTheDocument()
    expect(screen.getByText('Waiting for the other player')).toBeInTheDocument()
  })

  it('prints each point’s number on the board, in the player’s own numbering', () => {
    render(<NardiBoard state={state(1, [], 0)} onAction={vi.fn()} pending={false} />)

    expect(screen.getByRole('button', { name: 'Point 24: 2 checkers, yours' })).toHaveTextContent(
      /^24/,
    )
  })

  it('shows the turn’s time on the board, from the server’s deadline', () => {
    const now = Date.now()
    render(
      <NardiBoard
        state={{
          ...state(0, []),
          server_time: new Date(now).toISOString(),
          deadline: new Date(now + 42_000).toISOString(),
        }}
        onAction={vi.fn()}
        pending={false}
      />,
    )

    expect(screen.getByText('42')).toBeInTheDocument()
  })

  it('dims the die that was played, whichever it was', () => {
    render(
      <NardiBoard
        state={{
          ...state(0, []),
          view: { ...state(0, []).view, rolled: [3, 5], dice: [3] },
        }}
        onAction={vi.fn()}
        pending={false}
      />,
    )

    const dice = screen.getByLabelText('Dice')
    expect(within(dice).getByText('5').parentElement).toHaveClass('opacity-30')
    expect(within(dice).getByText('3').parentElement).not.toHaveClass('opacity-30')
  })

  it('says to enter from the bar when a checker has to', () => {
    render(
      <NardiBoard
        state={state(0, [{ type: 'move', from: 'bar', to: 20, die: 5 }])}
        onAction={vi.fn()}
        pending={false}
      />,
    )

    expect(screen.getByText('Enter your checker from the bar.')).toBeInTheDocument()
  })

  it('draws the timer as a share of the whole turn', () => {
    const now = Date.now()
    const { container } = render(
      <NardiBoard
        state={{
          ...state(0, []),
          turn_seconds: 60,
          server_time: new Date(now).toISOString(),
          deadline: new Date(now + 30_000).toISOString(),
        }}
        onAction={vi.fn()}
        pending={false}
      />,
    )

    const ring = container.querySelector('circle[stroke-dasharray]')
    const whole = Number(ring?.getAttribute('stroke-dasharray'))
    expect(Number(ring?.getAttribute('stroke-dashoffset'))).toBeCloseTo(whole / 2, 0)
  })

  it('bears off through the off tray', async () => {
    const onAction = vi.fn()
    const points = Array(24).fill(0)
    points[0] = 1
    points[23] = -1
    render(
      <NardiBoard
        state={roomStateFixture({
          seat: 0,
          seats_to_act: [0],
          view: {
            points,
            bar: [0, 0],
            off: [14, 14],
            turn: 0,
            dice: [2],
            rolled: [2, 5],
            winner: null,
          },
          legal_actions: [{ type: 'move', from: 1, to: 'off', die: 2 }] as never,
        })}
        onAction={onAction}
        pending={false}
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: /Point 1:/ }))
    await userEvent.click(screen.getByRole('button', { name: /Off/ }))
    expect(onAction).toHaveBeenCalledWith({ type: 'move', from: 1, to: 'off', die: 2 })
  })

  it('shows Long nardi from each player’s side, with pip counts and no bar', () => {
    const points = Array(24).fill(0)
    points[23] = 15 // seat 0's head, point 24
    points[11] = -15 // seat 1's head, point 12
    const view = {
      variant: 'long',
      points,
      bar: [0, 0],
      off: [0, 0],
      turn: 0,
      dice: [5, 2],
      rolled: [5, 2],
      winner: null,
      pips: [360, 360],
    }

    const { unmount } = render(
      <NardiBoard
        state={roomStateFixture({ seat: 0, seats_to_act: [0], view, legal_actions: [] })}
        onAction={vi.fn()}
        pending={false}
      />,
    )
    expect(screen.getByRole('button', { name: 'Point 24: 15 checkers, yours' })).toBeInTheDocument()
    expect(screen.getByLabelText('Pips: you 360, opponent 360')).toBeInTheDocument()
    expect(screen.queryByText('Their bar')).not.toBeInTheDocument()
    unmount()

    // Seat 1's head (point 12) is the start of their way: they see it as their point 24.
    render(
      <NardiBoard
        state={roomStateFixture({ seat: 1, seats_to_act: [0], view, legal_actions: [] })}
        onAction={vi.fn()}
        pending={false}
      />,
    )
    expect(screen.getByRole('button', { name: 'Point 24: 15 checkers, yours' })).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: "Point 12: 15 checkers, opponent's" }),
    ).toBeInTheDocument()
  })
})
