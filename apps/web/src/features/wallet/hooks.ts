import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useCurrentUser } from '@/features/auth/hooks'
import { useRealtimeConnected } from '@/shared/realtime/useRealtimeConnected'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { fetchWallet, walletHistoryKey, walletKey, type Wallet } from './api'

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
 */
export function useWalletSync() {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()

  useUserChannel(user?.id, {
    'wallet.updated': (payload: { balance: number; change: number }) => {
      queryClient.setQueryData<Wallet>(walletKey, (wallet) =>
        wallet ? { ...wallet, balance: payload.balance } : wallet,
      )
      void queryClient.invalidateQueries({ queryKey: walletKey })
      void queryClient.invalidateQueries({ queryKey: walletHistoryKey })
    },
  })
}
