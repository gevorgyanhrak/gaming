import { act, screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { Banner } from './banners'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const banner = (overrides: Partial<Banner>): Banner => ({
  id: 1,
  title: 'Poker night',
  description: null,
  cta: null,
  image_url: null,
  link: null,
  ...overrides,
})

describe('Casino promotion banners', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('shows nothing when an administrator has made no banner visible', async () => {
    renderRoute('/')
    await screen.findByRole('region', { name: 'Top games' })
    expect(screen.queryByRole('region', { name: 'Promotions' })).not.toBeInTheDocument()
  })

  it('shows the banners in order with their links, and turns to the next one', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    server.use(
      http.get('*/api/v1/banners', () =>
        HttpResponse.json({
          data: [
            banner({
              id: 1,
              title: 'Poker night',
              description: 'Every Friday',
              link: '/games/poker',
              cta: 'Sit down',
            }),
            banner({ id: 2, title: 'Our rules', link: 'https://example.org/rules' }),
          ],
        }),
      ),
    )
    renderRoute('/')

    const promos = await screen.findByRole('region', { name: 'Promotions' })
    const first = within(promos).getByRole('group', { name: '1 of 2' })
    expect(within(first).getByText('Every Friday')).toBeInTheDocument()
    expect(within(first).getByRole('link', { name: 'Sit down' })).toHaveAttribute(
      'href',
      '/games/poker',
    )
    const second = promos.querySelectorAll('[aria-roledescription="slide"]')[1] as HTMLElement
    expect(second).toHaveAttribute('aria-hidden', 'true')
    const away = within(second).getByRole('link', { name: 'Open', hidden: true })
    expect(away).toHaveAttribute('target', '_blank')
    expect(away).toHaveAttribute('rel', 'noopener noreferrer')

    act(() => {
      vi.advanceTimersByTime(7000)
    })
    expect(second).not.toHaveAttribute('aria-hidden')
    expect(second).toHaveAccessibleName('2 of 2')
    expect(within(promos).getByRole('button', { name: 'Show promotion 2' })).toHaveAttribute(
      'aria-current',
      'true',
    )
  })
})
