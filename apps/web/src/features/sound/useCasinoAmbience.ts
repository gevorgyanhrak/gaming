import { useEffect } from 'react'
import { playUiSound, setAmbience, unlockSounds } from '@/shared/lib/sounds'
import { useSoundSetting } from './useSoundSetting'

const WELCOMED_KEY = 'sound:welcomed'

/**
 * The casino's sound around the games (HayBet item 8): with sounds on, the first tap (browsers allow audio only
 * after a gesture) plays the welcome chime once a session and starts the quiet ambience; it pauses at a game table
 * (`allowed` false: the table has its own sounds) and stops when sounds are switched off.
 */
export function useCasinoAmbience(allowed: boolean) {
  const { on } = useSoundSetting()

  useEffect(() => {
    if (!on || !allowed) {
      setAmbience(false)
      return
    }
    const start = () => {
      unlockSounds()
      // The context resumes asynchronously after the gesture; start a moment later.
      window.setTimeout(() => {
        setAmbience(true)
        let welcomed = false
        try {
          welcomed = sessionStorage.getItem(WELCOMED_KEY) === '1'
          sessionStorage.setItem(WELCOMED_KEY, '1')
        } catch {
          // Storage blocked: the chime may play again next session start; harmless.
        }
        if (!welcomed) playUiSound('welcome')
      }, 120)
    }
    // Already unlocked (sounds were on before): start now; otherwise on the first tap or key.
    start()
    window.addEventListener('pointerdown', start, { once: true })
    window.addEventListener('keydown', start, { once: true })
    return () => {
      window.removeEventListener('pointerdown', start)
      window.removeEventListener('keydown', start)
      setAmbience(false)
    }
  }, [on, allowed])
}
