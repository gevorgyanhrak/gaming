# Slot engine — dependency audit (2026-11-18)

## Go modules

`go mod verify`: all modules verified against go.sum. `govulncheck` v1.10.0 (Go 1.27.4, database vuln.go.dev,
symbol-level): **No vulnerabilities found.**

| Module | Version | Licence | Why it is there (`go mod why`) | In the binary |
|---|---|---|---|---|
| go.yaml.in/yaml/v3 | v3.0.6 | MIT / Apache-2.0 | `game` — reads the embedded reels and paytables | yes |
| golang.org/x/crypto | v0.59.0 | BSD-3-Clause | `prng` — ChaCha20, the spins' generator | yes |
| go.uber.org/atomic | v1.12.0 | MIT | `game/slot` — counters in the RTP scanners | yes |
| golang.org/x/sys | v0.50.0 | BSD-3-Clause | `x/crypto/chacha20` → CPU feature detection | yes (indirect) |
| github.com/stretchr/testify, golang.org/x/net, x/term, x/text | — | — | module graph of the above (their tests); not imported | no |

`go version -m` of the built binary lists only atomic, yaml and x/crypto (x/sys inlined). The standard library's
`net/http` links its client dialer, but no code calls it (a test forbids `net`, `net/http`, `os/exec`,
`database/sql`, `plugin` outside `engine/server.go` and `main.go`), and the container has no outbound route.

Removed with the platform parts (2026-11-17): gin, golang-jwt, xorm and three SQL drivers (one with cgo), uasurfer,
go-disk-usage, viper, cobra.

## Images

| Image | Use | Notes |
|---|---|---|
| golang:1.27-bookworm | build stage only | `CGO_ENABLED=0`, `-trimpath` |
| gcr.io/distroless/static-debian12:nonroot | runtime | no shell or package manager; user `nonroot`; 15.4 MB |

Pinned by tag, not digest (remaining risk: a re-tagged image). Rebuilding picks up base-image patches.

## Platform (for context, not in the engine)

- `composer audit`: 11 advisories in 6 packages of the PHP framework (laravel/framework < 13.40, symfony
  http-foundation/http-kernel/routing/mime/html-sanitizer) and 3 abandoned `dragon-code/*`. Not part of the slot
  engine; recommended as a separate update step (see the security audit).
- `npm audit --omit=dev`: 0 vulnerabilities.
