import type { ParticipantResult, RoomStatus } from './api'

// Typed translation keys for values that come from the API.

export const resultLabel = {
  win: 'room.result.win',
  loss: 'room.result.loss',
  draw: 'room.result.draw',
  abandoned: 'room.result.abandoned',
} as const satisfies Record<ParticipantResult, string>

export const outcomeLabel = {
  win: 'room.outcome.win',
  loss: 'room.outcome.loss',
  draw: 'room.outcome.draw',
  abandoned: 'room.outcome.abandoned',
} as const satisfies Record<ParticipantResult, string>

export const statusLabel = {
  waiting: 'room.status.waiting',
  active: 'room.status.active',
  finished: 'room.status.finished',
  aborted: 'room.status.aborted',
} as const satisfies Record<RoomStatus, string>

export const endReasonLabel: Record<
  string,
  | 'room.endReason.completed'
  | 'room.endReason.resign'
  | 'room.endReason.timeout'
  | 'room.endReason.abandon'
> = {
  completed: 'room.endReason.completed',
  resign: 'room.endReason.resign',
  timeout: 'room.endReason.timeout',
  abandon: 'room.endReason.abandon',
}

/** The end reason in a few words, for lists. */
export const endReasonShort: Record<
  string,
  | 'room.endReasonShort.completed'
  | 'room.endReasonShort.resign'
  | 'room.endReasonShort.timeout'
  | 'room.endReasonShort.abandon'
> = {
  completed: 'room.endReasonShort.completed',
  resign: 'room.endReasonShort.resign',
  timeout: 'room.endReasonShort.timeout',
  abandon: 'room.endReasonShort.abandon',
}

/** How each result looks as a badge. */
export const resultTone = {
  win: 'success',
  loss: 'danger',
  draw: 'neutral',
  abandoned: 'warning',
} as const satisfies Record<ParticipantResult, string>
