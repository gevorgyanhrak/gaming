# 0012. One-time codes for email and phone verification and password reset

**Status:** Accepted (2026-10-27)

## Context
Users register with an email address or a phone number. Laravel's built-in flows use signed links for
email verification and a token table keyed by email for password resets, neither of which covers phones.

## Decision
One mechanism for both channels and all purposes (verify, password reset, change contact): a 6-digit code
in `verification_codes`, stored as an HMAC (keyed with the app key), expiring after 10 minutes, allowing
5 attempts, replaced when a new one is issued, with a 60-second resend cooldown and an hourly cap per
destination. Sending is throttled before looking up the account, so responses and throttling are identical
for unknown addresses. Codes are delivered by queued notifications (mail, or SMS through `SmsGateway`).

## Consequences
- Same UX on every channel; it works in an SPA without links that leave the app.
- Laravel's `password_reset_tokens` table and `MustVerifyEmail` are not used.
- A real SMS provider must be implemented before phone sign-up is offered publicly; the development `log`
  driver refuses to run in production.
