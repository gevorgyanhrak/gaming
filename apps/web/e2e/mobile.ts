import { type Frame, type Page, test } from '@playwright/test'
import { appendFileSync, mkdirSync } from 'node:fs'

/** The widths the platform supports (375 is the critical one), each with a typical phone height. */
export const PHONES = [
  { width: 320, height: 667 },
  { width: 344, height: 740 },
  { width: 360, height: 740 },
  { width: 375, height: 812 },
  { width: 390, height: 844 },
  { width: 414, height: 932 },
  { width: 430, height: 932 },
] as const
export const LARGER = [
  { width: 768, height: 1024 },
  { width: 1024, height: 768 },
  { width: 1280, height: 800 },
  { width: 1440, height: 900 },
] as const
export const ALL = [...PHONES, ...LARGER]

export const ITERATION = process.env.E2E_ITERATION ?? '1'

export type Finding = {
  area: string
  route: string
  viewport: string
  kind: string
  detail: string
  screenshot: string
}

/** Every finding goes to e2e/results/findings-<iteration>.jsonl, for the report. */
export function record(f: Finding) {
  mkdirSync('e2e/results', { recursive: true })
  appendFileSync(`e2e/results/findings-${ITERATION}.jsonl`, `${JSON.stringify(f)}\n`)
}

/**
 * What a page shows wrong at this size: horizontal overflow (the document wider than the window, or an element
 * past the edges outside a scroll container), and tap targets smaller than 24 px (WCAG 2.5.8; inline text links
 * in a sentence are exempt).
 */
export async function scan(page: Page | Frame) {
  return page.evaluate(() => {
    const vw = document.documentElement.clientWidth
    const over: string[] = []
    const small: string[] = []
    const label = (el: Element) => {
      const text = (el.getAttribute('aria-label') || el.textContent || '')
        .trim()
        .replace(/\s+/g, ' ')
        .slice(0, 30)
      const cls =
        typeof el.className === 'string' ? el.className.split(' ').slice(0, 3).join('.') : ''
      return `${el.tagName.toLowerCase()}${cls ? `.${cls}` : ''} "${text}"`
    }
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect()
      if (!r.width || !r.height) continue
      const cs = getComputedStyle(el)
      if (cs.visibility === 'hidden' || el.closest('[aria-hidden="true"], .sr-only, [inert]'))
        continue
      if (r.right > vw + 1 || r.left < -1) {
        let p = el.parentElement
        let clipped = false
        while (p) {
          const o = getComputedStyle(p).overflowX
          if (o !== 'visible') {
            clipped = true
            break
          }
          p = p.parentElement
        }
        if (!clipped && cs.position !== 'fixed')
          over.push(`${label(el)} [${Math.round(r.left)}…${Math.round(r.right)}]`)
      }
      if (
        el.matches(
          'a[href], button, input:not([type=checkbox]):not([type=radio]), select, textarea, [role="button"], [role="tab"], [role="radio"]',
        ) &&
        (r.width < 24 || r.height < 24) &&
        !(el.tagName === 'A' && cs.display === 'inline')
      ) {
        small.push(`${label(el)} ${Math.round(r.width)}×${Math.round(r.height)}`)
      }
    }
    return {
      doc: document.documentElement.scrollWidth,
      vw,
      over: [...new Set(over)].slice(0, 8),
      small: [...new Set(small)].slice(0, 8),
    }
  })
}

/** Closes whatever greets a player (daily bonus, level-up, out of Fishkas) so the page itself is tested. */
export async function calm(page: Page) {
  for (let i = 0; i < 3; i++) {
    const dialog = page.getByRole('dialog').first()
    // Escape on the dialog itself: when a slot's frame has focus, a page-level key would go to the game.
    if (await dialog.isVisible().catch(() => false)) await dialog.press('Escape').catch(() => {})
    else await page.keyboard.press('Escape')
    await page.waitForTimeout(200)
  }
}

export function screenshotPath(area: string, route: string, viewport: string) {
  const slug = route.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home'
  const dir = `e2e/screenshots/iteration-${ITERATION}/${area}/${slug}`
  mkdirSync(dir, { recursive: true })
  return `${dir}/${viewport}.png`
}

/** Scans the page at this size, records findings, saves a screenshot; returns the counts for the test to assert. */
export async function audit(
  page: Page,
  area: string,
  route: string,
  viewport: { width: number; height: number },
  target: Page | Frame = page,
) {
  const name = `${viewport.width}x${viewport.height}`
  const shot = screenshotPath(area, route, name)
  await page.screenshot({ path: shot, fullPage: area !== 'game' && area !== 'slot' })
  const s = await scan(target)
  if (s.doc > s.vw + 1)
    record({
      area,
      route,
      viewport: name,
      kind: 'overflow',
      detail: `page ${s.doc}px wide in ${s.vw}px`,
      screenshot: shot,
    })
  for (const o of s.over)
    record({ area, route, viewport: name, kind: 'outside', detail: o, screenshot: shot })
  for (const o of s.small)
    record({ area, route, viewport: name, kind: 'tap-target', detail: o, screenshot: shot })
  return { overflow: s.doc > s.vw + 1 ? 1 : 0, outside: s.over.length, small: s.small.length, shot }
}

/** Console errors (not network noise from rate limits or signed-out probes). */
export function watchConsole(page: Page, area: string) {
  const errors: string[] = []
  page.on('console', (m) => {
    if (m.type() === 'error' && !/401|419|429|ERR_NETWORK_IO_SUSPENDED/.test(m.text()))
      errors.push(m.text().slice(0, 200))
  })
  page.on('pageerror', (e) => errors.push(`pageerror: ${e.message.slice(0, 200)}`))
  test.info().annotations.push({ type: 'area', description: area })
  return errors
}
