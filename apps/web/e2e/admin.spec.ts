import { expect, test } from '@playwright/test'
import { PHONES, LARGER, audit } from './mobile'

/** The Filament admin (/admin on the API) on phones: signs in with E2E_ADMIN_EMAIL / E2E_ADMIN_PASSWORD. */
const ADMIN = process.env.E2E_ADMIN_URL ?? 'http://localhost:8000/admin'
const PAGES = [
  '',
  '/users',
  '/games',
  '/game-categories',
  '/play-groups',
  '/bot-profiles',
  '/level-rewards',
  '/casino-banners',
  '/slot-games',
  '/slot-providers',
  '/slot-transactions',
  '/content-pages',
  '/audit-logs',
  '/manage-settings',
]

test('admin panel', async ({ browser }) => {
  test.skip(!process.env.E2E_ADMIN_EMAIL, 'E2E_ADMIN_EMAIL not set')
  const context = await browser.newContext({ storageState: { cookies: [], origins: [] } })
  const page = await context.newPage()
  await page.goto(`${ADMIN}/login`)
  await page.locator('input[type="email"]').fill(process.env.E2E_ADMIN_EMAIL!)
  await page.locator('input[type="password"]').fill(process.env.E2E_ADMIN_PASSWORD ?? '')
  await page.locator('form button[type="submit"]').click()
  await page.waitForURL((u) => !u.pathname.endsWith('/login'))
  let overflow = 0
  for (const vp of [PHONES[0], PHONES[3], LARGER[3]]) {
    await page.setViewportSize(vp)
    for (const p of PAGES) {
      const r = await page.goto(`${ADMIN}${p}`)
      if (!r || r.status() >= 400) continue
      await page.waitForTimeout(400)
      overflow += (await audit(page, 'admin', `admin${p}`, vp)).overflow
    }
  }
  await context.close()
  expect(overflow).toBe(0)
})
