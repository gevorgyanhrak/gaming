import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { signedInAs, userFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

describe('Club ranks', () => {
  it('lights your rank and shows how many levels are left to the next', async () => {
    server.use(signedInAs(userFixture({ id: 1, username: 'ani', level: 12 })))
    renderRoute('/')

    expect(await screen.findByText('Your rank: Silver (level 12)')).toBeInTheDocument()
    expect(screen.getByText('8 levels to Gold')).toBeInTheDocument()
    expect(screen.getByRole('progressbar', { name: 'Progress to the next rank' })).toHaveAttribute(
      'aria-valuenow',
      '20',
    )
    expect(screen.getAllByRole('img', { name: 'Amethyst' }).length).toBeGreaterThan(0)
  })
})
