# QA report — TASK-044 · Daily bonus popup with a login streak

- **Date:** 2026-11-02, about 20:49–21:30 local (16:49–17:30 UTC).
- **Build:** `main` at 7edefc9 (backend 8c7a0d6, popup 89ecad5, fixes b599e4e). `make restart` was run
  first.
- **Machine load:** load average 3.6–4.5 (much quieter than earlier runs).
- **Method:**
  - Fresh test accounts: q44a–q44e (created in tinker with `FishkaWallets::open`, the registration path),
    plus q44reg02407 registered through the UI.
  - Every UI check ran in an isolated headless-Chrome context with that account's real session (CDP :9333).
  - Days were simulated with `qa2/dayset.sh <user> <daysAgo> <streak>`. It sets
    `fishka_wallets.daily_bonus_on` and `daily_streak`, and first renames today's ledger key for that
    account (`…:qa-rewind-<id>`). I used it only on the q44 test accounts.
  - The midnight boundary was checked with a frozen clock (`Carbon::setTestNow`) in tinker.
- **Evidence:** screenshots and scripts are in
  `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/qa2/`, written below
  as `qa2/`. Screenshots start with `t44-`; scripts are `t44api.mjs`, `t44race.mjs`, `t44pop.mjs`,
  `t44card.mjs`, `t44tabs.mjs`, `t44look.mjs`, `t44room.mjs`, `t44reg.mjs`, `t44hist.mjs`, `t44switch.mjs`
  and `dayset.sh`.

## Summary

| Area | Result |
|---|---|
| 1. New account: registration day, then day 1 | PASS for the logic. **BUG-1:** on the registration day the popup says "Come back tomorrow for 1,500", but tomorrow pays day 1 (1,000) |
| 2. Streak 1→2→3→4→5→5, reset after a missed day, tiles, messages, plurals en/ru/hy | PASS |
| 3. Once a day: double click, two tabs, 12 parallel requests, API repeats | PASS (one ledger row; correct `collected`) |
| 4. Midnight Asia/Yerevan | PASS (the day changes at 20:00:00 UTC) |
| 5. Popup: once per visit, never on a room page, Later / Escape / backdrop, focus, inert, wallet card, none after collecting | PASS. **BUG-2:** after switching accounts in the same tab, the second account is not offered its bonus until a reload |
| 6. Admin: day amounts, audit, restore | PASS (restored) |
| 7. Regression: register, log in, no auto-credit, out-of-Fishkas popup and bonus, header, wallet history | PASS. **BUG-3:** wallet history shows "Daily bonus" without the day |
| 8. Layout 390 / 1440 in en/ru/hy, chips fly to the header | PASS |

**Totals:** 41 checks — 38 PASS, 3 Low bugs, 3 observations. No Critical, High or Medium bugs.

## 1–2. Streak and amounts (API, account q44a)

| Simulated day | GET before | POST | Repeat POST | Balance |
|---|---|---|---|---|
| Registration day | day 1, claimed **true**, 1,000 | 200, collected 0 | — | +0 (welcome bonus 5,000 only) |
| Next day | day 1, claimed false, 1,000 | 201, collected 1,000 | 200, 0 | +1,000 |
| 2nd in a row | day 2, 1,500 | 201, 1,500 | 200, 0 | +1,500 |
| 3rd | day 3, 2,000 | 201, 2,000 | 200, 0 | +2,000 |
| 4th | day 4, 2,500 | 201, 2,500 | 200, 0 | +2,500 |
| 5th | day 5, 5,000 | 201, 5,000 | 200, 0 | +5,000 |
| 6th (day 5 repeats) | day 5, 5,000 | 201, 5,000 | 200, 0 | +5,000 |
| After one missed day | day 1, 1,000 | 201, 1,000 | 200, 0 | +1,000 |
| After 4 missed days | day 1 | 201, 1,000 | 200, 0 | +1,000 |
| Never collected (null date) | day 1 | 201, 1,000 | 200, 0 | +1,000 |

Ledger: one `daily_login_bonus` row per collected day, described "Daily bonus, day N", with the right
amounts (9 rows for 9 collections) — **PASS**.

**Popup on each day** (390, en / ru / hy):

| Day | Message (en / ru / hy) | Tiles |
|---|---|---|
| 1 | "4 days to go until your double bonus!" / "осталось 4 дня" / "մնաց 4 օր" | Day 1 lit |
| 2 | "3 days" / "3 дня" / "3 օր" | Day 1 ✓, day 2 lit |
| 4 | "**One** day to go…" / "остал**ся 1 день**" / "մնաց 1 օր" | Days 1–3 ✓, day 4 lit |
| 5 | "Double bonus today! Come back tomorrow to keep it." / "Сегодня двойной бонус!…" / "Այսօր կրկնակի բոնուս է։…" | Days 1–4 ✓, day 5 lit; day 5 shows a red **×2** badge until collected |

After collecting, the message is "Today's bonus is collected. Come back tomorrow for 2,000." (ru "…за 2 000.",
hy "…2,000-ի համար։"), and the day 5 repeat says "…for 5,000". Numbers follow each language's format
(1 000 in ru). Russian plural forms are correct (дня / день) — **PASS**.

Evidence: `qa2/t44-{en,ru,hy}-390-day{1,2,4,5}.png`, `qa2/t44-plurals.log`.

**Registration day**, on account q44c and on the UI-registered account:

| Check | Actual | Result |
|---|---|---|
| Popup offers itself | No (today counts as collected) | PASS |
| Header | 5,000 | PASS |
| Wallet card | "Daily bonus · Day 1 · Collected · Open" | PASS |
| Popup opened from the card | Tile "Day 1 ✓ 1,000" lit; message "Come back tomorrow for **1,500**" | **BUG-1** |
| Next day | Day 1, 1,000 (as decided) | PASS |

So the registration-day promise (1,500) doesn't match what tomorrow actually pays (1,000).

## 3. Once a day

| Check | Steps | Actual | Result |
|---|---|---|---|
| 12 parallel POSTs from 3 sessions | q44b, new day | 1×201 (collected 1,000) + 11×200 (collected 0); balance +1,000; 1 ledger row | PASS |
| Double click on Collect | UI, two clicks | One request (the second click is ignored), header 5,000 → 6,000 "+1,000", 6 chips flew, the popup closed, 1 ledger row | PASS |
| Two tabs collecting at once | q44e at 1440 and 390, both popups open, Collect clicked in both at the same time | 1 ledger row; balance +1,000. Both tabs ended at 6,000; both flew chips (the losing tab's balance really did change too) and both closed | PASS |
| API repeats | See the table above | `collected: 0`, status 200, every time | PASS |

## 4. Time zone (Asia/Yerevan, UTC+4, no DST)

Frozen clock on account q44a, which collected on 2026-11-02 (Yerevan):

| UTC | Yerevan | Server "today" | Status |
|---|---|---|---|
| 2026-11-02 19:59:59 | 23:59 | 11-02 | claimed, day 1 |
| 2026-11-02 **20:00:00** | 11-03 00:00 | **11-03** | **unclaimed, day 2, 1,500** |
| 2026-11-02 21:00:00 | 11-03 01:00 | 11-03 | unclaimed, day 2 |
| 2026-11-03 19:59:59 | 11-03 23:59 | 11-03 | still day 2 (nothing collected that day) |
| 2026-11-03 20:00:01 | 11-04 00:00 | 11-04 | day 1 (a missed day resets) |

**PASS.** Note for the brief: midnight in Yerevan is 20:00 UTC, not 21:00 UTC (UTC+4).

The client takes the day from the server only. A page left open across midnight picks up the new day
when the window regains focus or the page reloads; it doesn't refresh at midnight on its own (O-2).

## 5. The popup

Accounts q44d / q44e / q44c at 1440 and 390:

| Check | Actual | Result |
|---|---|---|
| Offers itself on the first page of a visit while waiting | Yes (/dashboard, /wallet, /games…). It is a `role=dialog` with `aria-modal`, labelled and described. The app behind is `inert`, the body doesn't scroll, and focus starts in the dialog | PASS |
| Focus trap | Tab cycles Later → Collect → (the document) → Later…; Shift+Tab goes the other way. It never reaches a control in the app behind, because the root is `inert` | PASS |
| Escape | Closes; `inert` and the scroll lock are removed; focus returns to where it was (the body after an automatic popup, the card's button when opened from the wallet) | PASS |
| Backdrop click | Closes; focus returns to the opener | PASS |
| Later | Closes; SPA navigation (/games, /wallet) doesn't offer it again in the same visit | PASS |
| Reload (a new visit) | Offered again while waiting | PASS |
| Wallet card | Shows "Daily bonus · Day N · amount" with Collect while waiting, and "Collected" with Open after collecting; both open the popup | PASS (qa2/t44-card-*.png) |
| Never on a room page | q44c (waiting) loaded `/rooms/<poker room>` directly: no popup in 12 s, nor on the game-over panel. It was offered as soon as the player left to /games/poker | PASS |
| After collecting | Reload → no popup | PASS |
| Switching accounts in one tab | q44e (waiting) → Later → log out → log in as q44a (waiting) in the same tab: **no popup** for q44a until a reload | **BUG-2** |

## 6. Admin → Settings → Fishkas

| Check | Result |
|---|---|
| Fields "Daily bonus, day 1 … day 5 and on" are present with helper text | PASS |
| Day 2 1,500 → 1,700 and day 5 5,000 → 6,000, Save: audited (`settings.updated` id 52, old → new values); `GET /me/daily-bonus` shows `days` [1,000, 1,700, 2,000, 2,500, 6,000]; a day-2 collect paid 1,700 ("Daily bonus, day 2") | PASS |
| Restored through the admin UI: 1,500 and 5,000 (audit id 53). The DB has `daily_bonus_day_2 = 1500` and `day_5 = 5000`; day 1, 3 and 4 were never changed | PASS |

Evidence: qa2/t44-admin-changed.png.

## 7. Regression

| Check | Actual | Result |
|---|---|---|
| Register through the UI (q44reg02407) | /verify → code from Mailpit → /dashboard; header 5,000; no daily popup (registration day) | PASS (qa2/t44-reg-dashboard-390.png) |
| Log out and log in through the form | /dashboard, no popup (same day) | PASS |
| No silent credit | q44e with a bonus waiting: log in, then GET /me twice → balance unchanged (6,000); the bonus still waits | PASS |
| Out-of-Fishkas popup | q44c at 0 Fishkas on /games/nardi → Start game → "Your Fishkas Have Run Out!" → "Get 5,000 Free Fishkas" → "+5,000 Fishkas!"; header "5,000 +5,000". No daily popup in the way (already collected that day) | PASS (qa2/t44-zero-bonus-390.png) |
| Header balance | Rises once by the bonus with a green "+N" (5,000 → 6,000; 6,000 → 8,000; 8,000 → 13,000) | PASS |
| Wallet history | Rows show "Daily bonus · date · +1,000 · balance" (ru "Ежедневный бонус", hy "Օրական բոնուս"). The **day number is not shown** (the ledger description "Daily bonus, day N" is not used) | **BUG-3** |

## 8. Layout and chips

| Check | Actual | Result |
|---|---|---|
| 390 × 844 (en/ru/hy, days 1–5 and collected) | The dialog is a bottom sheet (x 12–378). No clipped text in any tile or line (no element where scrollWidth > clientWidth). scrollWidth 390 | PASS |
| 1440 × 900 (en / ru / hy) | Centred dialog (432–1008). Nothing clipped; scrollWidth 1440 | PASS |
| Chips fly to the header | 6 chips per collect. Each chip's last keyframe ends on the header balance pill's centre: 390 → (280,33) and (275,33); 1440 → (1241,65). The pill bumps by "+N"; the popup closes about 0.7 s after they land | PASS (qa2/t44-ru-390-day3c-flying.png, qa2/t44-hy-1440-day5c-flying.png) |

## Bugs

### BUG-1 — Low — The registration day promises 1,500 for tomorrow, but tomorrow pays 1,000

- **Steps:** register a new account; open Wallet → Daily bonus → Open (same day).
- **Expected:** a message that matches what the next day really gives. That is 1,000 (day 1): on the
  registration day `daily_streak` is 0, so the next day is day 1.
- **Actual:** "Today's bonus is collected. Come back tomorrow for 1,500." with Day 1 ✓ lit. The next day the
  popup offers Day 1, 1,000 again.
- **Evidence:** `t44card.mjs q44c` output; qa2/t44-card-reg-day.png; the API walk (registration day → next
  day = day 1).
- **Suspected:** `apps/web/src/features/wallet/components/DailyBonusDialog.tsx`, where
  `tomorrow = days[min(day, last-1)]` assumes the shown day was collected. Two possible fixes:
  - **Backend:** add `next_amount` to `GET /me/daily-bonus` in `FishkaWallets::dailyStatus`, e.g.
    `dailyAmount(nextDay)`.
  - **Frontend:** treat streak 0 as "day 0" so tomorrow is day 1.

### BUG-2 — Low — After switching accounts in the same tab, the second account isn't offered its bonus

- **Steps:**
  1. Account A has its bonus waiting: the popup appears; click Later.
  2. Log out from the account menu.
  3. Log in as account B, whose bonus is also waiting, in the same tab.
- **Expected:** B is offered its daily bonus.
- **Actual:** no popup for B until the page is reloaded; then it shows. B can still collect from the wallet
  card.
- **Evidence:** `t44switch.mjs` output: "q44a signed in (same tab) … no popup", "after reload: daily
  popup…".
- **Suspected:** `apps/web/src/features/wallet/dailyBonus.ts`, where `shownThisVisit` is per page load
  instead of per user. Reset it on logout or user change (for example from `useLogout` / when the current
  user's id changes).

### BUG-3 — Low — The wallet history doesn't show which day of the streak a bonus was

- **Steps:** collect day 2 or 5; open /wallet.
- **Expected:** "Daily bonus, day N" in the history (the ledger stores it; the coordinator's checklist
  expects it).
- **Actual:** only "Daily bonus" with the amount, in en, ru and hy.
- **Evidence:** `t44hist.mjs` output; qa2/t44-history-{en,ru,hy}-390.png.
- **Suspected:** `apps/web/src/pages/WalletPage.tsx:135` labels rows only by
  `t('fishka.type.' + type)`. Either send the day as structured data (e.g. `meta.day`) from
  `FishkaWallets::claimDaily` and render "Daily bonus · day N" in each language, or show the description.

## Observations

- **O-1:** while the popup is open, the header (where the chips land) is under the dimmed, blurred backdrop,
  so the "+N" bump is hard to see until the popup closes about 0.7 s later. Consider closing first, or
  lifting the balance pill above the backdrop during the flight. See qa2/t44-hy-1440-day5c-flying.png.
- **O-2:** a page left open and focused across midnight doesn't notice the new day until it regains focus
  or reloads (`refetchOnWindowFocus` only). This is fine for "once per visit".
- **O-3:** in the account-switch test the header animated "+19,000" when the second account signed in. The
  header pill compares the old account's balance with the new one's (`FishkaBalance.tsx`, not new in
  TASK-044). It should reset its "seen" balance when the user changes.

## Data and cleanup

- **Test accounts** (dev DB, password `password`): q44a 25,000, q44b 17,000, q44c 5,000 (set to 0 for
  the out-of-Fishkas test, then took the 5,000 bonus), q44d 6,000, q44e 6,000, q44reg02407 5,000. Their
  dates and streaks were moved with `dayset.sh`, and renamed ledger keys end in `:qa-rewind-…`. No other
  accounts were changed; player2's recent stakes at 12:3x UTC are not from this run.
- **Settings:** the daily amounts are back to the defaults (1,000 / 1,500 / 2,000 / 2,500 / 5,000; audit
  ids 52 and 53); `reactions.enabled` is still true.
- **Rooms:** none waiting or active (DB checked). The one poker room opened for the room-page test was left.
- **Browser:** signed in as player2@example.test, in English, on the lobby; no daily popup there.

## Fix status (developer, 2026-11-02)

| Finding | Status | Fix | Verified |
|---|---|---|---|
| BUG-1 "Come back tomorrow for 1,500" on the registration day | Fixed | GET/POST /me/daily-bonus return `tomorrow` (what tomorrow gives if the streak goes on; day 1 after the opening day); the popup shows it | DailyBonusTest ("says what tomorrow gives"), DailyBonus.test ("says what tomorrow brings") |
| BUG-2 a second account in the same tab isn't offered its bonus | Fixed | "Shown this visit" is kept per player; the wallet sync and the dialog are keyed by account | Code; DailyBonus tests |
| BUG-3 history without the day | Fixed | Ledger rows of a daily bonus carry `day`; the wallet shows "Daily bonus · Day N" | DailyBonusTest ("shows the streak day … in the wallet history") |
| Obs. header "+19,000" on an account switch | Fixed | The header balance is keyed by account (starts fresh) | Code |
| Obs. header under the backdrop during the flight; a page open across midnight | Not changed | The chips land after the popup closes; the day refreshes on focus/reload (acceptable) | — |
| Note: midnight is 20:00 UTC | — | Correct (UTC+4); my note said 21:00 by mistake | — |

