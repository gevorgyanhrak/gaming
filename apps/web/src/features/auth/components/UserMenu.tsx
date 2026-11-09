import { useEffect, useId, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { LevelProgress } from '@/features/levels/components/LevelProgress'
import { useLevel } from '@/features/levels/hooks'
import { Avatar } from '@/shared/ui/Avatar'
import type { CurrentUser } from '../api'
import { useLogout } from '../hooks'

const item =
  'flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm text-fg hover:bg-surface-raised'

/** Account disclosure menu in the header. Closes on outside click, Escape and navigation. */
export function UserMenu({ user }: { user: CurrentUser }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const logout = useLogout()
  const [open, setOpen] = useState(false)
  const level = useLevel()
  const ref = useRef<HTMLDivElement>(null)
  const menuId = useId()
  const name = user.profile.display_name ?? user.username

  useEffect(() => {
    if (!open) return
    const onPointer = (event: PointerEvent) => {
      if (!ref.current?.contains(event.target as Node)) setOpen(false)
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }
    document.addEventListener('pointerdown', onPointer)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('pointerdown', onPointer)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        aria-expanded={open}
        aria-controls={menuId}
        aria-label={t('nav.account')}
        onClick={() => setOpen((value) => !value)}
        className="flex min-h-11 items-center gap-2 rounded-full p-1 pr-3 hover:bg-white/8"
      >
        <Avatar
          url={user.profile.avatar_url}
          name={name}
          size="sm"
          level={user.level}
          frame={user.looks.frame}
        />
        <span className="hidden max-w-32 truncate text-sm font-medium sm:inline">{name}</span>
      </button>

      {open ? (
        <div
          id={menuId}
          className="absolute right-0 mt-2 w-60 rounded-card border border-gold/30 bg-emerald-800 p-1.5 shadow-raised"
        >
          <p className="truncate px-3 py-2 text-xs text-muted">@{user.username}</p>
          {level.data ? (
            <Link
              to="/profile"
              className="mx-1 mb-1 block rounded-lg bg-emerald-900/60 px-2.5 py-2 hover:bg-emerald-900"
              onClick={() => setOpen(false)}
            >
              <LevelProgress status={level.data} compact />
            </Link>
          ) : null}
          <Link to="/dashboard" className={item} onClick={() => setOpen(false)}>
            {t('nav.dashboard')}
          </Link>
          <Link to="/history" className={item} onClick={() => setOpen(false)}>
            {t('nav.history')}
          </Link>
          <Link to="/profile" className={item} onClick={() => setOpen(false)}>
            {t('nav.profile')}
          </Link>
          <Link to="/settings" className={item} onClick={() => setOpen(false)}>
            {t('nav.settings')}
          </Link>
          <button
            type="button"
            className={item}
            disabled={logout.isPending}
            onClick={() => {
              // Leave protected pages first: once the user is gone this menu unmounts, and
              // RequireAuth would otherwise send them to the login page.
              setOpen(false)
              void navigate('/')
              logout.mutate()
            }}
          >
            {t('auth.logout')}
          </button>
        </div>
      ) : null}
    </div>
  )
}
