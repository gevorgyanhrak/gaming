import { screen, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { signedInAs, userFixture } from '@/test/handlers'
import { joined } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Online counter', () => {
  it('shows how many people are connected, as the server counts them', async () => {
    server.use(
      http.get('*/api/v1/stats/online', () => HttpResponse.json({ data: { online: 1234 } })),
    )
    renderRoute('/')

    const counter = await screen.findByTitle('People connected right now')
    expect(counter).toHaveTextContent('1,234')
    expect(counter).toHaveTextContent('online')
  })

  it('shows nothing when the server can’t count (never a made-up number)', async () => {
    renderRoute('/')
    await screen.findByRole('banner')
    await new Promise((resolve) => setTimeout(resolve, 50))
    expect(screen.queryByTitle('People connected right now')).not.toBeInTheDocument()
  })

  it('counts a signed-in player’s page as online', async () => {
    server.use(signedInAs(userFixture({ id: 5 })))
    renderRoute('/games')
    await waitFor(() => expect(joined('online')).toBe(true))
  })

  it('doesn’t count a guest', async () => {
    renderRoute('/games')
    expect((await screen.findAllByRole('link', { name: 'Log in' })).length).toBeGreaterThan(0)
    expect(joined('online')).toBe(false)
  })
})
