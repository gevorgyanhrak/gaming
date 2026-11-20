import { apiRequest } from '@/shared/api/http'
import type { Schemas } from '@/shared/api/types'

export type Slot = Schemas['SlotGameResource']

export const slotsKey = ['slots'] as const

/** The slot games players can open: on, from a connected provider (ADR 0023) or the house (ADR 0024). */
export const fetchSlots = (signal?: AbortSignal) =>
  apiRequest<{ data: Slot[] }>('/api/v1/slots', { signal }).then((r) => r.data)

/**
 * Messages a house slot's page (an iframe under /play/) sends to the page around it: a spin was dealt (with its win
 * and the new balance), the win has been shown (HayBet item 17), or the bet was more than the balance.
 */
export type SlotMessage =
  | { type: 'slot:spun'; win?: number; balance?: number }
  | { type: 'slot:settled' }
  | { type: 'slot:low-balance' }

export function isSlotMessage(data: unknown): data is SlotMessage {
  if (typeof data !== 'object' || data === null) return false
  const type = (data as { type?: unknown }).type
  return type === 'slot:spun' || type === 'slot:settled' || type === 'slot:low-balance'
}
