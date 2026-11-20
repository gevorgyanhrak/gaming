import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useCurrentUser } from '@/features/auth/hooks'
import { useRealtimeConnected } from '@/shared/realtime/useRealtimeConnected'
import { playUiSound } from '@/shared/lib/sounds'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { fetchWallet, walletHistoryKey, walletKey } from './api'

/**
 * The signed-in player's Fishkas (null while signed out). Kept current by `wallet.updated` events;
 * polled while live updates aren't connected.
 */
export function useWallet() {
  const { data: user } = useCurrentUser()
  const live = useRealtimeConnected()
  return useQuery({
    queryKey: walletKey,
    queryFn: ({ signal }) => fetchWallet(signal),
    enabled: !!user,
    refetchOnWindowFocus: true,
    refetchInterval: live ? false : 5000,
  })
}

/**
 * Keeps the balance current in every open tab: the server announces each change ("wallet.updated")
 * on the player's channel; the summary (totals, bonus availability) and the history are refetched.
 * The event's balance isn't patched in on its own: the header shows it together with the chips at a
 * table, and a buy-in or cash-out moves Fishkas between the two, so only the summary is consistent.
 */
export function useWalletSync() {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()

  useUserChannel(user?.id, {
    'wallet.updated': (payload: { change?: number }) => {
      // Fishkas coming in clink (when sounds are on).
      if ((payload?.change ?? 0) > 0) playUiSound('chips')
      void queryClient.invalidateQueries({ queryKey: walletKey })
      void queryClient.invalidateQueries({ queryKey: walletHistoryKey })
    },
  })
}

const ZERO_PROMPT_KEY = 'fishka:zero-prompted'

/**
 * When the player's Fishkas reach zero (none in the wallet, none at a table), the "out of Fishkas" popup offers the
 * free top-up and the chip packages (HayBet item 6) — once a session, never over a game in progress (`paused`).
 */
export function useZeroBalancePrompt(paused: boolean, open: () => void) {
  const wallet = useWallet()
  const empty = wallet.data ? wallet.data.balance + wallet.data.at_table === 0 : false
  useEffect(() => {
    if (!empty || paused) return
    let shown = false
    try {
      shown = sessionStorage.getItem(ZERO_PROMPT_KEY) === '1'
      sessionStorage.setItem(ZERO_PROMPT_KEY, '1')
    } catch {
      // Storage blocked: the popup may come back on the next empty balance; nothing else depends on it.
    }
    if (!shown) open()
  }, [empty, paused, open])
}
