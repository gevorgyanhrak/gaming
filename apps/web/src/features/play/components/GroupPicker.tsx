import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { LockIcon } from '@/shared/ui/icons'
import { canEnter, groupColor, type PlayGroup } from '../groups'

/**
 * The game's five groups as radio tiles: name, the balance needed and the bets. Groups the balance
 * can't enter are shown locked with what they need. The server checks the same rules.
 */
export function GroupPicker({
  groups,
  value,
  balance,
  onChange,
}: {
  groups: PlayGroup[]
  value: string
  balance: number | undefined
  onChange: (slug: string) => void
}) {
  const { t, i18n } = useTranslation()
  const name = useId()
  const format = (n: number) => n.toLocaleString(i18n.language)

  return (
    <fieldset>
      <legend className="mb-2 text-xs font-semibold tracking-wider text-muted uppercase">
        {t('play.group')}
      </legend>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {groups.map((group) => {
          const open = canEnter(group, balance)
          const color = groupColor(group)
          return (
            <label
              key={group.slug}
              className={`relative flex min-h-11 min-w-0 cursor-pointer flex-col gap-0.5 rounded-xl border bg-black/25 p-2.5 text-left transition has-checked:border-gold has-checked:bg-gold/10 has-checked:shadow-[0_0_0_2px_rgb(214_171_78/0.35)] has-focus-visible:outline-2 has-focus-visible:outline-accent-text ${
                open ? 'border-border hover:border-gold/60' : 'cursor-not-allowed border-border/60'
              }`}
            >
              <input
                type="radio"
                name={name}
                value={group.slug}
                checked={value === group.slug}
                disabled={!open}
                onChange={() => onChange(group.slug)}
                className="sr-only"
              />
              <span className="flex min-w-0 items-center gap-1.5 text-sm font-bold">
                <span
                  aria-hidden="true"
                  className={`size-3 shrink-0 rounded-full shadow-[inset_0_-2px_0_rgb(0_0_0/0.25)] ${open ? '' : 'opacity-50'}`}
                  style={{ background: color }}
                />
                {/* A locked group dims its name only: what it needs stays readable (contrast). Long
                    names (hy "Professional") wrap rather than truncate. */}
                <span className={`min-w-0 leading-tight break-words ${open ? '' : 'text-muted'}`}>
                  {t(`groups.${group.slug}` as 'groups.beginner')}
                </span>
                {open ? null : <LockIcon className="ml-auto size-4 shrink-0 text-muted" />}
              </span>
              <span className="text-xs text-muted">
                {open
                  ? t('play.groupFrom', { amount: format(group.min_balance) })
                  : t('play.groupNeed', { amount: format(group.min_balance) })}
              </span>
              <span
                className={`text-xs font-semibold tabular-nums ${open ? 'text-gold-light' : 'text-muted'}`}
              >
                {group.kind === 'chips' || group.max_bet === null
                  ? t('play.groupMinBet', { amount: format(group.min_bet) })
                  : t('play.groupBets', { min: format(group.min_bet), max: format(group.max_bet) })}
              </span>
            </label>
          )
        })}
      </div>
    </fieldset>
  )
}
