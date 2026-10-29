import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Avatar } from '@/shared/ui/Avatar'
import { Button } from '@/shared/ui/Button'
import { FriendsIcon, LinkIcon, ShareIcon } from '@/shared/ui/icons'
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
  const code = room.invite_code
  const link = code ? `${window.location.origin}/join/${code}` : ''
  const canShare = typeof navigator.share === 'function'
  // Which button just copied (its label says so for a moment), and the link to copy by hand if
  // the clipboard isn't available.
  const [copied, setCopied] = useState<'link' | 'code' | null>(null)
  const [manual, setManual] = useState(false)
  const field = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!copied) return
    const timer = window.setTimeout(() => setCopied(null), 2000)
    return () => window.clearTimeout(timer)
  }, [copied])

  useEffect(() => {
    if (manual) field.current?.select()
  }, [manual])

  const copy = async (what: 'link' | 'code') => {
    try {
      await navigator.clipboard.writeText(what === 'link' ? link : (code ?? ''))
      setCopied(what)
    } catch {
      setManual(true)
    }
  }

  const share = async () => {
    try {
      await navigator.share({
        title: t('join.title'),
        text: t('room.shareText', { game: room.game.name }),
        url: link,
      })
    } catch (error) {
      // Closing the share sheet isn't a failure; anything else falls back to copying.
      if (!(error instanceof DOMException && error.name === 'AbortError')) await copy('link')
    }
  }

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
            {code ? (
              <>
                <p className="sr-only">{t('room.inviteCode')}</p>
                <p className="mt-2 font-mono text-3xl font-bold tracking-[0.3em] text-gold sm:text-4xl">
                  {code}
                </p>
                <button
                  type="button"
                  onClick={() => void copy('code')}
                  className="min-h-11 text-sm font-medium text-accent-text hover:underline"
                >
                  {copied === 'code' ? t('room.codeCopied') : t('room.copyCode')}
                </button>
                <Button
                  variant="gold"
                  className="mt-1 w-full"
                  onClick={() => void (canShare ? share() : copy('link'))}
                >
                  {canShare ? <ShareIcon className="size-4" /> : <LinkIcon className="size-4" />}
                  {canShare
                    ? t('room.share')
                    : copied === 'link'
                      ? t('room.linkCopied')
                      : t('room.copyLink')}
                </Button>
                {manual ? (
                  <label className="mt-3 block text-left text-xs text-muted">
                    {t('room.copyFallback')}
                    <input
                      ref={field}
                      readOnly
                      value={link}
                      onFocus={(event) => event.currentTarget.select()}
                      className="mt-1 w-full rounded-lg border border-border bg-bg px-3 py-2 text-sm text-fg"
                    />
                  </label>
                ) : null}
                <p role="status" className="sr-only">
                  {copied === 'link'
                    ? t('room.linkCopied')
                    : copied === 'code'
                      ? t('room.codeCopied')
                      : ''}
                </p>
              </>
            ) : null}
          </div>
        </div>
      </div>

      <div className="mt-16 flex flex-wrap justify-center gap-2 sm:mt-20">
        {code && canShare ? (
          <Button variant="secondary" onClick={() => void copy('link')}>
            {copied === 'link' ? t('room.linkCopied') : t('room.copyLink')}
          </Button>
        ) : null}
        <Button variant="secondary" onClick={onLeave}>
          {t('room.cancel')}
        </Button>
      </div>
    </section>
  )
}
