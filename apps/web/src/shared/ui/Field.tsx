import { useId, type ComponentProps, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

const control =
  'w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-fg placeholder:text-muted/70 transition-colors focus:border-accent-text focus:outline-none disabled:opacity-60 aria-invalid:border-danger'

interface FieldProps {
  label: string
  /** A translation key (client-side validation) or an already translated message (from the API). */
  error?: string
  hint?: ReactNode
}

/** Wires a label, hint and error message to a control with the right ARIA attributes. */
function useField({ error, hint }: Omit<FieldProps, 'label'>) {
  const id = useId()
  const { t, i18n } = useTranslation()
  const message = error && i18n.exists(error) ? t(error as never) : error

  return {
    id,
    message,
    controlProps: {
      id,
      'aria-invalid': message ? true : undefined,
      'aria-describedby': message ? `${id}-error` : hint ? `${id}-hint` : undefined,
    },
  }
}

function FieldShell({
  id,
  label,
  message,
  hint,
  children,
}: Omit<FieldProps, 'error'> & { id: string; message?: string; children: ReactNode }) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="block text-sm font-medium text-fg">
        {label}
      </label>
      {children}
      {message ? (
        <p id={`${id}-error`} className="text-sm text-danger">
          {message}
        </p>
      ) : hint ? (
        <p id={`${id}-hint`} className="text-sm text-muted">
          {hint}
        </p>
      ) : null}
    </div>
  )
}

export function TextField({
  label,
  error,
  hint,
  className = '',
  ...props
}: FieldProps & ComponentProps<'input'>) {
  const { id, message, controlProps } = useField({ error, hint })

  return (
    <FieldShell id={id} label={label} message={message} hint={hint}>
      <input {...controlProps} className={`${control} ${className}`} {...props} />
    </FieldShell>
  )
}

export function TextAreaField({
  label,
  error,
  hint,
  className = '',
  ...props
}: FieldProps & ComponentProps<'textarea'>) {
  const { id, message, controlProps } = useField({ error, hint })

  return (
    <FieldShell id={id} label={label} message={message} hint={hint}>
      <textarea {...controlProps} className={`${control} min-h-24 ${className}`} {...props} />
    </FieldShell>
  )
}

export function SelectField({
  label,
  error,
  hint,
  className = '',
  children,
  ...props
}: FieldProps & ComponentProps<'select'>) {
  const { id, message, controlProps } = useField({ error, hint })

  return (
    <FieldShell id={id} label={label} message={message} hint={hint}>
      <select {...controlProps} className={`${control} ${className}`} {...props}>
        {children}
      </select>
    </FieldShell>
  )
}
