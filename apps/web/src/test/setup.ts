import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll, vi } from 'vitest'
import i18n from '@/shared/i18n'
import { resetDailyBonus } from '@/features/wallet/dailyBonus'
import { resetChannels } from './realtime'
import { server } from './server'

vi.mock('@/shared/realtime/echo', async () => {
  const { fakeEcho } = await import('./realtime')
  // The fake socket counts as connected: tests drive updates with emit() rather than polling.
  return {
    getEcho: () => fakeEcho,
    disconnectEcho: () => undefined,
    isRealtimeConnected: () => true,
    onRealtimeStatus: () => () => undefined,
  }
})

beforeAll(() => server.listen({ onUnhandledFrame: 'error' }))

afterEach(async () => {
  cleanup()
  server.resetHandlers()
  resetChannels()
  resetDailyBonus()
  await i18n.changeLanguage('en')
})

afterAll(() => server.close())
