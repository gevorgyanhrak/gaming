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
  /** Public channels work the same way here. */
  channel(name: string) {
    return fakeEcho.private(name)
  },
  /** Presence channels: joined while a page is open (see joined()). */
  join(name: string) {
    present.add(name)
    return fakeEcho.private(`presence-${name}`)
  },
  leave(name: string) {
    present.delete(name)
  },
  disconnect() {},
}

const present = new Set<string>()

/** The presence channels joined right now, e.g. 'online'. */
export function joined(name: string) {
  return present.has(name)
}

/** Deliver a server event, e.g. emit('user.1', 'room.updated', {...}). */
export function emit(channel: string, event: string, payload: unknown) {
  channels.get(channel)?.get(`.${event}`)?.(payload)
}

export function resetChannels() {
  channels.clear()
  present.clear()
}
