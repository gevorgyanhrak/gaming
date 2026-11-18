import { defineConfig } from '@playwright/test'

/**
 * Mobile QA suite (e2e/): runs against the local stack (API on :8000, web on :5175) with the local test account.
 * PW_CHROMIUM may point at an installed Chromium; otherwise Playwright's own browser is used.
 *   npx playwright test            — everything
 *   E2E_ITERATION=2 npx playwright test e2e/routes.spec.ts
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 10 * 60_000,
  workers: 1,
  reporter: [['list'], ['json', { outputFile: 'e2e/results/results.json' }]],
  globalSetup: './e2e/global-setup.ts',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:5175',
    storageState: 'e2e/.auth/player.json',
    contextOptions: { reducedMotion: 'reduce' },
    launchOptions: process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {},
  },
})
