import type { TFunction } from 'i18next'
import { describe, expect, it } from 'vitest'
import i18n from '@/shared/i18n'
import { roomStateFixture } from '@/test/handlers'
import { waitingCaption } from './turnCaption'

const t = i18n.t.bind(i18n) as TFunction
const names = { 0: 'alice', 1: 'Aram', 2: 'Nina' }

describe('waitingCaption', () => {
  it('says nothing once the game is over or when it is my turn', () => {
    const over = roomStateFixture({
      seats_to_act: [],
      result: { end_reason: 'completed', seats: ['win', 'loss'] },
    })
    expect(waitingCaption(over, names, t)).toBeNull()
    expect(waitingCaption(roomStateFixture({ seats_to_act: [] }), names, t)).toBeNull()
    expect(waitingCaption(roomStateFixture({ seats_to_act: [0] }), names, t)).toBeNull()
  })

  it('names the one player acting, or says "the other players" when several act', () => {
    expect(waitingCaption(roomStateFixture({ seats_to_act: [1] }), names, t)).toBe(
      'Waiting for Aram',
    )
    expect(waitingCaption(roomStateFixture({ seats_to_act: [1, 2] }), names, t)).toBe(
      'Waiting for the other players',
    )
    expect(waitingCaption(roomStateFixture({ seats_to_act: [1] }), {}, t)).toBe(
      'Waiting for the other player',
    )
  })
})
