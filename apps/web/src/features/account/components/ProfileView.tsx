import { PlayerAchievements } from '@/features/achievements/PlayerAchievements'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { badgeLabel } from '@/features/levels/items'
import { countryName } from '@/shared/lib/countries'
import { Avatar } from '@/shared/ui/Avatar'
import type { PublicUser } from '../api'

/** A player's public profile card; `actions` shows owner-only controls. */
export function ProfileView({ user, actions }: { user: PublicUser; actions?: ReactNode }) {
  const { t, i18n } = useTranslation()
  const name = user.display_name ?? user.username
  const date = (iso: string) =>
    new Intl.DateTimeFormat(i18n.language, { dateStyle: 'long' }).format(new Date(iso))

  return (
    <article className="rounded-2xl border border-border bg-surface p-6 sm:p-8">
      <title>{name}</title>
      <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
        <Avatar
          url={user.avatar_url}
          name={name}
          size="lg"
          level={user.level}
          frame={user.looks.frame}
        />
        <div className="min-w-0 flex-1">
          <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
            <h1 className="truncate text-2xl font-bold">{name}</h1>
            {user.looks.badge ? (
              <span className="inline-flex items-center gap-1 rounded-full border border-gold/50 bg-gold/15 px-2.5 py-0.5 text-xs font-bold text-gold-light">
                <span aria-hidden="true">★</span>
                {badgeLabel(t, user.looks.badge)}
              </span>
            ) : null}
          </div>
          <p className="text-muted">@{user.username}</p>
          <dl className="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted">
            {user.country ? (
              <div className="flex gap-1.5">
                <dt>{t('profile.country')}:</dt>
                <dd className="text-fg">{countryName(user.country, i18n.language)}</dd>
              </div>
            ) : null}
            <div className="flex gap-1.5">
              <dt>{t('profile.memberSince')}</dt>
              <dd className="text-fg">{date(user.created_at)}</dd>
            </div>
            {user.last_active_at ? (
              <div className="flex gap-1.5">
                <dt>{t('profile.lastActive')}</dt>
                <dd className="text-fg">{date(user.last_active_at)}</dd>
              </div>
            ) : null}
          </dl>
        </div>
        {actions}
      </div>
      {user.bio ? <p className="mt-6 whitespace-pre-line text-fg/90">{user.bio}</p> : null}
      <PlayerAchievements achievements={user.achievements ?? []} />
    </article>
  )
}
