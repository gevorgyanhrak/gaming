import { useEffect, useRef } from 'react'
import type { GameTraits } from '@/games/registry'
import type { RoomState } from './api'
import { playSound, unlockSounds, type Sound } from './sounds'

type Snapshot = Pick<RoomState, 'version' | 'seat' | 'seats_to_act' | 'view' | 'result'>

interface Context {
  traits: GameTraits
  /** Seats at the table: with more than two, turns come irregularly and get their own chime. */
  seats: number
  /** The page is in a background tab. */
  hidden: boolean
  /** This page watched the room wait for players, so the first state is the game starting. */
  sawWaiting: boolean
}

/**
 * The one sound (if any) that the step from `prev` to `next` deserves. Nothing on a first load or a
 * refetch of the same version; the game's end wins over everything, then the start of my turn
 * (when it could go unnoticed), then a capture, then a plain move.
 */
export function soundFor(prev: Snapshot | null, next: Snapshot, ctx: Context): Sound | null {
  const seat = next.seat
  if (seat === null) return null
  const myTurn = next.seats_to_act.includes(seat)

  if (!prev) return ctx.sawWaiting && myTurn ? 'yourTurn' : null
  if (next.version <= prev.version) return null

  if (next.result && !prev.result) {
    const outcome = next.result.seats[seat]
    return outcome === 'win' ? 'win' : outcome === 'draw' ? 'draw' : outcome ? 'loss' : null
  }
  if (myTurn && !prev.seats_to_act.includes(seat) && (ctx.hidden || ctx.seats > 2)) {
    return 'yourTurn'
  }
  return ctx.traits.capture?.(prev.view, next.view) ? 'capture' : 'move'
}

/**
 * Plays the game's sounds while the player is at the table and has sound switched on. Also warns
 * once per turn when the player's own time drops to 10 seconds.
 */
export function useGameSounds({
  roomId,
  state,
  waiting,
  seats,
  traits,
  enabled,
  secondsLeft,
}: {
  roomId: string
  state: RoomState | null | undefined
  waiting: boolean
  seats: number
  traits: GameTraits
  /** Sound is on in the player's settings and the player is still in the game. */
  enabled: boolean
  secondsLeft: number | null
}) {
  const on = useRef(enabled)
  const last = useRef<{ room: string; snapshot: Snapshot } | null>(null)
  const sawWaiting = useRef(false)
  const clock = useRef<{ deadline: string | null; seconds: number | null }>({
    deadline: null,
    seconds: null,
  })

  useEffect(() => {
    on.current = enabled
  }, [enabled])

  useEffect(() => {
    if (waiting) sawWaiting.current = true
  }, [waiting])

  // Audio may only start after a gesture; nothing is created while sound is off.
  useEffect(() => {
    const unlock = () => {
      if (on.current) unlockSounds()
    }
    window.addEventListener('pointerdown', unlock, true)
    window.addEventListener('keydown', unlock, true)
    return () => {
      window.removeEventListener('pointerdown', unlock, true)
      window.removeEventListener('keydown', unlock, true)
    }
  }, [])

  const version = state?.version
  useEffect(() => {
    if (!state) return
    const prev = last.current?.room === roomId ? last.current.snapshot : null
    if (prev && state.version <= prev.version) return
    last.current = { room: roomId, snapshot: state }
    const sound = soundFor(prev, state, {
      traits,
      seats,
      hidden: document.hidden,
      sawWaiting: sawWaiting.current,
    })
    if (sound && on.current) playSound(sound, traits.material)
    // Only a new version is a new event.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [roomId, version])

  const myTurn = state?.seat != null && state.seats_to_act.includes(state.seat)
  const deadline = state?.deadline ?? null
  useEffect(() => {
    const before = clock.current
    clock.current = { deadline, seconds: secondsLeft }
    if (!myTurn || secondsLeft === null || before.deadline !== deadline) return
    if (before.seconds !== null && before.seconds > 10 && secondsLeft <= 10 && on.current) {
      playSound('lowTime')
    }
  }, [deadline, secondsLeft, myTurn])
}
