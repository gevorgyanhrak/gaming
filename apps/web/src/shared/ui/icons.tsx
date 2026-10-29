import type { ReactNode } from 'react'

/** Line icons on a 24px grid, drawn in the current text colour. Decorative: label the control. */
function Icon({ children, className = 'size-5' }: { children: ReactNode; className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={className}
    >
      {children}
    </svg>
  )
}

type IconProps = { className?: string }

export const HomeIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M3.5 10.5 12 4l8.5 6.5V20a1 1 0 0 1-1 1H15v-6H9v6H4.5a1 1 0 0 1-1-1z" />
  </Icon>
)

export const GridIcon = (p: IconProps) => (
  <Icon {...p}>
    <rect x="4" y="4" width="7" height="7" rx="1.5" />
    <rect x="13" y="4" width="7" height="7" rx="1.5" />
    <rect x="4" y="13" width="7" height="7" rx="1.5" />
    <rect x="13" y="13" width="7" height="7" rx="1.5" />
  </Icon>
)

export const HistoryIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M3.5 12a8.5 8.5 0 1 0 2.5-6" />
    <path d="M3.5 4v4h4" />
    <path d="M12 8v4.5l3 2" />
  </Icon>
)

export const UserIcon = (p: IconProps) => (
  <Icon {...p}>
    <circle cx="12" cy="8.5" r="4" />
    <path d="M4.5 20.5c1.2-3.6 4-5.5 7.5-5.5s6.3 1.9 7.5 5.5" />
  </Icon>
)

export const SettingsIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M4 7h10M18 7h2M4 17h2M10 17h10" />
    <circle cx="16" cy="7" r="2" />
    <circle cx="8" cy="17" r="2" />
  </Icon>
)

export const BoltIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M13 3 5 13.5h6L10 21l8-10.5h-6z" />
  </Icon>
)

export const BotIcon = (p: IconProps) => (
  <Icon {...p}>
    <rect x="4.5" y="8" width="15" height="11" rx="3" />
    <path d="M12 4.5V8M9 13h.01M15 13h.01M9.5 16h5" />
    <circle cx="12" cy="4" r="0.6" />
  </Icon>
)

export const FriendsIcon = (p: IconProps) => (
  <Icon {...p}>
    <circle cx="9" cy="8.5" r="3.5" />
    <path d="M2.5 19.5c.9-3 3.4-4.5 6.5-4.5s5.6 1.5 6.5 4.5" />
    <path d="M16 5.2a3.5 3.5 0 0 1 0 6.6M18 15.3c1.7.6 2.9 2 3.5 4.2" />
  </Icon>
)

export const TicketIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M3.5 8.5V6a1 1 0 0 1 1-1h15a1 1 0 0 1 1 1v2.5a3.5 3.5 0 0 0 0 7V18a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1v-2.5a3.5 3.5 0 0 0 0-7Z" />
    <path d="M14 5v14" strokeDasharray="2 2.5" />
  </Icon>
)

export const MenuIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M4 7h16M4 12h16M4 17h16" />
  </Icon>
)

export const CloseIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M6 6l12 12M18 6 6 18" />
  </Icon>
)

export const BackIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M19 12H5M11 6l-6 6 6 6" />
  </Icon>
)

export const ChevronIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="m9 6 6 6-6 6" />
  </Icon>
)

export const TrophyIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M8 4h8v5a4 4 0 0 1-8 0zM8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M10 17h4" />
  </Icon>
)

export const TargetIcon = (p: IconProps) => (
  <Icon {...p}>
    <circle cx="12" cy="12" r="8" />
    <circle cx="12" cy="12" r="4" />
    <circle cx="12" cy="12" r="0.8" />
  </Icon>
)

export const LinkIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1" />
    <path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1" />
  </Icon>
)

export const ShareIcon = (p: IconProps) => (
  <Icon {...p}>
    <path d="M12 15V4M8 8l4-4 4 4" />
    <path d="M6 11H5a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7a1 1 0 0 0-1-1h-1" />
  </Icon>
)

export const PlayIcon = (p: IconProps) => (
  <svg viewBox="0 0 24 24" aria-hidden="true" className={p.className ?? 'size-5'}>
    <path d="M7 4.5v15l12-7.5z" fill="currentColor" />
  </svg>
)
