# PM validation — TASK-044 · Daily bonus popup with a login streak

- **Date:** 2026-11-06 · **Build:** `main` at d93d67f (backend 8c7a0d6, popup 89ecad5, live fixes b599e4e, QA
  fixes 6c816e4, design fixes d93d67f)
- **Request (owner, 2026-11-02):** "daily bonus will be like it", with a screenshot of a "Daily bonus" dialog.
  The screenshot showed day tiles 1–5 with today lit, rewards that grow for days in a row, "One day to go
  until your double bonus!" and an open chest with the amount.
- **Inputs:**
  - `docs/team/PROGRESS.md` "Now";
  - QA `docs/qa/QA_REPORT_TASK044.md`: 41 checks, 38 pass, 3 Low, fixed;
  - design review `docs/design/REVIEW_TASK044.md`: NEEDS REVISION, one Medium and five Lows, all fixed.
- **PM's own checks (read-only):**
  - **Mid-flight screenshot:** I viewed `scratchpad/review/d44-fix-midflight-390.png`, which the reviewer
    asked for. There is no backdrop, the chips fly into a sharp header pill "20,000" with "+1,500".
    Design issue 1 is resolved.
  - **Claim path:** `claimDaily` is now called only from `DailyBonusController`. Sign-in and `GET /me` no
    longer credit anything.
  - **Settings and data:** `fishka.daily_bonus` plus `daily_bonus_day_2…5` (1,000 / 1,500 / 2,000 / 2,500 /
    5,000). The migration only adds `daily_streak`.
  - **Tests present:** `DailyBonusTest` (8 cases) and `DailyBonus.test.tsx` (6 cases).
  - **Web vitest:** 145 passed in 2 of 3 full runs. The other run failed one unrelated test,
    `guards.test.tsx` › "sends guests to the login page…", while the machine's load average was about 40.
    That test passes alone and in the re-runs, so it is flaky, not broken → follow-up TASK-051.
  - Backend tests not run (shared testing DB).

## Verdict: **ACCEPT WITH FOLLOW-UPS**

- **What is in place:** every element of the reference and every platform rule is Met. The streak is
  decided and paid by the server, exactly once a day, in Yerevan days, in en/ru/hy. All QA and design
  findings are fixed.
- **Not re-checked independently:** several Low design fixes and QA BUG-2 were verified by reading the
  code or by unit tests. The reviewer said the task could be approved without another full review once
  the mid-flight screenshot existed, and it does.
- **Follow-ups:** two questions for the owner, because they change how many free Fishkas come in (§B).
  There is also one flaky test.

## A. Request and rules

| Element | Status | Evidence |
|---|---|---|
| A "Daily bonus" dialog | Met | `role=dialog`, labelled; bottom sheet at 390, centred at 1440 (QA 5, 8); our own art, nothing copied (design review "What works") |
| Day tiles 1–5, today lit | Met | QA 1–2 popup table (days 1, 2, 4, 5 in en/ru/hy): past days ✓, today highlighted and scaled; screen-reader text "Collected / Today / Upcoming / Double bonus" (design fix 3; DailyBonus.test) |
| Rewards grow for days in a row | Met | QA 1–2 API walk: 1,000 → 1,500 → 2,000 → 2,500 → 5,000 → 5,000; DailyBonusTest › "raises the bonus with each day in a row, doubles on day 5 and keeps day 5" |
| "One day to go until your double bonus!" | Met | QA 2: "One day to go…" on day 4, "N days" on days 1–3 with correct ru plurals (дня / день), "Double bonus today!" on day 5; ×2 badge on day 5 at 6.5:1 (design fix 4) |
| Open chest with the amount | Met | Original SVG chest, lid up with chips, closed once collected (design review); the amount is shown; after collecting "Collected: 1,500" (design fix 5) |
| The payoff: Fishkas arrive in the balance | Met | QA 8: 6 chips land on the header pill at 390 and 1440; design fix 1 and the mid-flight screenshot (PM viewed) |
| **Fishkas free; nothing bought** | Met | The bonus is a free ledger credit (`daily_login_bonus`, "Daily bonus, day N"); no purchase path (unchanged since TASK-038) |
| **The server decides** (day, amount, claimed) | Met | `GET/POST /me/daily-bonus`; the client shows only server values incl. `tomorrow` (QA BUG-1 fix; DailyBonusTest › "says what tomorrow gives…") |
| **Once a day** | Met | QA 3: 12 parallel POSTs from 3 sessions → one ledger row; double click and two tabs → one; DailyBonusTest › "gives the bonus once even when collected twice at the same moment" |
| Day boundary (Asia/Yerevan) | Met | QA 4: frozen clock; the day changes at 20:00:00 UTC (= 00:00 UTC+4); a missed day resets |
| **en / ru / hy** | Met | QA 2, 8; design review checked hy (day 5) and ru (day 1) at 390 and 1440; no clipping |
| Admin-editable amounts, audited | Met | QA 6: changed to 1,700 / 6,000 (audit 52), paid, restored (audit 53) |
| Wallet history shows the day | Met | QA BUG-3 fixed: "Daily bonus · Day N"; DailyBonusTest › "shows the streak day on the bonus in the wallet history" |
| Nothing else broken | Met | QA 7: registration, login, out-of-Fishkas bonus, header and history all pass |

## B. Decisions taken without the owner

The README puts "anything touching Fishka amounts or rules" on the approval list. The owner asked for
growing daily rewards, so the streak itself is approved. The specific numbers and rules below are the
team's choice and should be confirmed.

| Decision | PM judgement | Owner? |
|---|---|---|
| **5-day amounts 1,000 / 1,500 / 2,000 / 2,500 / 5,000**, admin-editable | Matches the reference's shape: growing, with day 5 = double day 4. **Economy effect:** a 30-day perfect streak now gives 137,000 Fishkas (7,000 for days 1–4 + 26 × 5,000), up from 30,000 at a flat 1,000. That is about 4.6× the free inflow. Players reach the Professional stake group (100,000) or poker Professional (50,000) from bonuses alone within a month | **Question 12** |
| **Day 5 repeats** while the streak holds | Fine for retention; it is the main driver of the 4.6× above. Alternative: a 5-day cycle back to day 1 (30 days ≈ 75,000 at 2,500/day on average) | Part of question 12 |
| **A missed day resets to day 1** | Standard and simple; QA verified it at the boundary. Some products soften this (one grace day) to avoid punishing a gap. Not needed now | No (mention in question 12) |
| **Collected in the popup instead of credited silently at sign-in** | A behaviour change: players who never open the popup or the wallet card no longer get the 1,000. It matches the reference ("collect" moment) and makes the reward visible, which was the owner's aim. Acceptable; it should be mentioned in the owner report | **Question 13** (confirm; inform) |
| **Offered once per visit, never on a game page** | Right: the brief culture is "not aggressive", and nothing must cover a live game (QA 5; DailyBonus.test › "never opens over a game in progress"). Later → not again in the visit; the wallet card always offers it | No |

## C. Follow-ups

| Item | Where |
|---|---|
| Confirm the daily amounts, the day-5 repeat and the reset (economy effect above) | Owner question 12 |
| Confirm "collect in the popup" (no silent credit) and mention it in the owner report | Owner question 13 |
| `guards.test.tsx` "sends guests to the login page…" fails under heavy load (1 of 3 full runs) | TASK-051 (new, P3, test stability) |
| Not changed and acceptable: a page left open across midnight sees the new day on focus or reload (QA O-2) | — |
| Several Low design fixes are verified by code reading only. The reviewer allowed approval on the mid-flight screenshot; a later UI/UX pass (TASK-048 redesign) will cover them | — |
