import { useRef } from 'react'
import { useFitHeight } from './useFitHeight'

/**
 * The width for a square board (chess, checkers) whose bottom edge must stay on screen: the height
 * from the board's top to the bottom of the screen (above the phone tab bar), between 17.5rem and 38rem.
 * Whatever sits above it (the room header, the table chat, captions) is measured, not guessed. Until
 * measured, the board keeps its CSS cap. Returns the ref for the board's frame and the width style.
 */
export function useBoardFit() {
  const boardRef = useRef<HTMLDivElement>(null)
  const height = useFitHeight(boardRef, { min: 280 })
  const boardStyle =
    height === null ? undefined : { maxWidth: `${Math.min(608, Math.max(280, height))}px` }
  return [boardRef, boardStyle] as const
}
