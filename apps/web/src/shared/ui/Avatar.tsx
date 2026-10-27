const sizes = { sm: 'size-8 text-sm', md: 'size-12 text-lg', lg: 'size-24 text-3xl' } as const

/** A user's avatar image, or their initial on a neutral tile. */
export function Avatar({
  url,
  name,
  size = 'md',
}: {
  url: string | null
  name: string
  size?: keyof typeof sizes
}) {
  if (url) {
    return <img src={url} alt="" className={`${sizes[size]} rounded-full object-cover`} />
  }

  return (
    <span
      aria-hidden="true"
      className={`${sizes[size]} inline-flex shrink-0 items-center justify-center rounded-full bg-accent/30 font-semibold text-fg uppercase`}
    >
      {name.charAt(0)}
    </span>
  )
}
