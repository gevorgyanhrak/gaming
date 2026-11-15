import type { DealerMood } from './Dealer'

/** What the dealer says, as an i18n key with its values. */
export type DealerLine = { key: string; values?: Record<string, string | number> }

/** Each situation has a few short lines (`blackjack.say.<situation>.<n>`). */
const VARIANTS = 3
const lastSaid = new Map<string, number>()

/** One of the situation's lines, never the one it said last time. */
export function line(situation: string, values?: Record<string, string | number>): DealerLine {
  const last = lastSaid.get(situation)
  let n = Math.floor(Math.random() * VARIANTS)
  if (n === last) n = (n + 1 + Math.floor(Math.random() * (VARIANTS - 1))) % VARIANTS
  lastSaid.set(situation, n)
  return { key: `blackjack.say.${situation}.${n}`, values }
}

/**
 * The dealer's reaction to a round the server just settled, for the player watching: their net and hands as the
 * server scored them (presentation only — no rules here).
 */
export function roundMood(
  net: number | null,
  blackjack: boolean,
  dealerBust: boolean,
  /** All of the player's hands went over 21. */
  busted = false,
): { mood: DealerMood; line: DealerLine } {
  if (net === null) return { mood: 'neutral', line: line('next') }
  if (net > 0 && blackjack) return { mood: 'wow', line: line('blackjack') }
  if (net > 0 && dealerBust) return { mood: 'happy', line: line('dealerBust') }
  if (net > 0) return { mood: 'happy', line: line('youWin') }
  if (net < 0 && busted) return { mood: 'sorry', line: line('youBust') }
  if (net < 0) return { mood: 'sorry', line: line('youLose') }
  return { mood: 'neutral', line: line('push') }
}

/**
 * The dealer's thanks for a tip, warmer the bigger it is against what the round won: a token, a nice tip, a
 * generous one (half the win or more).
 */
export function tipMood(
  amount: number,
  won: number,
  /** Another player's name; null when the tip is yours (the dealer speaks to you). */
  name: string | null,
): { mood: DealerMood; line: DealerLine } {
  const share = won > 0 ? amount / won : 0
  const size = share >= 0.5 ? 'Big' : share >= 0.15 ? 'Nice' : 'Small'
  return {
    mood: size === 'Big' ? 'delighted' : 'thanks',
    line: name === null ? line(`tip${size}`) : line(`tip${size}Named`, { name }),
  }
}
