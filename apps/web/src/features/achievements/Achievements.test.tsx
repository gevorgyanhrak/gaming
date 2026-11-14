import { act, screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { signedInAs, userFixture } from '@/test/handlers'
import { emit } from '@/test/realtime'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const publicUser = (achievements: object[]) => ({
  username: 'ani',
  level: 7,
  looks: { frame: null, badge: null },
  display_name: 'Ani',
  avatar_url: null,
  bio: null,
  country: null,
  created_at: '2026-01-01T00:00:00Z',
  last_active_at: null,
  stats: [],
  achievements,
})

describe('Achievements', () => {
  it('shows a player’s achievements on their profile, with the game they were earned in', async () => {
    server.use(
      http.get('*/api/v1/users/ani', () =>
        HttpResponse.json({
          data: publicUser([
            {
              key: 'big_win',
              game: { slug: 'poker', name: 'Poker' },
              period: null,
              value: 12_000,
              earned_at: '2026-11-14T10:00:00Z',
            },
            {
              key: 'veteran_100',
              game: null,
              period: null,
              value: 100,
              earned_at: '2026-11-13T10:00:00Z',
            },
          ]),
        }),
      ),
    )
    renderRoute('/u/ani')

    const list = await screen.findByRole('region', { name: 'Achievements' })
    expect(within(list).getByText('Big Poker win')).toBeInTheDocument()
    expect(within(list).getByText('Experienced: 100 games')).toBeInTheDocument()
  })

  it('says when a player has none yet', async () => {
    server.use(http.get('*/api/v1/users/ani', () => HttpResponse.json({ data: publicUser([]) })))
    renderRoute('/u/ani')
    expect(await screen.findByText('No achievements yet.')).toBeInTheDocument()
  })

  it('congratulates the player the moment they earn one', async () => {
    server.use(signedInAs(userFixture({ id: 1, username: 'ani' })))
    renderRoute('/games')
    // Signed in: the header shows the player, and the app listens on their channel.
    await screen.findAllByText('ani')
    await act(() => new Promise((resolve) => setTimeout(resolve, 0)))

    act(() =>
      emit('user.1', 'achievement.unlocked', {
        key: 'weekly_champion',
        game: null,
        period: '2026-11-09',
        value: 9_000,
      }),
    )

    const toast = await screen.findByText('Achievement unlocked!')
    expect(
      within(toast.closest('[role="status"]') as HTMLElement).getAllByText('Weekly champion')
        .length,
    ).toBeGreaterThan(0)
  })
})
