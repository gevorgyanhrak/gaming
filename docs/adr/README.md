# Architecture Decision Records

Each record captures one decision: its context, the choice, and its consequences.
Records are immutable once accepted; a changed decision gets a new record that supersedes the old one.

| # | Decision | Status |
|---|---|---|
| [0001](0001-monorepo-independent-apps.md) | Monorepo with independently deployable apps | Accepted |
| [0002](0002-modular-monolith.md) | Laravel modular monolith | Accepted |
| [0003](0003-http-commands-websocket-events.md) | Commands over HTTP, events over WebSocket | Accepted |
| [0004](0004-pure-game-engines.md) | Pure-PHP game engines behind one contract | Accepted |
| [0005](0005-redis-hot-state-mysql-move-log.md) | Hot state in Redis, durable move log in MySQL | Accepted |
| [0006](0006-server-authoritative-rules.md) | Server is the only rules authority | Accepted |
| [0007](0007-bots-share-human-write-path.md) | Bots use the same write path as humans | Accepted |
| [0008](0008-mysql.md) | MySQL 8.4 LTS as the primary database | Accepted |
| [0009](0009-sanctum-spa-cookie-auth.md) | Sanctum SPA cookie authentication | Accepted |
| [0010](0010-filament-admin.md) | Admin panel in Filament | Accepted |
| [0011](0011-toolchain-versions.md) | Toolchain versions and compatibility pins | Accepted |
| [0012](0012-one-time-codes-for-both-channels.md) | One-time codes for both channels | Accepted |
| [0013](0013-admin-managed-settings.md) | Admin-managed platform settings | Accepted |
| [0014](0014-separate-admin-guard.md) | Separate session guard for the admin panel | Accepted |
| [0015](0015-fishka-wallet-ledger.md) | Fishka wallets: locked balance row, append-only ledger | Accepted |
| [0016](0016-play-groups-and-table-stakes.md) | Play groups and table stakes | Accepted |
| [0017](0017-table-reactions.md) | Emojis and quick messages at tables | Accepted |
| [0018](0018-levels-and-xp.md) | Levels and XP: backend-only progression, rewards once, bot levels | Accepted |
| [0019](0019-games-against-the-house.md) | Games against the house: the dealer is the engine, solo tables, `Outcome::HOUSE` | Accepted |
| [0020](0020-server-side-pool-physics.md) | Server-side pool physics, replayed by the client from segments | Accepted |
| [0021](0021-ai-players-platform-wide.md) | AI players platform-wide: one level, people first, AI stands in for people | Accepted |
