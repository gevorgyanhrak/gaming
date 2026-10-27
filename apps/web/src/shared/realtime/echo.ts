import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { apiRequest } from '@/shared/api/http'
import { env } from '@/shared/config/env'

let instance: Echo<'reverb'> | null = null

/**
 * The shared WebSocket connection (Laravel Reverb), or null when it isn't configured.
 * Private channels are authorized through the API with the session cookie and CSRF token.
 */
export function getEcho(): Echo<'reverb'> | null {
  if (!env.reverb.key) return null

  instance ??= new Echo({
    broadcaster: 'reverb',
    Pusher,
    key: env.reverb.key,
    wsHost: env.reverb.host,
    wsPort: env.reverb.port,
    wssPort: env.reverb.port,
    forceTLS: env.reverb.tls,
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: (
        socketId: string,
        callback: (error: Error | null, authData: { auth: string } | null) => void,
      ) => {
        apiRequest<{ auth: string }>('/broadcasting/auth', {
          method: 'POST',
          body: { socket_id: socketId, channel_name: channel.name },
        }).then(
          (data) => callback(null, data),
          (error: unknown) =>
            callback(
              error instanceof Error ? error : new Error('Channel authorization failed'),
              null,
            ),
        )
      },
    }),
  })

  return instance
}

/** Closes the connection (e.g. on logout), so the next user gets a fresh one. */
export function disconnectEcho() {
  instance?.disconnect()
  instance = null
}
