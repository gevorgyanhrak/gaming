import { useId } from 'react'

/** A labelled group of pill-shaped radio buttons, for a few short choices. */
export function Segmented<T extends string | number>({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: T
  options: { value: T; label: string }[]
  onChange: (value: T) => void
}) {
  const name = useId()

  return (
    <fieldset>
      <legend className="mb-2 text-xs font-semibold tracking-wider text-muted uppercase">
        {label}
      </legend>
      <div className="inline-flex flex-wrap gap-1 rounded-xl border border-border bg-bg/60 p-1">
        {options.map((option) => (
          <label
            key={option.value}
            className="cursor-pointer rounded-lg px-3 py-1.5 text-sm font-medium text-muted transition-colors hover:text-fg has-checked:bg-linear-to-b has-checked:from-gold-light has-checked:to-gold has-checked:text-black has-focus-visible:outline-2 has-focus-visible:outline-accent-text"
          >
            <input
              type="radio"
              name={name}
              value={option.value}
              checked={option.value === value}
              onChange={() => onChange(option.value)}
              className="sr-only"
            />
            {option.label}
          </label>
        ))}
      </div>
    </fieldset>
  )
}
