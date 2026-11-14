import { useTranslation } from 'react-i18next'
import { playUiSound } from '@/shared/lib/sounds'
import { SoundOffIcon, SoundOnIcon } from '@/shared/ui/icons'
import { useSoundSetting } from './useSoundSetting'

/** The speaker button: sounds on or off, in one tap (the header and the table bar). */
export function SoundToggle({ className = '' }: { className?: string }) {
  const { t } = useTranslation()
  const { on, set, pending } = useSoundSetting()
  return (
    <button
      type="button"
      aria-pressed={on}
      aria-label={t('sound.toggle')}
      title={on ? t('sound.on') : t('sound.off')}
      disabled={pending}
      onClick={() => {
        set(!on)
        if (!on) window.setTimeout(() => playUiSound('chips'), 60)
      }}
      className={`flex size-10 shrink-0 items-center justify-center rounded-full border border-gold/30 text-gold-light transition hover:border-gold/70 hover:bg-black/30 aria-pressed:bg-gold/15 ${className}`}
    >
      {on ? <SoundOnIcon /> : <SoundOffIcon />}
    </button>
  )
}
