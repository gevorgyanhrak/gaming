// In-memory stand-in for the Echo connection: tests push events with emit().

type Listener = (payload: unknown) => void

const channels = new Map<string, Map<string, Listener>>()

export const fakeEcho = {
  private(name: string) {
    const listeners = channels.get(name) ?? new Map<string, Listener>()
    channels.set(name, listeners)
    const channel = {
      listen(event: string, callback: Listener) {
        listeners.set(event, callback)
        return channel
      },
      stopListening(event: string) {
        listeners.delete(event)
        return channel
      },
    }
    return channel
  },
  disconnect() {},
}

/** Deliver a server event, e.g. emit('user.1', 'room.updated', {...}). */
export function emit(channel: string, event: string, payload: unknown) {
  channels.get(channel)?.get(`.${event}`)?.(payload)
}

export function resetChannels() {
  channels.clear()
}
