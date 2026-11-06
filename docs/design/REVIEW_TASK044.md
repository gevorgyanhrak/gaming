# Design review — TASK-044 · Daily bonus popup with a login streak (2026-11-06)

UI/UX agent. Build: `main` at `6c816e4`. Decisions: `docs/team/PROGRESS.md` "Now" (5 days, amounts
from settings, claimed in the popup). QA: `docs/qa/QA_REPORT_TASK044.md` (all fixed).

## Verdict

**NEEDS REVISION** — one small required fix (issue 1, Medium). Issues 2–6 are Low and recommended in
the same pass; they don't block approval.

It matches the owner's reference in structure:
- title;
- a row of five arrow-shaped day tiles with today lit;
- "Play every day… N days to go until your double bonus!";
- an open chest with chips and the amount.

It does so in our own card-club art (emerald felt to warm wood, gold), with nothing copied. The
visual quality is good at 390 and 1440 in en, ru and hy.

The one real UX miss is the payoff moment, which the owner asked for explicitly: the chips fly into
a header that is dimmed and blurred under the backdrop (issue 1). Once that is fixed and shown in a
mid-flight screenshot, the task can be approved without another full review.

## What I checked

All live, as the test account `q44daily` in its own browser context (`qa2/daily2.mjs`, plus my
`qa2/rv44.mjs` for the wallet card, keyboard, accessibility tree, reduced motion and the collected
state).
- **Days** were set through `qa2/dayset.sh`: today's ledger key renamed, `daily_bonus_on` and
  `daily_streak` changed; only this test account was touched.
- **Screenshots** are in `scratchpad/review/`:
  - `d44-r390en-*`, `d44-r1440en-d3-*`, `d44-r1440en-d1-open.png`;
  - `d44-r390hy-d5-open.png`, `d44-r1440hy-d5-open.png`, `d44-r390ru-d1-open.png`;
  - `d44-rv-wallet-390.png`, `d44-rv-reduced-390.png`, `d44-rv-collected-390.png`.

| State | Where |
|---|---|
| Day 1 | 390 ru, 1440 en |
| Day 2 | 390 en: collected with reduced motion; the wallet card before and after; reopened from the wallet |
| Day 3 | 390 en and 1440 en: collected with the flight |
| Day 5 (double) | 390 hy, 1440 hy |

## What works

- **Hierarchy:** the gold-gradient title, then the day row, then the message, then the chest and
  the big amount, then one gold "Collect" with a secondary "Later". The primary action is obvious.
- **Contrast (computed):**

  | Text | Background | Ratio |
  |---|---|---|
  | Black | today's gold tile | 6.3–9.8:1 |
  | White/80 | past emerald tile | 5.9:1 |
  | White/85 | upcoming wood tile | 9.7:1 |
  | Message text | felt / wood | 9.9 / 11.4:1 |
  | Gold-light amount | wood | 10.2:1 |

- **Tiles:**
  - Collected days show ✓; today glows and is scaled up; day 5 shows "×2".
  - The amounts are formatted per language ("1 000" ru, "1,000" hy/en).
  - The hy uppercase title "ՕՐԱԿԱՆ ԲՈՆՈՒՍ" and the ru two-line «ЕЖЕДНЕВНЫЙ БОНУС» fit; no horizontal
    scroll at 390 or 1440.
- **Chest:** original SVG. Wood and brass, with the lid up, chips heaped and a soft glow while
  there's a bonus. Closed when collected. Fits the club look.
- **Collect:**
  - The amount turns to "+2,000", the message becomes "Today's bonus is collected. Come back tomorrow
    for 2,500", and the buttons reduce to "Close".
  - The header pill shows the new balance with its "+2,000" badge.
  - The ledger has one entry (`daily:18:2026-11-06`).
- **Reduced motion (emulated):** no chip flight (0 chips added) and no animations; the bonus is still
  credited (16,500 after +1,500).
- **Modal:** opens with focus on the dialog, `#root` inert, Escape closes, focus returns. The popup
  also offers itself on `/wallet` the first time in a visit.
- **Wallet card:** "Daily bonus · Day 2 · 1,500" with a 44 px "Collect", then "Day 2 · Collected"
  with "Open", which reopens the collected dialog.

## Issues

### 1 · Medium — chips land in a header you can't see

| | |
|---|---|
| Seen | After Collect the chips fly to the header balance, but the header sits under the `bg-black/70` + `backdrop-blur-sm` backdrop. The pill, its new number and the "+2,000" badge are dark and blurred while the chips land (`d44-r1440en-d3-collecting.png`, `d44-r390en-collecting.png`). The reference's payoff is "you see the chips arrive"; here it's lost |
| File | `features/wallet/components/DailyBonusDialog.tsx` (`onSuccess` → `flyFishkas`, then `closeDailyBonus` after 700 ms) |
| Fix | Close the dialog (fade the backdrop out in ~150 ms) **before** the chips arrive: call `closeDailyBonus()` at once and run `flyFishkas` from the chest's last rect, which `flyFishkas` already takes as an element. Alternative: while the flight runs, lift the header balance above the backdrop (`[data-fishka-balance]` `relative z-[55]`). **Check:** in a mid-flight screenshot the pill and its "+N" badge are sharp and undimmed |

### 2 · Low — on phones the Tab order doesn't match the visual order

| | |
|---|---|
| Seen | At 390 the buttons show "Collect" above "Later" (`flex-col-reverse`), but Tab goes Later → Collect (measured) (WCAG 2.4.3) |
| File | `DailyBonusDialog.tsx`, the action row |
| Fix | Put Collect first in the DOM and use `sm:flex-row-reverse` (or `sm:order-*`) for the desktop order, instead of `flex-col-reverse` on phones |

### 3 · Low — "Collected" isn't reliably announced

| | |
|---|---|
| Seen | Collected days put `aria-label="Collected"` on a plain `<span>` holding ✓. The accessibility tree exposes it as an unnamed-role generic, which many screen readers skip, so a tile reads "Day 1, check mark, 1,000" or less. The list items have no name or state beyond `aria-current="date"` on today |
| File | `DailyBonusDialog.tsx` `DayTile` |
| Fix | Make the ✓ `aria-hidden` and add `<span className="sr-only">{t('daily.collected')}</span>`. For upcoming days add sr-only "{amount} Fishkas", and for day 5 sr-only "double bonus" (the visual "×2" is aria-visible text only) |

### 4 · Low — the "×2" badge and the smallest tile text

- **"×2":** white on `#e0483e` at `text-xs` is 4.1:1, below 4.5:1 for small text. Use a darker red
  (`#c0392b`, ≈ 5.4:1) or black text on gold.
- **Tile text:** at 390 the "DAY n" and amount lines are `text-[0.65rem]` (10.4 px). They are
  legible thanks to the bold weight and high contrast, but at the edge (`d44-rv-collected-390.png`,
  shown at 1×). `text-[0.7rem]` for the amount (11.2 px) still fits five tiles in 358 px in ru/hy.

### 5 · Low — the collected state's big number has no label

When the dialog is reopened after collecting (`d44-rv-collected-390.png`), it shows a closed chest
and a large "1,500" next to "Come back tomorrow for 2,000". Without the "+" it reads like a new
amount on offer. Show it as "+1,500" with a small "collected today" label (`daily.collected`), or
show tomorrow's amount dimmed beside the closed chest.

### 6 · Low — the sheet jumps when you collect

On phones, removing the Collect button shortens the bottom sheet by about 56 px while the chips start
flying, so the sheet visibly drops (`d44-r390en-open.png` → `d44-r390en-collecting.png`). Keep the
row's height: swap Collect for a disabled "Collected ✓" in the same slot, or animate the height.

## Clean-up

- Only `q44daily` was changed (streak and date set four times, two collects: +1,500, +2,000).
- The main headless tab had been signed out (session expired over the days). I signed it in as
  player2 and it is on `/dashboard` in English.
- No code was changed. I recreated the missing `qa2/api.mjs` client in the scratchpad.

## Fix status (developer, 2026-11-06)

| Finding | Status | Fix | Verified |
|---|---|---|---|
| Medium: the header the chips fly to is under the backdrop | Fixed | The chips take off from the chest and the popup closes 250 ms later, so the balance pill is in plain view when they land | Live 390 en: mid-flight screenshot `review/d44-fix-midflight-390.png` (`qa2/d44-fly-collecting.png`): no backdrop, pill sharp at 20,000 with "+1,500" |
| Low: Tab order on phones (Later before Collect) | Fixed | Collect first in the DOM; on wide screens Later is placed to its left with `sm:order-first` | Code |
| Low: "Collected" as aria-label on a span | Fixed | sr-only text: "Collected", "Today", "Upcoming", "Double bonus" (en/ru/hy) | DailyBonus.test |
| Low: ×2 badge 4.1:1; tile text 10.4 px | Fixed | Badge `#b3261e` with white (≈ 6.5:1); tile text 0.7rem | Code |
| Low: reopened after collecting, the amount reads like an offer | Fixed | "Collected: 1,500" | Code |
| Low: the sheet drops when Collect disappears | Fixed | The Collect slot stays, disabled, "✓ Collected" | Code |

