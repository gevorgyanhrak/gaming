import type { ButtonHTMLAttributes } from 'react'
import { Link, type LinkProps } from 'react-router'

const variants = {
  primary: 'bg-accent text-white hover:bg-accent-hover',
  secondary: 'border border-border bg-surface text-fg hover:bg-surface-raised',
} as const

type Variant = keyof typeof variants

const base =
  'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50'

export function Button({
  variant = 'primary',
  className = '',
  type = 'button',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant }) {
  return <button type={type} className={`${base} ${variants[variant]} ${className}`} {...props} />
}

export function ButtonLink({
  variant = 'primary',
  className = '',
  ...props
}: LinkProps & { variant?: Variant }) {
  return <Link className={`${base} ${variants[variant]} ${className}`} {...props} />
}
