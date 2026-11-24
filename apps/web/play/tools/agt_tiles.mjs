// Renders each AGT-style game's lobby tile (play/<slug>/tile.webp, 1024×1024) from its own page: the title over the
// reels, without the control panel. Needs the local stack and a signed-in session (the page loads the game's
// layout from the API).  node play/tools/agt_tiles.mjs seven-hot ice-fruits …
import { execFileSync } from 'node:child_process'
import { mkdirSync } from 'node:fs'
import { chromium } from 'playwright-core'

const CHROME = process.env.PW_CHROMIUM
const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:5175'
const slugs = process.argv.slice(2)

const browser = await chromium.launch(CHROME ? { executablePath: CHROME } : {})
const page = await (await browser.newContext({ viewport: { width: 1024, height: 1024 } })).newPage()
await page.goto(`${BASE}/login`)
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

for (const slug of slugs) {
  await page.goto(`${BASE}/play/${slug}/`)
  await page.waitForSelector('.reel .cell')
  await page.addStyleTag({
    content: `#stage{width:1024px!important;height:1024px!important;transform:none!important;left:0!important;top:0!important;
      grid-template-rows:200px 1fr!important;padding:40px!important}
      .panel,#message,#rotate{display:none!important}
      #board{width:100%!important;height:auto!important;align-self:center}#title{font-size:76px!important}`,
  })
  await page.evaluate(() => window.dispatchEvent(new Event('resize')))
  await page.waitForTimeout(600)
  const png = `play/${slug}/tile.png`
  await page.screenshot({ path: png })
  mkdirSync(`public/play/${slug}`, { recursive: true })
  execFileSync('cwebp', ['-quiet', '-q', '82', png, '-o', `public/play/${slug}/tile.webp`])
  execFileSync('rm', [png])
  console.log(`${slug}: public/play/${slug}/tile.webp`)
}
await browser.close()
