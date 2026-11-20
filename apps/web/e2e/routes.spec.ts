import { expect, test } from '@playwright/test'
import { ALL, audit, calm, watchConsole } from './mobile'

/**
 * Every app route at every supported size: no horizontal overflow, nothing past the screen edges, tap targets
 * at least 24 px, no console errors. Findings go to e2e/results; screenshots to e2e/screenshots/iteration-N.
 */
const GUEST = [
  '/',
  '/games',
  '/login',
  '/register',
  '/forgot-password',
  '/leaderboards',
  '/ranks',
  '/u/player2',
  '/no-such-page',
]
const PLAYER = [
  '/',
  '/dashboard',
  '/games',
  '/games/poker',
  '/games/nardi',
  '/games/blot',
  '/games/chess',
  '/games/checkers',
  '/games/durak',
  '/games/blackjack',
  '/games/domino',
  '/games/ludo',
  '/games/pool',
  '/games/rummy',
  '/dashboard?category=casino',

  '/leaderboards',
  '/ranks',
  '/profile',
  '/wallet',
  '/history',
  '/settings',
  '/u/player2',
  '/casino/slots/jungle-slot',
]

for (const vp of ALL) {
  test.describe(`${vp.width}x${vp.height}`, () => {
    test.use({ viewport: vp })

    // Signed out, in every language: long words (ru "Регистрация") are what overflow first.
    for (const lang of ['en', 'ru', 'hy']) {
      test(`guest routes (${lang})`, async ({ browser }) => {
        const context = await browser.newContext({
          viewport: vp,
          storageState: { cookies: [], origins: [] },
          reducedMotion: 'reduce',
        })
        await context.addInitScript((l) => localStorage.setItem('locale', l), lang)
        const page = await context.newPage()
        const errors = watchConsole(page, 'guest')
        let overflow = 0
        for (const route of GUEST) {
          await page.goto(route)
          await page.waitForLoadState('networkidle').catch(() => {})
          await page.waitForTimeout(400)
          overflow += (await audit(page, `guest-${lang}`, route, vp)).overflow
        }
        await context.close()
        expect.soft(errors, 'console errors').toEqual([])
        expect(overflow, 'pages wider than the screen').toBe(0)
      })
    }

    test('player routes', async ({ page }) => {
      const errors = watchConsole(page, 'player')
      let overflow = 0
      for (const route of PLAYER) {
        await page.goto(route)
        await page.waitForLoadState('networkidle').catch(() => {})
        await calm(page)
        await page.waitForTimeout(500)
        overflow += (await audit(page, 'player', route, vp)).overflow
        await page.waitForTimeout(700) // pace /me under throttle:account
      }
      expect.soft(errors, 'console errors').toEqual([])
      expect(overflow, 'pages wider than the screen').toBe(0)
    })
  })
}
