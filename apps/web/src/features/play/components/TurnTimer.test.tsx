import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { TurnTimer } from './TurnTimer'

describe('TurnTimer', () => {
  it('shows the seconds in a ring that drains with the turn', () => {
    const { container } = render(<TurnTimer seconds={30} total={60} mine />)
    const timer = screen.getByRole('timer', { name: '30 seconds left' })
    expect(timer).toHaveTextContent('30')
    const ring = container.querySelectorAll('circle')[1]!
    const length = Number(ring.getAttribute('stroke-dasharray'))
    expect(Number(ring.getAttribute('stroke-dashoffset'))).toBeCloseTo(length / 2, 1)
    expect(timer.className).not.toContain('timer-tick')
  })

  it('warns in red from 10 seconds and ticks in the last 5', () => {
    const { rerender } = render(<TurnTimer seconds={9} total={60} mine={false} />)
    expect(screen.getByText('9')).toHaveClass('text-danger')
    expect(screen.getByRole('timer').className).not.toContain('timer-tick')

    rerender(<TurnTimer seconds={4} total={60} mine={false} />)
    expect(screen.getByRole('timer').className).toContain('timer-tick')
  })
})
