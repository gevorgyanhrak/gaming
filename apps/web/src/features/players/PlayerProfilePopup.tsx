import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { AchievementBadge } from '@/features/achievements/AchievementBadge'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { RankEmblem } from '@/features/levels/components/RankEmblem'
import { tierOf } from '@/features/levels/ranks'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { AiTag } from '@/shared/ui/AiTag'
import { Avatar } from '@/shared/ui/Avatar'
import { Modal } from '@/shared/ui/Modal'
import { Skeleton } from '@/shared/ui/Skeleton'
import { CloseIcon } from '@/shared/ui/icons'
import { usePlayerCard } from './api'

/** Who was tapped: a seat's participant (people have a username; AI has none). */
export interface PopupPlayer {
  name: string
  username: string | null
  is_bot: boolean
  level: number
  avatar_url: string | null
}

/**
 * A player's card over the table (tapping their avatar): avatar, name, rank and level progress, Fishkas, record and
 * win rate, biggest win, favourite game, streak, latest achievements, online and what they play now — from the
 * server (GET /users/{username}/card). AI gets a short card marked AI. Escape, the backdrop or × closes it; the
 * game goes on meanwhile. No gifts: Fishkas are never sent between players.
 */
export function PlayerProfilePopup({
  player,
  onClose,
}: {
  player: PopupPlayer
  onClose: () => void
}) {
  const { t, i18n } = useTranslation()
  const titleId = useId()
  const card = usePlayerCard(player.is_bot ? null : player.username)
  const format = (n: number) => n.toLocaleString(i18n.language)
  const c = card.data
  const share =
    c && c.next_level_xp !== null
      ? Math.min(1, (c.xp - c.level_xp) / Math.max(1, c.next_level_xp - c.level_xp))
      : 1

  const stat = (label: string, value: string) => (
    <div className="rounded-xl bg-black/30 px-2 py-1.5 text-center">
      <dt className="text-[0.7rem] text-muted">{label}</dt>
      <dd className="text-base font-extrabold tabular-nums">{value}</dd>
    </div>
  )

  return (
    <Modal
      onClose={onClose}
      labelledBy={titleId}
      size="sm"
      sheetOnPhone
      className="motion-safe:animate-rise"
    >
      <div className="relative space-y-3">
        <button
          type="button"
          onClick={onClose}
          aria-label={t('wins.close')}
          className="absolute top-0 right-0 flex size-9 items-center justify-center rounded-full border border-white/20 text-white/70 hover:text-white"
        >
          <CloseIcon className="size-4" />
        </button>

        <header className="flex items-center gap-3 pr-10">
          <span className="relative">
            <Avatar url={player.avatar_url} name={player.name} level={player.level} size="md" />
            {c?.online ? (
              <span
                className="absolute right-0 bottom-0 size-3.5 rounded-full border-2 border-emerald-900 bg-[#3ccf6e]"
                title={t('playerCard.online')}
              />
            ) : null}
          </span>
          <div className="min-w-0">
            <h2 id={titleId} className="flex items-center gap-1.5 truncate text-xl font-bold">
              {player.name} {player.is_bot ? <AiTag /> : null}
            </h2>
            <p className="flex items-center gap-1.5 text-sm text-gold-light">
              <RankEmblem level={player.level} size={20} />
              {t(`ranks.${tierOf(player.level).key}`)} ·{' '}
              {t('playerCard.level', { level: player.level })}
            </p>
            {c ? (
              <p className="text-xs text-muted">
                {c.playing
                  ? t('playerCard.playing', { game: c.playing.name })
                  : c.online
                    ? t('playerCard.online')
                    : t('playerCard.offline')}
              </p>
            ) : null}
          </div>
        </header>

        {player.is_bot ? (
          <p className="rounded-xl bg-black/30 px-3 py-2 text-sm text-fg/85">
            {t('playerCard.ai')}
          </p>
        ) : card.isPending ? (
          <div className="space-y-2">
            <Skeleton className="h-3 rounded-full" />
            <Skeleton className="h-16 rounded-xl" />
          </div>
        ) : card.isError || !c ? (
          <p className="text-sm text-muted">{t('playerCard.error')}</p>
        ) : (
          <>
            <div>
              <div className="mb-1 flex justify-between text-xs text-muted">
                <span>{t('playerCard.xp')}</span>
                <span className="flex items-center gap-1 font-bold text-gold-light">
                  <FishkaIcon className="size-4" />
                  {format(c.balance)}
                </span>
              </div>
              <div
                role="progressbar"
                aria-label={t('playerCard.xp')}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={Math.round(share * 100)}
                className="h-2 overflow-hidden rounded-full bg-black/40"
              >
                <div
                  className="h-full rounded-full bg-linear-to-r from-[#b8862e] to-[#fbe7a6]"
                  style={{ width: `${Math.max(3, share * 100)}%` }}
                />
              </div>
            </div>

            <dl className="grid grid-cols-4 gap-1.5">
              {stat(t('playerCard.played'), format(c.played))}
              {stat(t('playerCard.wins'), format(c.wins))}
              {stat(t('playerCard.losses'), format(c.losses))}
              {stat(t('playerCard.winRate'), c.win_rate === null ? '—' : `${c.win_rate}%`)}
            </dl>

            <dl className="space-y-1 text-sm">
              {c.biggest_win ? (
                <div className="flex items-center justify-between gap-2">
                  <dt className="text-muted">{t('playerCard.biggestWin')}</dt>
                  <dd className="flex items-center gap-1 font-bold text-gold-light">
                    +{format(c.biggest_win.amount)}
                    <FishkaIcon className="size-4" />
                    {c.biggest_win.game ? (
                      <span className="font-normal text-muted">· {c.biggest_win.game.name}</span>
                    ) : null}
                  </dd>
                </div>
              ) : null}
              {c.favourite_game ? (
                <div className="flex items-center justify-between gap-2">
                  <dt className="text-muted">{t('playerCard.favourite')}</dt>
                  <dd className="flex items-center gap-1 font-semibold">
                    <GameIcon
                      slug={c.favourite_game.slug}
                      name={c.favourite_game.name}
                      className="size-4 text-gold"
                    />
                    {c.favourite_game.name}
                  </dd>
                </div>
              ) : null}
              {c.streak > 1 ? (
                <div className="flex items-center justify-between gap-2">
                  <dt className="text-muted">{t('playerCard.streak')}</dt>
                  <dd className="font-bold text-[#ff8a7d]">🔥 {c.streak}</dd>
                </div>
              ) : null}
            </dl>

            {c.achievements.length > 0 ? (
              <ul aria-label={t('achievements.title')} className="flex flex-wrap gap-1.5">
                {c.achievements.map((a, i) => (
                  <li key={`${a.key}-${i}`}>
                    <AchievementBadge achievement={a} size="sm" />
                  </li>
                ))}
              </ul>
            ) : null}

            <Link
              to={`/u/${c.username}`}
              onClick={onClose}
              className="flex min-h-11 items-center justify-center rounded-control border border-gold/40 text-sm font-semibold text-gold-light hover:bg-gold/10"
            >
              {t('playerCard.fullProfile')}
            </Link>
          </>
        )}
      </div>
    </Modal>
  )
}
