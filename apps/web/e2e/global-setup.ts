import { chromium, type FullConfig } from '@playwright/test'
import { mkdirSync } from 'node:fs'

/** Signs in once with the local test account and keeps the session for every test (rate limits stay low). */
export default async function globalSetup(config: FullConfig) {
  const base = config.projects[0]?.use.baseURL ?? 'http://localhost:5175'
  const browser = await chromium.launch(
    process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {},
  )
  const page = await browser.newPage()
  await page.goto(`${base}/login`)
  await page
    .locator('input[autocomplete="username"], input[type="text"]')
    .first()
    .fill(process.env.E2E_EMAIL ?? 'player1@example.test')
  await page
    .locator('input[type="password"]')
    .first()
    .fill(process.env.E2E_PASSWORD ?? 'password')
  await page.locator('form button[type="submit"]').first().click()
  await page.waitForURL((u) => !u.pathname.startsWith('/login'))
  mkdirSync('e2e/.auth', { recursive: true })
  await page.context().storageState({ path: 'e2e/.auth/player.json' })
  await browser.close()
}
