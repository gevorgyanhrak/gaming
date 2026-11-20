import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Avatar } from './Avatar'
import { AiSeatMark } from './AiSeatMark'

describe('Avatar', () => {
  it('marks an AI player as AI — ring and chip — with or without an uploaded picture', () => {
    const { container, rerender } = render(<Avatar url={null} name="Aram" ai />)
    expect(container.querySelector('.ai-ring')).not.toBeNull()
    expect(screen.getByText('AI')).toBeInTheDocument()

    rerender(<Avatar url="/storage/avatars/bots/aram.webp" name="Aram" ai />)
    expect(container.querySelector('img.ai-ring')).not.toBeNull()
    expect(screen.getByText('AI')).toBeInTheDocument()
  })

  it('never marks a person', () => {
    const { container } = render(<Avatar url={null} name="Ani" />)
    expect(container.querySelector('.ai-ring')).toBeNull()
    expect(screen.queryByText('AI')).not.toBeInTheDocument()
  })

  it('gives a board’s own seat the same AI look', () => {
    const { container, rerender } = render(<AiSeatMark face="Aram" />)
    expect(container.querySelector('.ai-ring')).not.toBeNull()
    // The seat's name already says "· AI"; the tag only where asked.
    expect(screen.queryByText('AI')).not.toBeInTheDocument()
    rerender(<AiSeatMark face="Aram" tag />)
    expect(screen.getByText('AI')).toBeInTheDocument()
  })
})
