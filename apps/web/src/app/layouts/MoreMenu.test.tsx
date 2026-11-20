import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

/** The panel the phone's More button drops from the header (HayBet item 5: no side menu any more). */
const panel = () => document.getElementById('more-menu')

describe('The More menu (phones)', () => {
  it('drops from the header with every section, the casino too, sounds and the language', async () => {
    server.use(http.get('*/api/v1/me', () => HttpResponse.json({ data: userFixture() })))
    renderRoute('/wallet')

    const button = await screen.findByRole('button', { name: 'Menu' })
    expect(button).toHaveAttribute('aria-expanded', 'false')
    await userEvent.click(button)
    expect(button).toHaveAttribute('aria-expanded', 'true')
    const menu = panel()!
    // No side drawer: a panel in the header, not a dialog over the page.
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(within(menu).getByRole('link', { name: 'Casino' })).toHaveAttribute(
      'href',
      '/dashboard?category=casino',
    )
    for (const name of ['Home', 'Games', 'Ranks', 'Leaderboards']) {
      expect(within(menu).getByRole('link', { name })).toBeInTheDocument()
    }
    expect(within(menu).getByRole('button', { name: 'Sounds' })).toBeInTheDocument()
    expect(within(menu).getByRole('combobox', { name: 'Language' })).toBeInTheDocument()
  })

  it('closes on Escape, on a tap outside, and when a section is chosen', async () => {
    renderRoute('/dashboard')
    const button = await screen.findByRole('button', { name: 'Menu' })

    await userEvent.click(button)
    await userEvent.keyboard('{Escape}')
    expect(panel()).toBeNull()

    await userEvent.click(button)
    await userEvent.click(document.body)
    expect(panel()).toBeNull()

    await userEvent.click(button)
    await userEvent.click(within(panel()!).getByRole('link', { name: 'Leaderboards' }))
    expect(panel()).toBeNull()
    expect(await screen.findByRole('heading', { level: 1 })).toBeInTheDocument()
  })
})
