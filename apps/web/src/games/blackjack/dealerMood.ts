import type { DealerMood } from './Dealer'

/** What the dealer says, as an i18n key with its values. */
export type DealerLine = { key: string; values?: Record<string, string | number> }

/**
 * The dealer's reaction to a round the server just settled, for the player watching: their net and hands as the
 * server scored them (presentation only — no rules here).
 */
export function roundMood(
  net: number | null,
  blackjack: boolean,
  dealerBust: boolean,
): { mood: DealerMood; line: DealerLine } {
  if (net === null) return { mood: 'neutral', line: { key: 'blackjack.say.next' } }
  if (net > 0 && blackjack) return { mood: 'wow', line: { key: 'blackjack.say.blackjack' } }
  if (net > 0 && dealerBust) return { mood: 'happy', line: { key: 'blackjack.say.dealerBust' } }
  if (net > 0) return { mood: 'happy', line: { key: 'blackjack.say.youWin' } }
  if (net < 0) return { mood: 'sorry', line: { key: 'blackjack.say.youLose' } }
  return { mood: 'neutral', line: { key: 'blackjack.say.push' } }
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
    line:
      name === null
        ? { key: `blackjack.say.tip${size}` }
        : { key: `blackjack.say.tip${size}Named`, values: { name } },
  }
}
