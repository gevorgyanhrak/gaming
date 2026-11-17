import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { BetControls, TurnHeader, type Raise } from './Controls'

const standard: Raise = { type: 'raise', min: 20, max: 300 }

function controls({
  raise = standard,
  actions = ['fold', 'call'],
  callAmount = 10,
  stack = 300,
  opening = false,
}: {
  raise?: Raise | null
  actions?: ('fold' | 'check' | 'call')[]
  callAmount?: number
  stack?: number
  opening?: boolean
} = {}) {
  const onSend = vi.fn()
  render(
    <BetControls
      raise={raise ?? undefined}
      has={(type) => actions.includes(type)}
      callAmount={callAmount}
      stack={stack}
      opening={opening}
      presets={[{ label: 'Pot', value: 45 }]}
      step={10}
      pending={false}
      onSend={onSend}
    />,
  )
  return onSend
}

const amount = () => screen.getByRole('status', { name: 'Bet amount' })

describe('BetControls', () => {
  it('builds the bet with chips: the first tap at least the minimum, the next ones add', async () => {
    const onSend = controls()
    expect(amount()).toHaveTextContent('20')
    expect(amount().nextElementSibling).toHaveTextContent('Min')

    // 10 is under the minimum raise (20): the bet goes to the minimum.
    await userEvent.click(screen.getByRole('button', { name: 'Add 10' }))
    expect(amount()).toHaveTextContent('20')
    await userEvent.click(screen.getByRole('button', { name: 'Add 50' }))
    await userEvent.click(screen.getByRole('button', { name: 'Add 20' }))
    expect(amount()).toHaveTextContent('90')

    await userEvent.click(screen.getByRole('button', { name: 'Raise to 90' }))
    expect(onSend).toHaveBeenLastCalledWith({ type: 'raise', amount: 90 })
  })

  it('stops at the maximum: the bet becomes the all-in and the chips stop', async () => {
    const onSend = controls({ raise: { type: 'raise', min: 20, max: 150 } })
    await userEvent.click(screen.getByRole('button', { name: 'Add 100' }))
    await userEvent.click(screen.getByRole('button', { name: 'Add 100' }))
    expect(amount()).toHaveTextContent('150')
    expect(screen.getByRole('button', { name: 'Add 10' })).toBeDisabled()
    expect(screen.queryByRole('button', { name: /^Raise to/ })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'All-in 150' }))
    expect(onSend).toHaveBeenLastCalledWith({ type: 'raise', amount: 150 })
  })

  it('opens More above the chips: slider between min and max, steps, presets, confirm', async () => {
    const onSend = controls({ opening: true })
    const more = screen.getByRole('button', { name: 'More' })
    expect(more).toHaveAttribute('aria-expanded', 'false')
    await userEvent.click(more)
    expect(more).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByText('300')).toBeInTheDocument()

    const slider = screen.getByRole('slider', { name: 'Bet amount' })
    expect(slider).toHaveAttribute('min', '20')
    expect(slider).toHaveAttribute('max', '300')
    fireEvent.change(slider, { target: { value: '137' } })
    expect(amount()).toHaveTextContent('137')
    await userEvent.click(screen.getByRole('button', { name: 'More by 10' }))
    expect(amount()).toHaveTextContent('147')
    await userEvent.click(screen.getByRole('button', { name: 'Confirm 147' }))
    expect(onSend).toHaveBeenLastCalledWith({ type: 'raise', amount: 147 })
  })

  it('never goes under the minimum with the step down, and Clear returns to it', async () => {
    controls()
    await userEvent.click(screen.getByRole('button', { name: 'More' }))
    expect(screen.getByRole('button', { name: 'Less by 10' })).toBeDisabled()
    await userEvent.click(screen.getByRole('button', { name: 'Add 50' }))
    expect(amount()).toHaveTextContent('50')
    await userEvent.click(screen.getByRole('button', { name: 'Clear' }))
    expect(amount()).toHaveTextContent('20')
  })

  it('shows only the actions the server offers', () => {
    controls({ raise: null, actions: ['check'], callAmount: 0 })
    expect(screen.getByRole('button', { name: 'Check' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Fold' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Call/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^All-in/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^Add/ })).not.toBeInTheDocument()
  })

  it('says Bet when nobody has bet', () => {
    controls({ opening: true })
    expect(screen.getByRole('button', { name: 'Bet 20' })).toBeInTheDocument()
  })

  it('offers only the all-in when the stack is below a full raise, and a call that takes everything says so', () => {
    controls({ raise: { type: 'raise', min: 60, max: 60 }, callAmount: 60, stack: 60 })
    expect(screen.queryByRole('button', { name: /^Add/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'More' })).not.toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'All-in 60' })).toHaveLength(2)
  })
})

describe('TurnHeader', () => {
  it('asks for your action with the time left', () => {
    render(<TurnHeader mode="mine" secondsLeft={12} share={0.4} />)
    expect(screen.getByText('Your turn')).toBeInTheDocument()
    expect(screen.getByText('Action required')).toBeInTheDocument()
    expect(screen.getByText('12s')).toBeInTheDocument()
  })

  it('shows whose thinking time it is, and waiting when nobody acts', () => {
    const { rerender } = render(
      <TurnHeader mode="other" name="Aram · AI" face="Aram" secondsLeft={5} share={0.2} />,
    )
    expect(screen.getByText('Thinking time')).toBeInTheDocument()
    expect(screen.getByText('Aram · AI')).toBeInTheDocument()
    rerender(<TurnHeader mode="waiting" secondsLeft={null} share={null} />)
    expect(document.querySelector('[data-turn="waiting"]')).toHaveTextContent(
      /^Waiting for players/,
    )
  })
})
