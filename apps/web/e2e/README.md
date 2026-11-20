# Mobile QA suite (Playwright)

Runs against the real local stack (`make up`, `make web`): API on :8000, web on :5175, test account
player1@example.test (override with `E2E_EMAIL` / `E2E_PASSWORD`).

```sh
cd apps/web
export PW_CHROMIUM=~/Library/Caches/ms-playwright/chromium_headless_shell-1248/chrome-headless-shell-mac-arm64/chrome-headless-shell
E2E_ITERATION=1 npx playwright test                    # everything (~45 min)
E2E_ITERATION=2 npx playwright test e2e/routes.spec.ts # one area
E2E_ADMIN_EMAIL=… E2E_ADMIN_PASSWORD=… npx playwright test e2e/admin.spec.ts
```

| spec             | covers                                                                                                         |
| ---------------- | -------------------------------------------------------------------------------------------------------------- |
| `routes.spec.ts` | signed-out routes in en/ru/hy and signed-in routes at 320, 344, 360, 375, 390, 414, 430, 768, 1024, 1280, 1440 |
| `games.spec.ts`  | each game against the AI at every size, then real moves from the server's `legal_actions`                      |
| `casino.spec.ts` | the casino shelf; each house slot at phone (upright and sideways), tablet, desktop; a real spin; the way back  |
| `admin.spec.ts`  | Filament admin pages on phones and desktop (skipped without `E2E_ADMIN_EMAIL`)                                 |

Every page is checked for: page wider than the screen, elements past the edges, tap targets under 24 px, console
errors. Findings: `e2e/results/findings-<iteration>.jsonl`; screenshots: `e2e/screenshots/iteration-<n>/<area>/<route>/<WxH>.png`
(both git-ignored). The bug list and history are in `docs/qa/MOBILE_QA_REPORT.md`.
