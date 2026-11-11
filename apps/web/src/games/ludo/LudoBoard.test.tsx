import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { roomStateFixture } from '@/test/handlers'
import LudoBoard, { type LudoView } from './LudoBoard'

function view(extra: Partial<LudoView> = {}): LudoView {
  return {
    seat: 0,
    colours: [0, 2],
    tokens: [
      [-1, 12, -1, -1],
      [-1, -1, -1, 56],
    ],
    out: [false, false],
    to_act: 0,
    phase: 'roll',
    dice: null,
    sixes: 0,
    last: null,
    turn: 3,
    ...extra,
  }
}

const players = { 0: 'alice', 1: 'Levon' }

describe('LudoBoard', () => {
  it('rolls the die when the server offers it', async () => {
    const onAction = vi.fn()
    render(
      <LudoBoard
        state={roomStateFixture({
          seat: 0,
          view: view() as never,
          legal_actions: [{ type: 'roll' }],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText('Your turn: roll the die')).toBeInTheDocument()
    expect(screen.getByText('1/4 home')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Roll the die' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'roll' })
  })

  it('moves only the tokens the server lists', async () => {
    const onAction = vi.fn()
    render(
      <LudoBoard
        state={roomStateFixture({
          seat: 0,
          view: view({ phase: 'move', dice: 6 }) as never,
          legal_actions: [
            { type: 'move', token: 0, to: 0 },
            { type: 'move', token: 1, to: 18 },
          ],
        })}
        onAction={onAction}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByRole('img', { name: 'Rolled 6' })).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: /^Move red token/ })).toHaveLength(2)
    await userEvent.click(screen.getByRole('button', { name: 'Move red token 2' }))
    expect(onAction).toHaveBeenCalledWith({ type: 'move', token: 1, to: 18 })
  })

  it('waits for the other player', () => {
    render(
      <LudoBoard
        state={roomStateFixture({ seat: 0, view: view({ to_act: 1 }) as never, legal_actions: [] })}
        onAction={vi.fn()}
        pending={false}
        players={players}
      />,
    )

    expect(screen.getByText('Waiting for Levon…')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Roll the die' })).not.toBeInTheDocument()
  })
})
