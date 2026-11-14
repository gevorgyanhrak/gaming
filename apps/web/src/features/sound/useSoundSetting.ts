import { useEffect, useState } from 'react'
import { updateSettings, useAccountMutation } from '@/features/account/api'
import { useCurrentUser } from '@/features/auth/hooks'
import { setUiSounds, unlockSounds } from '@/shared/lib/sounds'

const KEY = 'sound'

function readGuest(): boolean {
  try {
    return window.localStorage.getItem(KEY) === '1'
  } catch {
    return false
  }
}

/**
 * Sounds on or off: a player's account preference (the same as Settings), a guest's choice kept in this browser
 * (off until they turn it on). Keeps the casino UI sounds in step, and — since browsers only play audio after a
 * gesture — unlocks audio on the first tap while sounds are on.
 */
export function useSoundSetting() {
  const { data: user } = useCurrentUser()
  const mutation = useAccountMutation(updateSettings)
  const [guest, setGuest] = useState(readGuest)
  const on = user ? Boolean(user.profile.preferences.sound) : guest

  useEffect(() => {
    setUiSounds(on)
    if (!on) return
    const unlock = () => unlockSounds()
    window.addEventListener('pointerdown', unlock, { once: true })
    return () => window.removeEventListener('pointerdown', unlock)
  }, [on])

  const set = (next: boolean) => {
    if (next) unlockSounds() // this click is the gesture that lets the browser play
    if (user) {
      mutation.mutate({ preferences: { sound: next } })
    } else {
      setGuest(next)
      try {
        window.localStorage.setItem(KEY, next ? '1' : '0')
      } catch {
        // A blocked store only means the choice isn't kept.
      }
    }
    setUiSounds(next)
  }

  return { on, set, pending: mutation.isPending }
}
