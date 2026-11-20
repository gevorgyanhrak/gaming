import { type Page, expect, test } from '@playwright/test'
import { PHONES, LARGER, audit, calm, watchConsole } from './mobile'

/**
 * Every game against the AI, on the real server: the table at every phone size and at the larger ones, then a few
 * moves played from the server's own legal actions (the test never decides what is legal).
 */
const GAMES = [
  'nardi',
  'chess',
  'checkers',
  'durak',
  'poker',
  'blackjack',
  'blot',
  'domino',
  'ludo',
  'pool',
  'rummy',
]

/** Calls the API from the signed-in page, with its cookies and CSRF token, as the app does. */
async function api<T>(
  page: Page,
  path: string,
  body?: unknown,
): Promise<{ status: number; json: T }> {
  const result = await page.evaluate(
    async ({ path, body }) => {
      await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
      const xsrf = decodeURIComponent(
        document.cookie
          .split('; ')
          .find((c) => c.startsWith('XSRF-TOKEN='))
          ?.slice(11) ?? '',
      )
      const r = await fetch(path, {
        method: body === undefined ? 'GET' : 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-XSRF-TOKEN': xsrf,
        },
        body: body === undefined ? undefined : JSON.stringify(body),
      })
      return { status: r.status, json: (await r.json().catch(() => null)) as unknown }
    },
    { path, body },
  )
  return result as { status: number; json: T }
}

/**
 * A legal action as the client sends it: range actions ({min, max}) take an amount, a pool shot an angle and power
 * (as the boards do); every other field the server listed is the move itself.
 */
function payloadFor(action: Record<string, unknown>) {
  const { min, max, ball_in_hand: ballInHand, ...move } = action
  void max
  if (move.type === 'shoot')
    return ballInHand
      ? { ...move, angle: 0, power: 40, x: 0.25, y: 0.5 }
      : { ...move, angle: 0, power: 40 }
  return min === undefined ? move : { ...move, amount: min }
}

type Game = { data: { min_players: number; modes: { id: number }[] } }
type Room = { data: { id: string; public_id?: string } }
type State = {
  data: { version: number; legal_actions: Record<string, unknown>[]; status?: string }
}

async function startAiTable(page: Page, slug: string) {
  // Leave whatever table this account still sits at, so the new one is the only game in progress.
  const mine = await api<{ data: { id: string }[] }>(page, '/api/v1/me/rooms')
  for (const room of mine.json?.data ?? []) await api(page, `/api/v1/rooms/${room.id}/leave`, {})
  const game = await api<Game>(page, `/api/v1/games/${slug}`)
  const mode = game.json.data.modes[0].id
  const created = await api<Room>(page, '/api/v1/rooms', {
    game_mode_id: mode,
    type: 'bot',
    // Blot is played by four; other games take the default two.
    ...(game.json.data.min_players > 2 ? { opponents: game.json.data.min_players - 1 } : {}),
  })
  expect(
    created.status,
    `create ${slug} table: ${JSON.stringify(created.json).slice(0, 200)}`,
  ).toBeLessThan(300)
  return created.json.data.id
}

for (const slug of GAMES) {
  test(`${slug} against the AI`, async ({ page }) => {
    const errors = watchConsole(page, `game:${slug}`)
    await page.setViewportSize({ width: 375, height: 812 })
    await page.goto('/dashboard')
    await calm(page)
    const id = await startAiTable(page, slug)
    const route = `/rooms/${id}`
    let overflow = 0
    for (const vp of [...PHONES, ...LARGER]) {
      await page.setViewportSize(vp)
      if (vp === PHONES[0]) {
        await page.goto(route)
        await calm(page)
      }
      await page.waitForTimeout(1200)
      overflow += (await audit(page, 'game', `${slug}`, vp)).overflow
    }

    // Play from the server's legal actions at 375 px, a few turns, through the real action endpoint.
    await page.setViewportSize({ width: 375, height: 812 })
    let played = 0
    for (let i = 0; i < 6; i++) {
      const state = await api<State>(page, `/api/v1/rooms/${id}/state`)
      const legal = state.json?.data?.legal_actions ?? []
      // Plain choices first (pass, check, a card): some ranges need fields only their board fills (a Blot bid's suit).
      const action =
        legal.find((a) => a.type === 'pass' || a.type === 'check') ??
        legal.find((a) => !('min' in a)) ??
        legal[0]
      if (!action) {
        await page.waitForTimeout(1500)
        continue
      }
      const r = await api(page, `/api/v1/rooms/${id}/actions`, {
        action: payloadFor(action),
        expected_version: state.json.data.version,
        client_action_id: crypto.randomUUID(),
      })
      if (r.status < 300) played++
      await page.waitForTimeout(1500)
    }
    await audit(page, 'game', `${slug}-after-moves`, { width: 375, height: 812 })
    test.info().annotations.push({ type: 'moves', description: `${slug}: ${played} accepted` })
    await api(page, `/api/v1/rooms/${id}/leave`, {})
    expect.soft(errors, 'console errors').toEqual([])
    expect(overflow, 'table wider than the screen').toBe(0)
  })
}
