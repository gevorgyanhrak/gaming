import { render } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { themes } from '@/games/themes'
import { GameBackdrop } from './GameBackdrop'

describe('GameBackdrop', () => {
  it('puts each game in its own room, behind the page', () => {
    const { container } = render(<GameBackdrop slug="blot" />)
    const layer = container.querySelector<HTMLElement>('[data-game-theme="blot"]')

    expect(layer).not.toBeNull()
    expect(layer?.getAttribute('aria-hidden')).toBe('true')
    expect(layer?.className).toContain('-z-10')
    expect(layer?.style.backgroundImage).toContain('linear-gradient')
  })

  it('gives no two games the same picture', () => {
    const images = ['blot', 'poker', 'checkers', 'chess', 'nardi', 'durak'].map(
      (slug) => themes[slug]?.image,
    )

    expect(images.every(Boolean)).toBe(true)
    expect(new Set(images).size).toBe(images.length)
  })

  it('leaves a game without a theme in the club room', () => {
    const { container } = render(<GameBackdrop slug="tictactoe" />)

    expect(container.firstChild).toBeNull()
  })
})
