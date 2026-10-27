import { z } from 'zod'

// Messages are translation keys; form fields translate them when rendering.
// These checks mirror the API for fast feedback. The API remains the authority.

const required = 'validation.required'

export const loginField = z.string().trim().min(1, required)

export const emailField = z
  .string()
  .trim()
  .min(1, required)
  .pipe(z.email({ error: 'validation.email' }))

export const phoneField = z
  .string()
  .trim()
  .min(1, required)
  .regex(/^\+[\d\s().-]{7,24}$/, 'validation.phone')

export const usernameField = z
  .string()
  .trim()
  .min(3, 'validation.usernameLength')
  .max(20, 'validation.usernameLength')
  .regex(/^[A-Za-z0-9_]+$/, 'validation.usernameFormat')

export const passwordField = z
  .string()
  .min(8, 'validation.passwordLength')
  .max(128, 'validation.passwordLength')
  .regex(/\p{L}/u, 'validation.passwordLettersNumbers')
  .regex(/\p{N}/u, 'validation.passwordLettersNumbers')

export const codeField = z
  .string()
  .trim()
  .regex(/^\d{6}$/, 'validation.code')

/** Adds a "passwords don't match" issue on password_confirmation. */
export function confirmed<T extends { password: string; password_confirmation: string }>(
  value: T,
  ctx: z.RefinementCtx,
) {
  if (value.password !== value.password_confirmation) {
    ctx.addIssue({
      code: 'custom',
      path: ['password_confirmation'],
      message: 'validation.passwordMismatch',
    })
  }
}
