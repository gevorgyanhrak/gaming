# 0008. MySQL 8.4 LTS as the primary database

**Status:** Accepted (2026-10-27). Chosen by the product owner over the initially proposed PostgreSQL.

## Decision
MySQL 8.4 LTS with InnoDB and utf8mb4 (not MariaDB, whose CHECK and JSON semantics differ).

## Consequences and conventions
- Case-insensitive collation for unique usernames and emails; emails are also lowercased before storage.
- `JSON` columns for translations, configuration and move payloads; generated columns if indexing inside JSON is ever needed.
- No partial indexes: composite indexes instead, e.g. `(user_id, is_active)`.
- CHECK constraints (enforced since 8.0.16) are added with `DB::statement`. Columns used in CHECK constraints cannot have cascading foreign-key actions, so participant FKs use RESTRICT (users are deactivated or anonymized, not deleted).
- Internal BIGINT primary keys plus a ULID `public_id` where ids are exposed; this keeps InnoDB secondary indexes compact.
- `DATETIME` in UTC instead of `TIMESTAMP` (2038 limit).
- Strict SQL mode stays on. Critical transactions retry deadlocks (`DB::transaction(..., attempts: 3)`).
- `game_moves` growth is handled by archiving, since partitioned tables cannot have foreign keys.
- Backend tests run against a real MySQL `testing` database, never SQLite.
