/**
 * The bridge between a house slot's client (an APEXSLOTS open-source game under /play/<id>/, ADR 0024) and our
 * API. The server deals every round: the client sends only the bet and a one-time key, and shows what comes back.
 * Fishkas are whole numbers and play money only. The page around the game (`SlotPage`) hears about every spin and
 * a low balance through `postMessage`.
 */
import { apiRequest } from '@/shared/api/http'
import { ApiError } from '@/shared/api/errors'
import { randomId } from '@/shared/lib/uuid'

export interface HouseSession {
  slug: string
  name: string
  balance: number
  bets: number[]
}

export interface HouseRound<Round> {
  round_id: string
  round: Round
  bet: number
  win: number
  balance: number
  replay: boolean
}

/** A refused or failed request: the message to show, and the balance when the server sent it. */
export class HouseError extends Error {
  readonly balance?: number
  readonly code?: string

  constructor(message: string, balance?: number, code?: string) {
    super(message)
    this.balance = balance
    this.code = code
  }
}

function toHouseError(error: unknown): HouseError {
  if (error instanceof ApiError) {
    if (error.status === 401 || error.status === 419) {
      window.top?.location.assign('/login')
    }
    const body = (error.body ?? {}) as { balance?: number | null }
    return new HouseError(error.message, body.balance ?? undefined, error.code ?? undefined)
  }
  return new HouseError('The server did not answer. Your Fishkas are safe; try again.')
}

/** Tells the page around the game (if any). */
function tellPage(
  type: 'slot:spun' | 'slot:settled' | 'slot:low-balance',
  detail: { win?: number; balance?: number } = {},
): boolean {
  if (window.parent === window) return false
  window.parent.postMessage({ type, ...detail }, window.location.origin)
  return true
}

/** The pause between a win shown and the balance counting up (HayBet item 17). */
export const SETTLE_DELAY_MS = 1000
const COUNT_MS = 800

/**
 * After a win has been shown: wait a second, count the game's own credits from `from` to `to`, then tell the page
 * (whose header held the win back) to count up too. The win was credited by the server with the spin — this only
 * paces what is shown. Without a win, call it with from === to: it settles at once.
 */
export async function settleWin(
  from: number,
  to: number,
  show: (value: number) => void,
): Promise<void> {
  if (to > from) {
    await new Promise((resolve) => setTimeout(resolve, SETTLE_DELAY_MS))
    const still = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    if (!still) {
      const start = performance.now()
      await new Promise<void>((resolve) => {
        const step = (now: number) => {
          const t = Math.min(1, (now - start) / COUNT_MS)
          show(Math.round(from + (to - from) * (1 - Math.pow(1 - t, 3))))
          if (t < 1) requestAnimationFrame(step)
          else resolve()
        }
        requestAnimationFrame(step)
      })
    }
  }
  show(to)
  tellPage('slot:settled')
}

/** For games whose own client shows the credits: just the pause, then tell the page. */
export async function settleAfterWin(win: number): Promise<void> {
  if (win > 0) await new Promise((resolve) => setTimeout(resolve, SETTLE_DELAY_MS))
  tellPage('slot:settled')
}

export async function openGame(slug: string): Promise<HouseSession> {
  try {
    const { data } = await apiRequest<{ data: HouseSession }>(`/api/v1/slots/${slug}/play`)
    return data
  } catch (error) {
    throw toHouseError(error)
  }
}

/**
 * One round, dealt by the server. A dropped connection retries the same key, so the server answers with the
 * round it already dealt instead of charging again.
 */
export async function spin<Round>(slug: string, bet: number): Promise<HouseRound<Round>> {
  const body = { bet, key: randomId() }
  for (let attempt = 0; ; attempt++) {
    try {
      const { data } = await apiRequest<{ data: HouseRound<Round> }>(`/api/v1/slots/${slug}/spin`, {
        method: 'POST',
        body,
      })
      tellPage('slot:spun', { win: data.win, balance: data.balance })
      return data
    } catch (error) {
      const failed = toHouseError(error)
      if (error instanceof ApiError && error.status !== 0 && error.status < 500) {
        if (failed.code === 'insufficient_fishkas') tellPage('slot:low-balance')
        throw failed
      }
      if (attempt >= 2) throw failed
      await new Promise((resolve) => setTimeout(resolve, 700))
    }
  }
}

/** Not enough Fishkas for the bet: the page shows its "out of Fishkas" card; alone, the game says so itself. */
export function tellLowBalance(): boolean {
  return tellPage('slot:low-balance')
}

/** Whole Fishkas, grouped by thousands. */
export function fishkas(value: number): string {
  return Math.floor(value).toLocaleString('en-US')
}
