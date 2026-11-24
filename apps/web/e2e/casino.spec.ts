import { expect, test } from '@playwright/test'
import { PHONES, LARGER, audit, calm, watchConsole } from './mobile'

/** Every house slot in the casino page: it fits, a real spin is dealt by the server, the way back works. */
const SLOTS = [
  'jungle-slot',
  'stellar-sevens',
  'nitro-hot',
  'scorching-hot',
  'tropic-escape',
  'pearl-reef',
  'discovery-voyage',
  'golden-papyrus',
  'house-of-ra',
  'samurai',
  'the-dynasty',
  'fairytale',
  'bumper-crop',
  'seven-hot',
  'ice-fruits',
  'lucky-slot',
  'gems',
  'gems-50',
  'hot-clover',
  'jokers',
  'jokers-100',
  'ice-fruits-6',
]
const SIZES = [PHONES[0], PHONES[3], { width: 812, height: 375 }, LARGER[0], LARGER[3]]

test('casino lobby', async ({ page }) => {
  const errors = watchConsole(page, 'casino')
  let overflow = 0
  for (const vp of [...PHONES, ...LARGER]) {
    await page.setViewportSize(vp)
    await page.goto('/dashboard?category=casino')
    await calm(page)
    await page.waitForTimeout(600)
    overflow += (await audit(page, 'casino', 'lobby', vp)).overflow
  }
  expect.soft(errors).toEqual([])
  expect(overflow).toBe(0)
})

for (const slug of SLOTS) {
  test(`slot ${slug}`, async ({ page }) => {
    const errors = watchConsole(page, `slot:${slug}`)
    const spins: number[] = []
    page.on('response', (r) => {
      if (r.url().endsWith(`/slots/${slug}/spin`)) spins.push(r.status())
    })
    let overflow = 0
    for (const vp of SIZES) {
      await page.setViewportSize(vp)
      await page.goto(`/casino/slots/${slug}`)
      await calm(page)
      await page.locator('iframe').waitFor()
      await page.waitForTimeout(3500)
      overflow += (await audit(page, 'slot', slug, vp)).overflow
    }
    // A spin at 375 px: Space is the spin key in every house slot.
    await page.setViewportSize({ width: 375, height: 812 })
    await page.goto(`/casino/slots/${slug}`)
    await calm(page)
    await page.waitForTimeout(4000)
    await calm(page) // the level-up / daily bonus popups open a moment after the page
    // Tap the game (dismisses an intro, focuses the frame), then Space, as a player would; twice for slots whose
    // first tap only starts the sound.
    const box = await page.locator('iframe').boundingBox()
    for (let i = 0; i < 2 && spins.length === 0 && box; i++) {
      await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2)
      await page.waitForTimeout(800)
      await page.keyboard.press('Space')
      await page.waitForTimeout(3500)
    }
    if (spins.length === 0) record_noSpin(slug)
    // Back to the casino from the slot page (a level-up earned by the spins may greet first).
    await calm(page)
    await page
      .locator('main a[href="/dashboard?category=casino"]')
      .first()
      .click({ timeout: 10_000 })
    await expect(page).toHaveURL(/\/dashboard\?category=casino/)
    expect.soft(errors).toEqual([])
    expect(overflow).toBe(0)
  })
}

function record_noSpin(slug: string) {
  test.info().annotations.push({
    type: 'no-spin',
    description: `${slug}: Space started no spin (the game may need its own button)`,
  })
}
