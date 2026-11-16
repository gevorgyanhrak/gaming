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
function tellPage(type: 'slot:spun' | 'slot:low-balance'): boolean {
  if (window.parent === window) return false
  window.parent.postMessage({ type }, window.location.origin)
  return true
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
      tellPage('slot:spun')
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
