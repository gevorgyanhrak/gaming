import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { renderRoute } from '@/test/render'

describe('Sound switch', () => {
  afterEach(() => window.localStorage.clear())

  it('starts off for a guest, turns on in one tap and is remembered in this browser', async () => {
    renderRoute('/games')
    const toggle = await screen.findByRole('button', { name: 'Sounds' })
    expect(toggle).toHaveAttribute('aria-pressed', 'false')

    await userEvent.click(toggle)

    expect(toggle).toHaveAttribute('aria-pressed', 'true')
    expect(window.localStorage.getItem('sound')).toBe('1')
  })
})
