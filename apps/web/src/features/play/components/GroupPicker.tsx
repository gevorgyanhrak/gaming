import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { LockIcon } from '@/shared/ui/icons'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { canEnter, groupColor, type PlayGroup } from '../groups'

/**
 * The game's five groups as one compact row of radio tiles: name, then the bets (or, locked, the balance
 * needed) in short numbers, the full figures in the tooltip. Groups the balance can't enter are locked. The server checks the same rules.
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

  const short = (n: number) =>
    new Intl.NumberFormat(i18n.language, { notation: 'compact', maximumFractionDigits: 1 }).format(
      n,
    )

  return (
    <fieldset>
      <legend className="mb-1.5 text-xs font-semibold tracking-wider text-muted uppercase">
        {t('play.group')}
      </legend>
      {/* One compact row (it scrolls sideways on a phone): name, then the bets or the balance needed in
          short numbers; the full figures are in the tooltip and read out to screen readers. */}
      <div className="-mx-1 flex snap-x gap-1.5 overflow-x-auto px-1 pb-1 sm:grid sm:grid-cols-5 sm:overflow-visible">
        {groups.map((group) => {
          const open = canEnter(group, balance)
          const color = groupColor(group)
          const bets =
            group.kind === 'chips' || group.max_bet === null
              ? t('play.groupMinBet', { amount: format(group.min_bet) })
              : t('play.groupBets', { min: format(group.min_bet), max: format(group.max_bet) })
          const need = open
            ? t('play.groupFrom', { amount: format(group.min_balance) })
            : t('play.groupNeed', { amount: format(group.min_balance) })
          return (
            <label
              key={group.slug}
              title={`${need} · ${bets}`}
              className={`relative flex min-h-11 min-w-[6.25rem] shrink-0 cursor-pointer snap-start flex-col justify-center gap-0.5 rounded-lg border bg-black/25 px-2 py-1.5 text-left transition has-checked:border-gold has-checked:bg-gold/10 has-checked:shadow-[0_0_0_2px_rgb(214_171_78/0.35)] has-focus-visible:outline-2 has-focus-visible:outline-accent-text sm:min-w-0 ${
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
              <span className="flex min-w-0 items-center gap-1.5 text-xs font-bold sm:text-sm">
                <span
                  aria-hidden="true"
                  className={`size-2.5 shrink-0 rounded-full shadow-[inset_0_-2px_0_rgb(0_0_0/0.25)] ${open ? '' : 'opacity-50'}`}
                  style={{ background: color }}
                />
                <span className={`min-w-0 truncate ${open ? '' : 'text-muted'}`}>
                  {t(`groups.${group.slug}` as 'groups.beginner')}
                </span>
              </span>
              <span
                aria-hidden="true"
                className={`flex items-center gap-1 text-xs font-semibold tabular-nums ${open ? 'text-gold-light' : 'text-muted'}`}
              >
                {open ? (
                  <>
                    <FishkaIcon className="size-3.5 shrink-0" />
                    {group.kind === 'chips' || group.max_bet === null
                      ? short(group.min_bet)
                      : `${short(group.min_bet)}–${short(group.max_bet)}`}
                  </>
                ) : (
                  <>
                    <LockIcon className="size-3.5 shrink-0" />
                    {short(group.min_balance)}
                  </>
                )}
              </span>
              <span className="sr-only">
                {need}. {bets}
              </span>
            </label>
          )
        })}
      </div>
    </fieldset>
  )
}
