import type { Channel, CurrentUser } from './api'

/** The contact the platform asks the user to verify next, if any. */
export function contactToVerify(
  user: CurrentUser,
  requirements: { require_email_verification: boolean; require_phone_verification: boolean },
): Channel | null {
  if (user.email && !user.email_verified && requirements.require_email_verification) return 'email'
  if (user.phone && !user.phone_verified && requirements.require_phone_verification) return 'phone'
  return null
}
