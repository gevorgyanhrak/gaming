import { useTranslation } from 'react-i18next'
import type { Room } from '../api'
import { groupColor } from '../groups'

/** A table's group with what it plays for: the bet (stake games) or the blinds (poker). */
export function GroupBadge({ room }: { room: Pick<Room, 'group' | 'stake'> }) {
  const { t, i18n } = useTranslation()
  const group = room.group
  if (!group) return null
  const format = (n: number) => n.toLocaleString(i18n.language)

  return (
    <span className="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-black/30 px-2.5 py-0.5 text-xs font-semibold">
      <span
        aria-hidden="true"
        className="size-2.5 rounded-full"
        style={{ background: groupColor(group) }}
      />
      {t('play.groupBadge', { group: t(`groups.${group.slug}` as 'groups.beginner') })}
      <span className="text-gold-light tabular-nums">
        ·{' '}
        {group.kind === 'chips'
          ? t('play.tableBlinds', {
              small: format(Math.max(1, Math.floor(group.min_bet / 2))),
              big: format(group.min_bet),
            })
          : t('play.tableBet', { amount: format(room.stake) })}
      </span>
    </span>
  )
}
