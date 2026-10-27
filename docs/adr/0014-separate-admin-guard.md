# 0014. Separate session guard for the admin panel

**Status:** Accepted (2026-10-27)

## Decision
The Filament admin panel authenticates with its own `admin` session guard over the same `users`
provider. Signing in to the admin panel does not sign anyone in to the player app (`web` guard), and
vice versa. Roles and permissions always use the `web` guard (`User::$guard_name`), because the
current default guard switches to `admin` inside the panel and would otherwise make permission
lookups fail.

## Consequences
- Staff use separate logins for administration and for playing.
- The admin login is by email; staff accounts need an email address.
- Two-factor authentication applies to the admin login only.
