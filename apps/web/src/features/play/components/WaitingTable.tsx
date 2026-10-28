import { useTranslation } from 'react-i18next'
import { Avatar } from '@/shared/ui/Avatar'
import { Button } from '@/shared/ui/Button'
import { FriendsIcon } from '@/shared/ui/icons'
import type { Room } from '../api'

/**
 * A private room before the game: the oval table with a seat for everyone (empty ones still
 * waiting), and in the middle the invite code to share.
 */
export function WaitingTable({
  room,
  mySeat,
  onLeave,
}: {
  room: Room
  mySeat: number
  onLeave: () => void
}) {
  const { t } = useTranslation()
  const bySeat = new Map(room.participants.map((p) => [p.seat, p]))

  return (
    <section className="px-9 py-10 sm:px-12 sm:py-16">
      <div className="relative mx-auto aspect-[3/4] w-full max-w-4xl sm:aspect-[2/1]">
        <div aria-hidden="true" className="table-rim absolute inset-0 rounded-[50%]">
          <div className="table-felt h-full w-full rounded-[50%]" />
        </div>

        {/* Seats round the rim, yours at the bottom. */}
        <ul aria-label={t('room.players')}>
          {Array.from({ length: room.seats }, (_, seat) => {
            const angle = Math.PI / 2 + ((seat - mySeat) * 2 * Math.PI) / room.seats
            const player = bySeat.get(seat)
            return (
              <li
                key={seat}
                className="absolute flex -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-1.5 motion-safe:animate-rise"
                style={{
                  left: `${50 + 50 * Math.cos(angle)}%`,
                  top: `${50 + 50 * Math.sin(angle)}%`,
                  animationDelay: `${seat * 90}ms`,
                }}
              >
                <span
                  className={`flex size-16 items-center justify-center overflow-hidden rounded-full border-2 sm:size-24 ${
                    player
                      ? 'border-gold bg-surface-raised shadow-lg shadow-black/50'
                      : 'border-gold/40 bg-linear-to-b from-[#5a4038]/90 to-[#2e1f1a]/90 shadow-inner'
                  }`}
                >
                  {player ? (
                    <Avatar url={player.avatar_url} name={player.name ?? '?'} size="md" />
                  ) : (
                    <span className="sr-only">{t('room.emptySeat')}</span>
                  )}
                </span>
                {player ? (
                  <span className="max-w-28 truncate rounded-lg border border-gold/30 bg-surface/90 px-3 py-0.5 text-sm font-semibold">
                    {player.name}
                  </span>
                ) : null}
              </li>
            )
          })}
        </ul>

        <div className="absolute inset-0 flex items-center justify-center p-6">
          <div className="club-frame w-full max-w-md rounded-3xl p-5 text-center motion-safe:animate-rise sm:p-6">
            <div className="flex items-center gap-3">
              <span className="club-medal flex size-12 shrink-0 items-center justify-center rounded-full text-[#3a2410]">
                <FriendsIcon className="size-6" />
              </span>
              <h2 className="flex-1 text-xl font-extrabold">{t('room.waitingTitle')}</h2>
              <span aria-hidden="true" className="flex gap-1">
                {[0, 1, 2].map((i) => (
                  <span
                    key={i}
                    className="size-1.5 rounded-full bg-gold motion-safe:animate-pulse"
                    style={{ animationDelay: `${i * 200}ms` }}
                  />
                ))}
              </span>
            </div>
            <hr className="my-4 border-gold/20" />
            <p className="text-sm text-muted">{t('room.waiting')}</p>
            {room.invite_code ? (
              <>
                <p className="sr-only">{t('room.inviteCode')}</p>
                <p className="mt-2 font-mono text-3xl font-bold tracking-[0.3em] text-gold sm:text-4xl">
                  {room.invite_code}
                </p>
              </>
            ) : null}
            <div className="mt-4 flex flex-wrap justify-center gap-2">
              {room.invite_code ? (
                <Button
                  variant="gold"
                  onClick={() => void navigator.clipboard?.writeText(room.invite_code ?? '')}
                >
                  {t('room.copyCode')}
                </Button>
              ) : null}
              <Button variant="secondary" onClick={onLeave}>
                {t('room.cancel')}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}
