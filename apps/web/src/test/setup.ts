import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll, vi } from 'vitest'
import i18n from '@/shared/i18n'
import { resetChannels } from './realtime'
import { server } from './server'

vi.mock('@/shared/realtime/echo', async () => {
  const { fakeEcho } = await import('./realtime')
  return { getEcho: () => fakeEcho, disconnectEcho: () => undefined }
})

beforeAll(() => server.listen({ onUnhandledFrame: 'error' }))

afterEach(async () => {
  cleanup()
  server.resetHandlers()
  resetChannels()
  await i18n.changeLanguage('en')
})

afterAll(() => server.close())
