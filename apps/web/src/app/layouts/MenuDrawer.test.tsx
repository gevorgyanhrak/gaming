import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('The ☰ menu', () => {
  it('offers the same sections as the desktop header, the casino too, and the sound switch', async () => {
    server.use(http.get('*/api/v1/me', () => HttpResponse.json({ data: userFixture() })))
    renderRoute('/wallet')

    await userEvent.click(await screen.findByRole('button', { name: 'Menu' }))
    const menu = screen.getByRole('dialog', { name: 'Menu' })

    expect(within(menu).getByRole('link', { name: 'Casino' })).toHaveAttribute(
      'href',
      '/games?category=casino',
    )
    for (const name of ['Home', 'Games', 'Ranks']) {
      expect(within(menu).getByRole('link', { name })).toBeInTheDocument()
    }
    expect(within(menu).getByRole('button', { name: 'Sounds' })).toBeInTheDocument()
  })
})
