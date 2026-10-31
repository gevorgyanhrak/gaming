# QA report: Fishka virtual currency

- **Tested:** 2026-10-30, local Docker stack. SPA at http://localhost:5175 (API under /api/v1), admin at http://localhost:8000/admin.
- **Build under test:** uncommitted working tree on `main` (on top of `e59414a`), including `FishkaWallets`, `RoomStakes`, `features/wallet/*` and `WalletPage`.
- **Browser:** headless Chrome over CDP, at 390×844 (mobile emulation) and 1440×900. The admin panel ran in a separate, isolated browser context.
- **Screenshots:** all file names below are in `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/qa/`.
- **Result:** 44 checks, **39 PASS, 5 FAIL**, 10 bugs (2 High, 2 Medium, 6 Low). Details are below.

## Test accounts created (local DB)

| User id | Login | Password | Role |
|---|---|---|---|
| 7 | qa1-1559528599@example.test (qa1u32216) | `Qa-Fishka#7731zx` | player (main test account) |
| 8 | qa1-admin-7389@example.test (qaadm3541) | `Qa-Fishka#7731zx` | super_admin; also played as the second player |

Both were registered and verified through the SPA, using codes from Mailpit.

## Where the API or tinker was used instead of the UI

Everything else went through the UI, by clicking real buttons and filling forms in headless Chrome.

| What | Why |
|---|---|
| tinker: set `daily_bonus_on` back to yesterday (user 7 ×2, user 8 ×1) | Simulates the next day, as instructed. |
| tinker: `balance = 0`, `refilled_at = null` on user 7, 4 times | Resets the state for the repeated refill double-click trials (7.5). |
| tinker: `refilled_at = null` on user 7, once | Shows the popup's "bonus available" state in Armenian (10.2). |
| tinker: user 8 `balance = 0` | Tests joining a private table at 0, since an admin can't change their own wallet in the panel. |
| API `POST /rooms/{id}/leave` | Cleans up the extra rooms created by Bug 1 (5 rooms). |
| API `POST /rooms`, `/matchmaking`, `/me/wallet/refill`, `/auth/login`, `/rooms/{id}/actions` | Reads the raw response bodies (422 codes) and tries an over-stack raise that the UI doesn't allow. Each of these was also exercised through the UI. |
| API `GET /me` ×5 in parallel | Concurrency check on the daily bonus. |
| tinker reads | Ledger and balance checks after each step. |

**Side effect:** the tinker balance resets bypassed the ledger. As a result, "Total earned/spent" for users 7 and 8 are inflated, and the ledger has 4 extra "Free Fishka bonus 0 → 5,000" rows. This is a test artefact, not a bug.

## Results

| # | Scenario | Steps | Expected | Actual | Result | Screenshots |
|---|---|---|---|---|---|---|
| 1 | Registration | Log out (UI), register by email at 390, verify with the Mailpit code, open /wallet | Header shows 5,000; /wallet shows "Welcome bonus +5,000" | Header 5,000 right after sign-up; /wallet balance 5,000 with one "Welcome bonus +5,000"; ledger `registration_bonus` with key `registration:7` | PASS | 01-after-register, 01-wallet-390 |
| 2.1 | Same-day re-login | Log out and log in (UI) | No daily bonus | Still 5,000; ledger has only the welcome bonus | PASS | 02-relogin-sameday |
| 2.2 | Next-day login | Log out; **tinker** `daily_bonus_on` = yesterday; log in (UI) | +1,000 | Header 6,000; "Daily bonus +1,000" | PASS | 02-nextday-login-anim, 02-nextday-login |
| 2.3 | Exactly once | Reload /wallet twice, log out and in again; also 5 parallel `GET /me` (API) | No second bonus | One `daily_login_bonus` (key `daily:7:2026-10-30`); balance stays 6,000 | PASS | 02-wallet-after-daily |
| 2.4 | Daily bonus on page load (no new sign-in) | User 8 signed in; **tinker** date = yesterday; load /wallet, reload ×2 | +1,000 once | 4,500 → 5,500; exactly 1 daily entry | PASS | 02-pageload-daily-u8 |
| 3.1 | Play and win: Durak | /games/durak, Easy, Start game, autoplay | Win | "You won!" | PASS | 03-durak-over |
| 3.2 | Fee charged once (normal click) | Start a game with one click (Play again, CDP double-click with a 60 ms gap; Start game with 30 ms) | −500 once, in header and history | One room, one −500; header animates "−500"; history "Game entry −500" | PASS | 03-durak-room, 04-wallet-after-loss |
| 3.3 | No double charge on repeated clicks | Triple `click()` on "Start game"; real CDP double-click with ~0 ms gap | One room, one charge | **3 active rooms and −1,500** (triple click); **2 rooms and −1,000** (0 ms double-click). See Bug 1 | **FAIL** | 03-durak-room |
| 3.4 | Matchmaking (PvP) | Users 7 and 8 both press "Play" on Chess | Each pays 500 once, when matched | Nothing charged while searching; both −500 with the same room reference; user 8 resigned and user 7 got "You won!" | PASS | 03-mm-searching-u7, 03-mm-matched-u7, 03-mm-won-u7 |
| 3.5 | Poker buy-in | Poker, 2 players, Easy, Start game with balance 4,000 | Buy-in min(4,000, 1,000) = 1,000; no entry fee | `poker_bet −1,000` (4,000 → 3,000); lobby shows "Buy-in: your own Fishkas" | PASS | 03-poker-lobby, 03-poker-room |
| 3.6 | Poker win and cash-out | Play until the stack is above 1,000, then Resign | Chips go back to the wallet as `poker_winning` | 3,000 + 1,015 = 4,015 (net +15). Of the 1,015, 10 was the posted small blind; see Bug 2 | PASS (math) | 03-poker-ahead, 03-poker-left |
| 4 | Play and lose | Durak game, Resign → "Yes, resign" | "You lost."; fee not refunded | "You lost."; balance stays 4,000; no refund entry | PASS | 04-resigned, 04-wallet-after-loss |
| 5 | Exactly 500, lose, reach 0 | Admin "Set exact balance" 500 (UI); Checkers bot game; Resign | Balance 0 | Admin set to 500 (open SPA tab updated live), game −500 → 0, "You lost." | PASS | 05-admin-set-500, 05-header-live-500, 05-lost-at-zero |
| 6.1 | 0 balance: bot game | Start game (Checkers 390, Durak 1440) | Popup with exact copy; 422 `insufficient_fishkas`; no room | Popup "Your Fishkas Have Run Out!" / "Get 5,000 Free Fishkas" / "Cancel"; API `422 {"code":"insufficient_fishkas","balance":0,"needed":500}`; room count unchanged | PASS | 06-zero-bot-popup-390, 06-zero-bot-popup-1440 |
| 6.2 | 0 balance: private table | "Create table" (Chess 390, Durak 1440) | Same | Same; no room | PASS | 06-zero-private-390, 06-zero-private-1440 |
| 6.3 | 0 balance: matchmaking "Play" | "Play / Find an opponent" | Same | Same; `/matchmaking` 422; not queued | PASS | 06-zero-matchmaking-390, 06-zero-matchmaking-1440 |
| 6.4 | 0 balance: "Play again" | Game-over panel, Play again (Checkers, Poker) | Same | Blocked, 422, no room; the popup is in the DOM, **but at 390 it renders below the fold**; see 6.7 | PASS (functional) | 06-zero-playagain-popup, 08-poker-zero-popup |
| 6.5 | 0 balance: join with invite code | User 8 at 0 joins user 7's waiting Durak table | Popup; no seat | Popup; `/rooms/join` 422; room still has 1 seat | PASS | 06-zero-join-invite-390 |
| 6.6 | 499 balance: all paths | Admin set 499; bot, private, matchmaking, Play again | Blocked + popup | All blocked, no rooms; copy reads "You have 499 left; a game costs 500." | PASS | 07-cooldown-499-390, 06-499-playagain-390 |
| 6.7 | Popup is visible and usable | Same flows at 390 and 1440 | Popup centred (desktop) or bottom sheet (mobile) inside the viewport, buttons reachable | **390:** after Play again the panel sits at y=838 in an 844 px viewport, so the user sees only a blurred page; in the lobby, the bottom nav covers "Cancel"/"Continue". **1440:** panel bottom at 918 px in a 900 px viewport, not centred. See Bug 3 | **FAIL** | 06-zero-playagain-popup-settled, 06-zero-bot-popup-390, 06-zero-bot-popup-1440 |
| 7.1 | Refill: loading state | Click "Get 5,000 Free Fishkas" with 1.5 s added network latency | Loading state | "Adding Fishkas…", button disabled + `aria-busy`, Cancel disabled | PASS | 07-refill-loading |
| 7.2 | Refill: exactly 5,000, one entry | From 0; includes 5 double-click trials | Balance 5,000; exactly one "Free Fishka bonus" per refill | Every trial: balance 0 → 5,000 and exactly 1 `insufficient_balance_bonus`; the second request got 422 | PASS | 07-refill-success |
| 7.3 | Header updates | Watch the header after refill | 5,000 with animation | "5,000" with a floating "+5,000" | PASS | 07-refill-success |
| 7.4 | Continue, then play | Click "Continue", then Start game | Popup closes; game starts | Closed; game started (−500 → 4,500) | PASS | 07-game-after-refill |
| 7.5 | Fast double-click result in the UI | 4 trials (2× synchronous double `click()`, 2× CDP double-click with a 15 ms gap) | Success screen "+5,000 Fishkas! / Continue" | **2 of 4 trials** showed the failure state ("You still have enough Fishkas to play." + Get button) although the bonus was granted. See Bug 4 | **FAIL** | 07-race-trial-1 … 07-race-trial-4 |
| 7.6 | Bonus refused within 24 h | Balance 499 (admin) after a refill | Button disabled, "next bonus" text, API 422 | Button disabled; "The next free bonus is available Oct 31, 2026, 4:48 PM."; API `422 {"code":"fishka_bonus_unavailable","available_at":"2026-10-31T12:48:36+00:00"}` | PASS | 07-cooldown-499-390 |
| 7.7 | Refund when a waiting room is left or closed | Creator closes a waiting Chess table; user 8 joins a 3-seat Durak table and leaves | Fee refunded | Creator 4,000 → 4,500 ("Entry refunded +500"); joiner 4,500 → 5,000; room stays waiting for the creator | PASS | 07-private-waiting, 07-private-closed, 07-private-joined-u8 |
| 8.1 | Poker with exactly 1 Fishka | Admin set 1; Poker, 2 players, Start | Can sit down | Seated, buy-in −1. All-in on the blind decided the hand at the deal: "You lost.", balance 0 | PASS | 08-poker-1-fishka |
| 8.2 | Poker with 0 | Play again | Popup | Popup; API `422 needed:1` | PASS | 08-poker-zero-popup |
| 8.3 | Can't bet more than your chips | UI: type 5,000 into the bet field. API: raise to 5,000 and to 1,001 with a 1,000 stack | Clamped or refused | UI clamps to "All-in 1000"; API `422 This move is not allowed.` for both | PASS | 08-poker-controls, 08-poker-overbet |
| 8.4 | Resigning mid-hand (heads-up) | Raise to 300, bot calls; on the flop (stack 710, 300 in the pot) Resign | Cash out 710 (chips in the pot are forfeited, as a fold or as leaving a multi-seat table) | **Cashed out 1,010**: the 300 in the pot came back. See Bug 2 | **FAIL** | 08-poker-resign-midhand |
| 9.1 | Admin add / deduct / set | User 7 → Fishkas menu → Add 1,000, Deduct 200, Set 500/499/1/0, each with a reason | Balance changes, notifications | "Balance is now 4,500 / 4,300 / 500 …" each time; ledger `admin_credit`/`admin_debit` with actor, reason, before/after; "Set" stores "Balance set to N" | PASS | 09-admin-add, 09-admin-deduct, 05-admin-set-500 |
| 9.2 | Deduct more than the balance | Deduct 999,999 from 4,300 | Refused | Notification "The balance cannot go below zero (it is 4300)."; no change, no audit entry | PASS | 09-admin-overdeduct |
| 9.3 | Validation | Empty reason, amount 0, set −5, whitespace-only reason | Refused | Form stays open ("Please fill out this field." etc.); balance unchanged | PASS | 09-admin-noreason |
| 9.4 | Fishka transactions tab | Admin user page → "Fishka transactions" | Ledger with type, amount, before/after, reason, admin | Present, paginated (28 rows), type filter | PASS | 09-admin-fishka-transactions |
| 9.5 | Audit log | Admin → Audit log | One `fishka.adjusted` per change | 5 entries for 5 successful adjustments; detail shows mode, amount, before/after, reason, transaction_id, IP, UA | PASS | 09-admin-audit-log, 09-admin-audit-detail |
| 9.6 | Ban: can't log in or play | Admin Ban (reason "QA test ban") | Session ends; login refused | Open session signed out immediately (lobby shows "Log in to play"); login form and API answer 422 | PASS | 09-admin-banned, 09-banned-session, 09-banned-login |
| 9.7 | Ban: clear message | Same | Clear message | Only "This account is not active.", with no mention of a ban, reason, end date or what to do. The open session gets no message at all. See Bug 6 | **FAIL** (Low) | 09-banned-login, 09-banned-session |
| 9.8 | Unban | Admin Unban → Confirm; log in | Can log in; balance kept | Status active; login OK; balance 4,300 kept | PASS | 09-admin-unbanned, 09-unbanned-login |
| 9.9 | Live balance after an admin change | Admin sets 500 while the player's tab is open | Header updates without reload | Header went 4,025 → 500 with "−3,525" (websocket) | PASS | 05-header-live-500 |
| 10.1 | Russian at 390 | Popup (cooldown state) and /wallet | No overflow | `scrollWidth` = 390 on both; nothing clipped; translations complete | PASS | 10-ru-popup-390, 10-ru-wallet-390 |
| 10.2 | Armenian at 390 | Popup (available, loading and success states) and /wallet | No overflow | No overflow; long title wraps over 2 lines. Dates/numbers render in English format (Bug 10) | PASS | 10-hy-popup-390, 10-hy-popup-loading, 10-hy-popup-success, 10-hy-wallet-390 |
| 10.3 | Header pill on mobile | 390, several pages | Visible, links to /wallet | Visible (92 px wide), brand name truncates instead; links to /wallet | PASS | 00-register-start, 01-wallet-390 |
| 10.4 | Console errors | Watched on every step | No errors | No JS exceptions or `console.error`. Only the existing React Router "No HydrateFallback" warning, expected 422s, and a 401 on `/me/wallet` after logout (Bug 7) | PASS | — |

The 1440 wallet page is in 10-en-wallet-1440.

## Bugs

### Bug 1 (High): concurrent "start game" requests bypass the one-game limit and charge the entry fee several times
- **Repro:**
  1. User 7 (6,000 Fishkas) opens /games/durak.
  2. Select Easy.
  3. Fire 3 clicks on "Start game" within the same task (`b.click(); b.click(); b.click()`).
  4. Alternatively, do a real mouse double-click with no gap between the two clicks.
- **Actual:** 3 bot rooms, all `active`, and 3 × `game_entry_fee −500` (6,000 → 4,500). With the 0 ms CDP double-click, 2 rooms and −1,000. The setting `matchmaking.max_concurrent_games = 1` is violated.
- **Expected:** one room and one charge; the other requests get 422 "too many games".
- **Not affected:** double-clicks 30–60 ms apart were safe (the button disables after re-render). The window is small but real: double taps, two tabs, client retries.
- **Suspected cause:**
  - `RoomController::store` → `PlayRules::ensureCanStart` checks the active-game count and then creates the room, with no lock (check-then-act).
  - The stake idempotency key is per participant (`stake:{participant_id}`), so it can't dedupe separate rooms.
  - On the client, `PlayPanel` and `GameOverPanel` disable the button via `isPending`, which TanStack Query updates asynchronously.
- **Fix:**
  - Serialize starts per user with an atomic lock around check + create (`Cache::lock("play:{$user->id}")->block(...)`), or lock the wallet or user row (`lockForUpdate`) inside the same transaction. See the Laravel docs on [atomic locks](https://laravel.com/docs/cache#atomic-locks) and [pessimistic locking](https://laravel.com/docs/queries#pessimistic-locking).
  - Optionally add a synchronous `useRef` guard on the client.

### Bug 2 (Medium): heads-up Poker, resigning mid-hand returns the chips already in the pot
- **Repro:**
  1. Poker, 2 players, Easy, buy-in 1,000.
  2. Raise to 300 preflop; the bot calls.
  3. On the flop (stack 710, committed 300) press Resign → Yes.
- **Actual:** `poker_winning +1,010`. The same happened with a posted blind: stack 1,005 + 10 blind gave 1,015, and stack 990 + 10 gave 1,000.
- **Expected:** 710. A resign mid-hand should forfeit the chips in the pot, like a fold. That is what the multi-seat "leave" path does ("chips already in this hand's pot stay in it").
- **Impact:** a free undo of any losing bet. In a PvP heads-up game, the opponent never receives the pot they were about to win.
- **Suspected cause:**
  - `GameActionService::end()` calls `$this->stakes->settle($room, $current->state)` with the state **before** the resignation.
  - `PokerEngine::chipsOf()` returns stack + committed for a seat that hasn't folded.
  - In contrast, `depart()` cashes out with `$transition->state`, which is after the fold.
- **Fix:** settle with the post-resign state (the leaver folded), or have `chipsOf` exclude the resigning seat's committed chips.

### Bug 3 (High): the "out of Fishkas" popup is positioned against the page, not the viewport
- **Repro A (390×844):**
  1. Lose a game with 0 balance.
  2. Click "Play again" on the game-over panel.
  3. Result: the page blurs but no dialog is visible. The panel's top is at y=838, and the buttons are at y≈1,135–1,235.
- **Repro B (390, lobby):** Start game with 0. Focus scrolls the page so the panel is visible, but the fixed bottom nav covers "Cancel" (and "Continue" after a refill).
- **Repro C (1440×900):** the panel is not centred; its bottom is at 918 px, so it is clipped.
- **Expected:** a centred modal or bottom sheet inside the viewport, above the bottom nav.
- **Cause:**
  - `PublicLayout`'s wrapper `div.club-frame` has `backdrop-filter: blur(6px)`. Per [MDN, position: fixed](https://developer.mozilla.org/en-US/docs/Web/CSS/position#fixed_positioning), a `backdrop-filter`/`filter`/`transform` ancestor becomes the containing block.
  - So `fixed inset-0` covers the whole document (1,275–2,520 px tall) instead of the viewport.
  - Files: `apps/web/src/features/wallet/components/OutOfFishkasDialog.tsx`, rendered inside `PublicLayout.tsx`.
- **Fix:** render the dialog with [`createPortal(..., document.body)`](https://react.dev/reference/react-dom/createPortal), or use a native `<dialog>` with `showModal()` ([MDN](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/dialog)).

### Bug 4 (Medium): fast double-click on "Get 5,000 Free Fishkas" can show a failure after a successful refill
- **Repro:**
  1. Balance 0, bonus available.
  2. Open the popup.
  3. Double-click "Get 5,000 Free Fishkas" very quickly (synchronous double `click()`, or CDP clicks 15 ms apart).
- **Actual:**
  - Two `POST /me/wallet/refill` requests are sent. The server is correct: exactly one bonus entry, balance 5,000.
  - But in 2 of 4 trials the popup stayed on the "Run Out" screen with the red alert "You still have enough Fishkas to play." and the Get button, saying "You have 5,000 left; a game costs 500."
- **Expected:** the success screen "+5,000 Fishkas!" with "Continue".
- **Cause:**
  - `disabled={!canTake}` depends on `refill.isPending`, which updates asynchronously, so both clicks call `mutate()`.
  - `useMutation` reports the latest call's result, which is the losing 422 ([TanStack `useMutation`](https://tanstack.com/query/latest/docs/framework/react/reference/useMutation): only the latest `mutate` call's callbacks fire).
- **Fix:** ignore clicks while a request is in flight (a `useRef` flag or a `mutation.isPending` check inside the handler). Alternatively, treat `fishka_bonus_unavailable` after a success as success.

### Bug 5 (Low): popup copy changes while the refill request is still pending
- **Repro:** click "Get 5,000 Free Fishkas" with network latency.
- **Actual:** while "Adding Fishkas…" is shown, the websocket `wallet.updated` arrives. The body becomes "You don't have enough Fishkas to play this game. You have 5,000 left; a game costs 500." This is self-contradictory (seen in English and Armenian).
- **Expected:** a stable copy until the success screen.
- **File:** `OutOfFishkasDialog.tsx` builds the body from the live `wallet.data.balance`. Freeze the balance at open, or hide `outLeft` while busy.

### Bug 6 (Low): banned user gets an unclear message
- **Repro:** ban user 7 in admin, then try to log in.
- **Actual:** "This account is not active." Nothing says the account was banned, why, or until when. An already-open tab is silently signed out with no notice.
- **Expected:** a clear message, e.g. "Your account has been suspended until … Reason: … Contact support.", with a matching notice for the open session.
- **Likely:** the existing login request / moderation code, not the Fishka work. Reference: [NN/g, error message guidelines](https://www.nngroup.com/articles/error-message-guidelines/).

### Bug 7 (Low): `GET /api/v1/me/wallet` returns 401 after logout
- **Repro:** log out from the user menu.
- **Actual:** the console shows "Failed to load resource: 401 … /api/v1/me/wallet" on every logout.
- **Cause:** `app/queryClient.ts` runs `MutationCache.onSettled` and invalidates `walletKey` after **every** mutation, including logout. The wallet query refetches after the session ends.
- **Fix:** skip the invalidation when signed out, or `removeQueries({ queryKey: walletKey })` on logout.

### Bug 8 (Low, cosmetic): "Total spent −0"
- **Repro:** /wallet for a new user.
- **Actual:** "TOTAL SPENT −0" in red.
- **Expected:** "0".
- **File:** `apps/web/src/pages/WalletPage.tsx`.

### Bug 9 (Low, copy): singular/plural
- **Actual:**
  - The API message says "You need 1 Fishkas to play this game." (`lang/en/platform.php`, `fishka.insufficient`).
  - The admin notification says "Balance is now 1 Fishkas." (`ViewUser.php`).
- **Fix:** use [`trans_choice`](https://laravel.com/docs/localization#pluralization).

### Bug 10 (Low, unconfirmed): Armenian locale shows English date and number formats
- **Observed:** with Armenian selected, wallet history dates show "Oct 30, 2026, 5:04 PM" and numbers "5,000". Russian is localized ("30 окт. 2026 г.", "5 000").
- **Possible cause:** the headless Chrome build's ICU data has no `hy` locale. Recheck in desktop Chrome before fixing.

## Not tested, or limits

- **Calendar day boundary:** the real midnight rollover in Asia/Yerevan wasn't tested, because the server clock can't be moved. "Next day" was simulated with tinker.
- **Repeating "next day":** a second simulated next day on the same real date gives no bonus, because the idempotency key is `daily:{user}:{date}`. This is expected.
- **Refill at 499:** a refill while at 499 with the bonus available wasn't granted live. By the code it adds 5,000 (→ 5,499), not "to 5,000". The "exactly 5,000" check was done from 0.
- **Poker gaps:**
  - No full Poker game was played to the natural end with a bot busting out. The end-of-game settle was only seen in the 1-Fishka instant loss.
  - Multi-seat "leave mid-hand" (the `depart()` path) wasn't exercised; I read the code only.
- **Matchmaking:** the bot fallback (`BotFallbackMode::Auto`) with insufficient funds wasn't tested.
- **Setup command:** `php artisan app:role` takes `<login> <role>`, the reverse of the order in the test brief.

## State at the end

- No active games or waiting rooms for users 2, 7 and 8.
- Language set back to English.
- The headless browser is signed in as player2@example.test.
- The isolated admin browser context was closed.
- No application code or committed files were changed. Only this report was added.
