import { act, waitFor } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { HOLD_MS, releaseBalance } from '@/features/wallet/balanceHold'
import { signedInAs, walletFixture } from '@/test/handlers'
import { renderRoute } from '@/test/render'
import { server } from '@/test/server'

const slot = {
  slug: 'jungle-slot',
  name: 'Jungle Slot',
  provider: 'House',
  image_url: null,
  min_bet: 10,
  max_bet: 1000,
  play_path: '/play/jungle-slot/',
  credit: null,
}

/** What the slot's page inside the frame tells the page around it. */
const fromGame = (data: Record<string, unknown>) =>
  act(() => {
    window.dispatchEvent(new MessageEvent('message', { data, origin: window.location.origin }))
  })

const pill = () => document.querySelector('[data-fishka-balance]')

describe('Slot page', () => {
  afterEach(() => {
    releaseBalance()
    vi.useRealTimers()
  })

  it('holds a win back in the header until the game has shown it, then shows it (HayBet item 17)', async () => {
    // The server has already credited the 500 win: the wallet says 10,500.
    server.use(
      signedInAs(),
      http.get('*/api/v1/slots', () => HttpResponse.json({ data: [slot] })),
      http.get('*/api/v1/me/wallet', () =>
        HttpResponse.json({ data: walletFixture({ balance: 10_500 }) }),
      ),
    )
    renderRoute('/casino/slots/jungle-slot')
    await waitFor(() => expect(pill()).toHaveTextContent('10,500'))

    fromGame({ type: 'slot:spun', win: 500, balance: 10_500 })
    await waitFor(() => expect(pill()).toHaveTextContent('10,000'))

    fromGame({ type: 'slot:settled' })
    await waitFor(() => expect(pill()).toHaveTextContent('10,500'))
  })

  it('never keeps the header wrong: a hold lets go by itself if the game never settles', async () => {
    server.use(
      signedInAs(),
      http.get('*/api/v1/slots', () => HttpResponse.json({ data: [slot] })),
      http.get('*/api/v1/me/wallet', () =>
        HttpResponse.json({ data: walletFixture({ balance: 10_500 }) }),
      ),
    )
    renderRoute('/casino/slots/jungle-slot')
    await waitFor(() => expect(pill()).toHaveTextContent('10,500'))

    vi.useFakeTimers({ shouldAdvanceTime: true })
    fromGame({ type: 'slot:spun', win: 500, balance: 10_500 })
    await waitFor(() => expect(pill()).toHaveTextContent('10,000'))
    act(() => {
      vi.advanceTimersByTime(HOLD_MS + 10)
    })
    await waitFor(() => expect(pill()).toHaveTextContent('10,500'))
  })
})
