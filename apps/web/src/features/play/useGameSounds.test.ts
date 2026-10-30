import { describe, expect, it } from 'vitest'
import { traitsOf } from '@/games/registry'
import { roomStateFixture } from '@/test/handlers'
import { soundFor } from './useGameSounds'

const wood = traitsOf('chess')
const ctx = { traits: wood, seats: 2, hidden: false, sawWaiting: false }
const state = roomStateFixture

describe('soundFor', () => {
  it('stays quiet on a first load, unless this page saw the game start', () => {
    expect(soundFor(null, state(), ctx)).toBeNull()
    expect(soundFor(null, state(), { ...ctx, sawWaiting: true })).toBe('yourTurn')
    expect(soundFor(null, state({ seats_to_act: [1] }), { ...ctx, sawWaiting: true })).toBeNull()
  })

  it('stays quiet when the version did not advance', () => {
    expect(soundFor(state({ version: 3 }), state({ version: 3 }), ctx)).toBeNull()
    expect(soundFor(state({ version: 3 }), state({ version: 2 }), ctx)).toBeNull()
  })

  it('stays quiet for a spectator view without a seat', () => {
    expect(soundFor(state(), state({ version: 1, seat: null }), ctx)).toBeNull()
  })

  it('plays the result when the game ends, and nothing else', () => {
    const end = (seats: ('win' | 'loss' | 'draw' | 'abandoned' | null)[]) =>
      soundFor(
        state({ version: 1 }),
        state({ version: 2, result: { end_reason: 'x', seats } }),
        ctx,
      )
    expect(end(['win', 'loss'])).toBe('win')
    expect(end(['draw', 'draw'])).toBe('draw')
    expect(end(['loss', 'win'])).toBe('loss')
    expect(end(['abandoned', 'win'])).toBe('loss')
  })

  it('chimes for my turn in a hidden tab or at a bigger table; a move sound otherwise', () => {
    const prev = state({ version: 1, seats_to_act: [1] })
    const next = state({ version: 2, seats_to_act: [0] })
    expect(soundFor(prev, next, { ...ctx, hidden: true })).toBe('yourTurn')
    expect(soundFor(prev, next, { ...ctx, seats: 4 })).toBe('yourTurn')
    expect(soundFor(prev, next, ctx)).toBe('move')
  })

  it('plays a capture where the game shows one', () => {
    const board = (...pieces: (string | null)[]) => ({ board: pieces })
    expect(
      soundFor(
        state({ version: 1, view: board('Q', 'p') }),
        state({ version: 2, view: board(null, 'Q') }),
        ctx,
      ),
    ).toBe('capture')
    expect(
      soundFor(
        state({ version: 1, view: board('Q', null) }),
        state({ version: 2, view: board(null, 'Q') }),
        ctx,
      ),
    ).toBe('move')
  })
})

describe('capture checks', () => {
  it('checkers: a piece jumped or removed', () => {
    const capture = traitsOf('checkers').capture!
    expect(capture({ board: ['w', 'b'], captured: [] }, { board: ['w', 'b'], captured: [1] })).toBe(
      true,
    )
    expect(
      capture({ board: ['w', null], captured: [] }, { board: [null, 'w'], captured: [] }),
    ).toBe(false)
  })

  it('nardi: a checker sent to the bar', () => {
    const capture = traitsOf('nardi').capture!
    expect(capture({ bar: [0, 0] }, { bar: [0, 1] })).toBe(true)
    expect(capture({ bar: [1, 0] }, { bar: [0, 0] })).toBe(false)
  })

  it('durak: the defender takes', () => {
    const capture = traitsOf('durak').capture!
    expect(capture({ taking: false }, { taking: true })).toBe(true)
    expect(capture({ taking: true }, { taking: true })).toBe(false)
  })

  it('poker has none, and cards sound like cards', () => {
    expect(traitsOf('poker').capture).toBeUndefined()
    expect(traitsOf('poker').material).toBe('cards')
    expect(traitsOf('unknown')).toEqual({ seatsOnBoard: false, material: 'wood' })
  })
})

describe('poker hands', () => {
  const poker = traitsOf('poker')
  const hand = (number: number, winners: number[]) => ({
    last_hand: { number, pots: [{ amount: 50, winners }] },
  })
  type View = Record<string, unknown>
  const step = (before: View, after: View, extra: object = {}) =>
    soundFor(
      { version: 1, seat: 0, seats_to_act: [], view: before, result: null },
      { version: 2, seat: 0, seats_to_act: [], view: after, result: null, ...extra },
      { traits: poker, seats: 3, hidden: false, sawWaiting: false },
    )

  it('plays the hand win when I take a pot, nothing special when someone else does', () => {
    expect(step(hand(1, [1]), hand(2, [0, 2]))).toBe('handWin')
    expect(step(hand(1, [0]), hand(2, [1]))).toBe('move')
    expect(step(hand(2, [0]), hand(2, [0]))).toBe('move')
  })

  it('plays only the game result when the hand ends the game', () => {
    expect(step(hand(1, [1]), hand(2, [0]), { result: { seats: { 0: 'win' } } })).toBe('win')
  })
})
