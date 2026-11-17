import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('The logo', () => {
  it('leads to the home page, signed in too (not the dashboard)', async () => {
    server.use(http.get('*/api/v1/me', () => HttpResponse.json({ data: userFixture() })))
    const { router } = renderRoute('/wallet')

    const header = await screen.findByRole('banner')
    const logo = await screen.findAllByRole('link', { name: 'Test Platform' })
    expect(header).toContainElement(logo[0]!)
    await userEvent.click(logo[0]!)
    expect(router.state.location.pathname).toBe('/')
  })
})
