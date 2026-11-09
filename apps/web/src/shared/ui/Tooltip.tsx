import { useId, type ReactElement, cloneElement } from 'react'

/** A label for an icon-only control (CSS only). Touch devices never rely on it. */
export function Tooltip({
  text,
  children,
}: {
  text: string
  children: ReactElement<{ 'aria-describedby'?: string }>
}) {
  const id = useId()
  return (
    <span className="group/tip relative inline-flex">
      {cloneElement(children, { 'aria-describedby': id })}
      <span
        role="tooltip"
        id={id}
        className="pointer-events-none absolute top-1/2 left-full z-40 ml-2 -translate-y-1/2 rounded-lg bg-ivory-100 px-2.5 py-1.5 text-xs font-medium whitespace-nowrap text-ink opacity-0 shadow-raised transition-opacity duration-200 group-focus-within/tip:opacity-100 group-hover/tip:opacity-100"
      >
        {text}
      </span>
    </span>
  )
}
