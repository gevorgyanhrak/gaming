import { useEffect, useRef, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

const sizes = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-xl' } as const

/**
 * The one modal: a popup, or a bottom sheet on phones. The app behind is inert and doesn't scroll, Escape
 * and the backdrop close it, and focus returns where it was.
 */
export function Modal({
  onClose,
  labelledBy,
  describedBy,
  label,
  size = 'md',
  sheetOnPhone = false,
  scrolls = true,
  lockScroll = true,
  className = '',
  rootClassName,
  backdropClassName,
  children,
}: {
  onClose: () => void
  labelledBy?: string
  describedBy?: string
  /** A name for popups without a visible title element to point at. */
  label?: string
  size?: keyof typeof sizes
  sheetOnPhone?: boolean
  /** False when the content has its own scrolling area. */
  scrolls?: boolean
  /** False for a popup that leaves the page's scroll position alone (the emoji picker). */
  lockScroll?: boolean
  className?: string
  rootClassName?: string
  backdropClassName?: string
  children: ReactNode
}) {
  const panel = useRef<HTMLDivElement>(null)
  const latestClose = useRef(onClose)

  useEffect(() => {
    latestClose.current = onClose
  })

  useEffect(() => {
    const opener = document.activeElement as HTMLElement | null
    const app = document.getElementById('root')
    const overflow = document.body.style.overflow
    app?.setAttribute('inert', '')
    if (lockScroll) document.body.style.overflow = 'hidden'
    panel.current?.focus()
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') latestClose.current()
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      app?.removeAttribute('inert')
      if (lockScroll) document.body.style.overflow = overflow
      opener?.focus()
    }
  }, [lockScroll])

  return createPortal(
    <div
      className={
        rootClassName ??
        `fixed inset-0 z-50 flex items-end justify-center ${sheetOnPhone ? 'p-0 sm:items-center sm:p-4' : 'p-3 sm:items-center sm:p-4'}`
      }
    >
      <div
        aria-hidden="true"
        className={
          backdropClassName ??
          'absolute inset-0 bg-scrim backdrop-blur-sm motion-safe:animate-[fade-in_200ms_ease-out]'
        }
        onClick={onClose}
      />
      <div
        ref={panel}
        role="dialog"
        aria-modal="true"
        aria-labelledby={labelledBy}
        aria-describedby={describedBy}
        aria-label={label}
        tabIndex={-1}
        className={`club-frame relative mb-[env(safe-area-inset-bottom)] w-full outline-none ${sizes[size]} ${scrolls ? 'overflow-y-auto' : 'overflow-hidden'} ${
          sheetOnPhone
            ? 'max-h-[85dvh] rounded-t-modal rounded-b-none motion-safe:animate-[sheet-up_260ms_cubic-bezier(0.2,0.8,0.2,1)] sm:rounded-modal'
            : 'max-h-[calc(100dvh-1.5rem)] rounded-modal motion-safe:animate-rise'
        } ${className}`}
      >
        {children}
      </div>
    </div>,
    document.body,
  )
}
