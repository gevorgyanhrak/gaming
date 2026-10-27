# 0013. Admin-managed platform settings

**Status:** Accepted (2026-10-27)

## Context
Routine configuration (platform name, languages, maintenance, registration, verification, matchmaking
defaults, session lifetime) must change without code changes or deploys, safely and auditably.

## Decision
Settings are **defined in code** (`App\Platform\Settings\SettingsCatalog`: key, type, default,
validation, public flag) and **overridden in the database** (`platform_settings`, one JSON value per
key). `App\Platform\Settings\Settings` reads them (cached until the next update) and validates updates.
Defaults come from configuration, so the environment still works and tests need no stored rows.
Only settings flagged public are exposed by `GET /api/v1/config`. Framework values (session lifetime,
app name) are applied per request by `ApplyRuntimeSettings`, never at boot, so artisan and deploys
work without a database.

**Secrets are not settings.** Provider credentials and API keys stay in environment variables or a
secret manager, never in a database row editable from the admin panel.

## Consequences
- Code reads admin-managed values through `Settings`, never `config()`.
- Adding a setting means a catalog entry, an admin form field and (if public) a config field.
- Every change is audited (`settings.updated` with old and new values).
