import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { signedInAs } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'
import type { Mission } from './api'

const ends = new Date(Date.now() + 5 * 3_600_000).toISOString()
const mission = (overrides: Partial<Mission>): Mission => ({
  key: 'play_3',
  period: 'day',
  kind: 'play',
  target: 3,
  progress: 1,
  reward: 300,
  claimed: false,
  ends_at: ends,
  ...overrides,
})

describe('Missions', () => {
  it('shows today’s missions and the week’s challenges with progress, and collects a finished one', async () => {
    let missions = [
      mission({ key: 'play_3', progress: 1 }),
      mission({ key: 'win_1', kind: 'win', target: 1, progress: 1, reward: 400 }),
      mission({ key: 'week_play_20', period: 'week', target: 20, progress: 4, reward: 2500 }),
    ]
    let claimed: string | null = null
    server.use(
      signedInAs(),
      http.get('*/api/v1/me/missions', () => HttpResponse.json({ data: missions })),
      http.post('*/api/v1/me/missions/:key/claim', ({ params }) => {
        claimed = params.key as string
        missions = missions.map((m) => (m.key === claimed ? { ...m, claimed: true } : m))
        return HttpResponse.json({ data: missions })
      }),
    )
    renderRoute('/')

    const panel = (await screen.findByRole('heading', { name: 'Missions' })).closest('section')!
    expect(within(panel).getByText('Play 3 games')).toBeInTheDocument()
    expect(within(panel).getByText('Play 20 games')).toBeInTheDocument()
    expect(within(panel).getAllByRole('progressbar')[0]).toHaveAttribute('aria-valuenow', '1')
    expect(within(panel).getAllByText('New in 5 hours').length).toBeGreaterThan(0)
    // Not finished yet: can't be collected.
    expect(within(panel).getByRole('button', { name: 'Collect 300 Fishkas' })).toBeDisabled()

    await userEvent.click(within(panel).getByRole('button', { name: 'Collect 400 Fishkas' }))

    expect(claimed).toBe('win_1')
    expect(await within(panel).findByText('Collected')).toBeInTheDocument()
  })

  it('stays out of the way for guests', async () => {
    renderRoute('/')
    await screen.findByRole('banner')
    expect(screen.queryByRole('heading', { name: 'Missions' })).not.toBeInTheDocument()
  })
})
