import blackjack from '@/assets/themes/blackjack.svg'
import blot from '@/assets/themes/blot.svg'
import checkers from '@/assets/themes/checkers.svg'
import chess from '@/assets/themes/chess.svg'
import domino from '@/assets/themes/domino.svg'
import durak from '@/assets/themes/durak.svg'
import ludo from '@/assets/themes/ludo.svg'
import nardi from '@/assets/themes/nardi.svg'
import poker from '@/assets/themes/poker.svg'
import pool from '@/assets/themes/pool.svg'
import rummy from '@/assets/themes/rummy.svg'

/**
 * How a game's screens look around the board (its lobby page and its tables): a background picture of the
 * game's own room, how it's placed, an overlay that keeps cards, avatars and text readable on it, and an
 * accent colour for the frame round the table bar. GameBackdrop applies it; a game without one keeps the
 * club's room.
 */
export interface GameTheme {
  /** The picture (an SVG scene in assets/themes, drawn for the game). */
  image: string
  /** CSS background-position / background-size of the picture. */
  position: string
  size: string
  /** Gradients over the picture, darkest where the controls sit. */
  overlay: string
  /** The game's accent (rgb), for edges and glows. */
  accent: string
}

/** Darker at the edges and the bottom, where the hand and buttons are. */
const shade = (top: number, bottom: number) =>
  `radial-gradient(ellipse at 50% 40%, rgb(0 0 0 / 0) 35%, rgb(0 0 0 / 0.55) 100%), linear-gradient(to bottom, rgb(0 0 0 / ${top}), rgb(0 0 0 / ${bottom}))`

const scene = (image: string, accent: string, top = 0.15, bottom = 0.5): GameTheme => ({
  image,
  position: 'center top',
  size: 'cover',
  overlay: shade(top, bottom),
  accent,
})

export const themes: Partial<Record<string, GameTheme>> = {
  blot: scene(blot, 'rgb(232 184 106)'),
  poker: scene(poker, 'rgb(224 178 74)', 0.2, 0.55),
  checkers: scene(checkers, 'rgb(207 230 255)', 0.1),
  chess: scene(chess, 'rgb(227 208 172)', 0.1),
  nardi: scene(nardi, 'rgb(240 194 122)'),
  durak: scene(durak, 'rgb(255 184 92)'),
  blackjack: scene(blackjack, 'rgb(232 198 106)'),
  pool: scene(pool, 'rgb(169 214 120)'),
  domino: scene(domino, 'rgb(244 234 216)'),
  ludo: scene(ludo, 'rgb(226 182 50)', 0.25, 0.55),
  rummy: scene(rummy, 'rgb(232 198 106)'),
}

export const themeOf = (slug: string): GameTheme | undefined => themes[slug]
