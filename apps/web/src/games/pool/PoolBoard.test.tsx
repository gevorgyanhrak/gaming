import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import PoolBoard, { type PoolView } from './PoolBoard'

function view(extra: Partial<PoolView> = {}): PoolView {
  const balls: (Readonly<[number, number]> | null)[] = Array.from({ length: 16 }, () => null)
  balls[0] = [635, 635]
  balls[3] = [1500, 600]
  balls[8] = [1905, 635]
  balls[11] = [2000, 300]
  return {
    seat: 0,
    balls: balls as PoolView['balls'],
    groups: ['solids', 'stripes'],
    to_act: 0,
    phase: 'aim',
    ball_in_hand: false,
    broken: true,
    shot: 4,
    last_shot: null,
    ...extra,
  }
}

const players = { 0: 'alice', 1: 'Levon' }

describe('PoolBoard', () => {
  it('shoots along the aim with the chosen power', async () => {
    const onAction = vi.fn()
    render(
      <PoolBoard
        state={roomStateFixture({
          seat: 0,
          view: view() as never,
          legal_actions: [{ type: 'shoot', ball_in_hand: false, min: 1, max: 100 }],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    fireEvent.change(screen.getByRole('slider'), { target: { value: '80' } })
    await userEvent.click(screen.getByRole('button', { name: 'Turn the aim right' }))
    await userEvent.click(screen.getByRole('button', { name: 'Shoot' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'shoot', angle: 0.5, power: 80 })
  })

  it('places the cue ball first when it is in hand, and sends where', async () => {
    const onAction = vi.fn()
    const state = view({ ball_in_hand: true, shot: 5 })
    render(
      <PoolBoard
        state={roomStateFixture({
          seat: 0,
          view: state as never,
          legal_actions: [{ type: 'shoot', ball_in_hand: true, min: 1, max: 100 }],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText(/Ball in hand/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Shoot' })).toBeDisabled()
    // Keep the cue ball where it is (stop placing) and shoot.
    await userEvent.click(screen.getByRole('button', { name: 'Move the cue ball' }))
    await userEvent.click(screen.getByRole('button', { name: 'Shoot' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'shoot', angle: 0, power: 60, x: 635, y: 635 })
  })

  it('shows groups and the last foul, and waits for the opponent', () => {
    render(
      <PoolBoard
        state={roomStateFixture({
          seat: 0,
          view: view({
            to_act: 1,
            ball_in_hand: true,
            last_shot: {
              number: 4,
              seat: 0,
              potted: [0],
              foul: 'scratch',
              duration_ms: 1200,
              segments: {},
            },
          }) as never,
          legal_actions: [],
        })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText('Solids')).toBeInTheDocument()
    expect(screen.getByText(/Foul: the cue ball went in/)).toBeInTheDocument()
    expect(screen.getByText('Waiting for Levon…')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Shoot' })).not.toBeInTheDocument()
  })
})
