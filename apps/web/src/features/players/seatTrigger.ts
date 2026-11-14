/**
 * Props that make a seat's avatar open that player's card (PlayerProfilePopup): RoomPage listens for clicks and
 * Enter/Space on `[data-player-seat]` anywhere on the table, so each board only marks its avatars.
 */
export function seatTrigger(seat: number, label: string) {
  return {
    'data-player-seat': seat,
    role: 'button' as const,
    tabIndex: 0,
    'aria-label': label,
    className: 'cursor-pointer rounded-full focus-visible:outline-2 focus-visible:outline-gold',
  }
}
