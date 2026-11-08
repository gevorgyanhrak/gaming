# Design review — TASK-047 · Level & XP progression, frontend (2026-11-08)

UI/UX agent. Build: `main` at `d0ab709`. Brief: `docs/product/briefs/TASK-047-levels-xp.md`, sections 1, 3,
4, 5 and 8. ADR: `docs/adr/0018-levels-and-xp.md`.

## Verdict

**NEEDS REVISION.** Three required fixes, each small:
- on the phone profile the level badge sits away from the avatar;
- the two avatar frames fill the avatar instead of ringing it, so the initial can't be read;
- the popup can't scroll, so with a long reward list "Collect" is off-screen in phone landscape and the
  title is cut at 360×640.

The rest is Medium or Low. The system is close: one badge component, five clear tiers, a progress bar
that says everything the brief asks, a calm popup, and poker's badge position is right.

## What I checked

Live in headless Chrome over CDP, with throwaway verified accounts (`rv47_*`, levels 1 to 100). Screenshots
are in `/private/tmp/claude-501/-Users-hrak/dcf481df-22d2-4f78-a522-b2a3b7f2218b/scratchpad/rv47/shots/`.
The scripts are `rv47*.mjs` in the scratchpad's `tools` folder.

| Screen | Where |
|---|---|
| Popup, normal level (1→2) | 1440, frames at 0.2 to 4.2 s |
| Popup, milestone (9→10) | 1440 frames, 820, 390 |
| Popup, level 30 alone (29→30) | 1440 |
| Popup, jump 1→30 (7 reward chips) | 390, 360×640, 844×390, 820, 1440, 1280×640, in ru and hy |
| Popup, reduced motion | 390 hy: 0 confetti pieces, 0 running animations |
| After "Later" | 1440 |
| Dashboard, profile | levels 9, 15, 25, 35, 55, 100 at 1440; levels 9, 35, 100 at 820 and 390 |
| User menu | level 35 en, ru, hy at 1440; 390 hy; level 99 en, hy |
| Poker, 6 seats | 1440, 820, 390, with zoomed seats |
| Durak, 4 seats | 1440, 820, 390, with zoomed seats |
| Chess (player list) | 1440, 820, 390; nardi and checkers from the earlier run's 1440 and 390 shots |
| Chat lines, closed and open | poker, durak, chess |
| Emoji picker | level 9 (all locked) at 1440 and 390; level 15 (part locked) at 1440 |
| Waiting table with two players | durak at 1440, 820, 390 |
| Badge matrix | sm, md, lg at levels 1 to 100 on 5 surfaces (`s-matrix.png`; real classes on injected tiles) |

### Not seen

- **Another player's chat line.** The bots sent nothing while I watched, so I only saw my own lines. Their
  lines use the same `Line` markup.
- **Poker showdown or winner state**, where cards and "+N" sit over the avatar; so the badge's z-order
  against shown cards is unconfirmed.
- **The progress bar animating.** I saw it before and after values, not a live XP change. The 700 ms
  transition, and its reduced-motion off switch, are read from code.
- **Keyboard use.** Focus rings and Tab through the popup were not driven; tab order below is from code.
- **Frames on an uploaded photo.** All test accounts have no photo.
- **ru and hy on game seats, chat and picker.** ru and hy were checked on the popup, dashboard and menu only.
- **Leaderboards**: none exist yet (PROGRESS step 1).
- Only Chrome. No real phone or Safari.

## What works

- **One badge, five tiers.** Bronze, silver, gold, ruby, amethyst are distinct at every size and on every
  surface in the matrix (`s-matrix.png`). Numbers stay on one line up to 100. Silver and gold are 8.5:1 or
  better across the digits. The light border keeps the darker tiers visible on felt.
- **Poker seats.** Top-left clears the dealer button and the nameplate at 1440, 820 and 390
  (`g-poker-1440-seat2.png`: 52 and D on one seat, both clean). A folded seat dims its badge with the seat.
- **Progress bar.** Level, bar, "20 / 180 XP", percent and "160 XP until level 7" match the brief's example.
  The next reward is a good addition. "Top level" with a full bar is right at level 100. No overflow at any
  width, including hy.
- **Popup.** Clean once the confetti has passed. Normal (green) and milestone (plum, red pill, more
  confetti) are clearly different. Long ru and hy reward strings wrap into neat chips at 390. Waiting for a
  game to end and going before the daily bonus are from the code and the earlier run's shots.
- **Reduced motion.** No confetti, no pop, no rise; the popup is complete without them.
- **Chat lines.** The small badge reads well before the name, and the line doesn't grow.
- **Locked emojis.** Greyed emoji with the level on its corner is clear at 1440 and 390, and the tiles stay
  44 px (`g-picker-9-1440-zoom.png`).

## Issues

### 1 · High — phone profile: the badge floats away from the avatar

| | |
|---|---|
| Seen | At 390 the badge sits at the far right of the card, about 170 px from the avatar. Measured at level 35: wrapper 306 px wide, avatar 100 px at x 42, badge at x 319 (`s-9-profile-390.png`, `s-35-profile-390.png`). The profile is the first place the brief names |
| Cause | In the profile's `flex-col` row the `inline-flex` wrapper stretches to full width, and the badge is pinned to its right edge |
| File | `shared/ui/Avatar.tsx` |
| Fix | Add `w-fit` to the wrapper: `relative inline-flex w-fit shrink-0`. This protects every column parent, not just this one. **Check:** at 390 the badge overlaps the avatar's bottom-right corner, as at 820 |

### 2 · High — the frames fill the avatar instead of ringing it

| | |
|---|---|
| Seen | At levels 20+ a player with no photo gets a solid olive-gold disc, and at 30+ a full rainbow disc, with the initial on top (`s-25-profile-1440.png`, `s-100-profile-1440.png`, header on every page). The initial is about 1.7 to 3.6:1 on those blended colours. These frames are the rewards the brief promises at levels 20 and 30 |
| Cause | The initial tile is `bg-accent/30`, translucent, so the frame's gradient shows through it |
| File | `shared/ui/Avatar.tsx` |
| Fix | Make the tile opaque with the same look: `bg-[#23301b]` (accent at 30% over surface) instead of `bg-accent/30`. **Check:** at level 25 and level 30 a 2 px ring around a dark tile, with the initial at the usual contrast |

### 3 · High — the popup can't scroll, so its button can be unreachable

| | |
|---|---|
| Seen | The panel has no maximum height and the page behind is locked. Jump to 30 (7 chips): at 844×390 the panel runs from −91 to 481, and Collect at 408–456 is below the screen with no way to reach it (`p4-jump-hy-land.png`). At 360×640 the top is cut by 112 px in ru, losing the title and milestone pill (`p4-jump-ru-360.png`). Offline catch-up is what produces long lists (brief §4), and landscape is common for card games |
| File | `features/levels/components/LevelUpDialog.tsx` |
| Fix | Keep the panel `overflow-hidden` with `max-h-[calc(100dvh-1.5rem)]`, and put the content in an inner `<div className="max-h-[inherit] overflow-y-auto overscroll-contain">`. Make the button row `sticky bottom-0` with the footer's solid background (`bg-[#24180c]`), so Collect always shows. Confetti must move to its own layer first (issue 4), or its pieces add to the scroll height. **Check:** at 844×390 and 360×640 with the 1→30 jump, Collect is visible without scrolling and the title is reachable by scrolling |

### 4 · Medium — confetti covers the text and Collect for about 2 s

| | |
|---|---|
| Seen | Pieces fall in front of everything. In the first 2 s they cross "You reached level 2!", the badges, "+4,250", the reward chip and, in the milestone, the Collect label (`p1-normal-1440-t700.png`, `p2-mile10-1440-t1400.png`, `02-popup-phone.png`). Clear by about 3.2 s |
| File | `LevelUpDialog.tsx` (`confetti()`, and the panel) |
| Fix | Draw confetti behind the content, not over it. Add `<div ref={burst} aria-hidden className="pointer-events-none absolute inset-0 z-[1] overflow-hidden" />` as the panel's first child and pass it to `confetti()`. Wrap the content of each of the two sections in `relative z-[2]`, leaving the gradient backgrounds on the sections, so the pieces fall above the background and behind text and buttons. **Check:** a frame at 700 ms and 1.4 s, with every word and the Collect label unbroken |

### 5 · Medium — level 30 looks the same as level 10

| | |
|---|---|
| Seen | The brief asks for a "major celebration" at 30 and "distinct milestone celebrations". 29→30 and 9→10 have the same plum panel, red pill and 90 pieces (`p3-l30-1440-settled.png`, `p2-mile10-1440-t3200.png`); only the rewards differ |
| File | `LevelUpDialog.tsx` |
| Fix | Add `const major = pending.some((up) => up.is_milestone && up.level >= 30)`. When major: header `from-[#5a1a1f] via-[#6a3a14] to-[#3b2a14]` (ruby to gold), a gold pill (`bg-gold text-black`) with a new key `level.majorMilestone` (en "Major milestone", ru «Главный рубеж», hy «Գլխավոր հանգրվան»), and a second confetti burst 700 ms later, 140 pieces in total. Reduced motion keeps the colours and pill, and skips the bursts. Levels above 30 that an admin marks as milestones get this too |

### 6 · Medium — the new level is the smallest thing in its own popup

| | |
|---|---|
| Seen | The from→to row is 28 px badges (35 px scaled), while the Fishka line is 36 px text with a 40 px chip, and the eye goes to the amount (`p1-normal-1440-t4200.png`). The old badge at `opacity-70` drops to about 4:1 |
| File | `shared/ui/LevelBadge.tsx`, `LevelUpDialog.tsx` |
| Fix | Add a size `xl: 'h-14 min-w-14 px-2 text-2xl rounded-xl'` and use it for the new level (drop `scale-125`, keep `level-pop`). Keep `lg` for the old level without the opacity. Reduce the amount to `text-3xl` with `size-8` chip. For jumps, use `level.reached` ("You reached level 30!") instead of "From level 1 to 30!", since the row already shows it |

### 7 · Medium — durak: the badge touches the name, the BOT pill and the cards

| | |
|---|---|
| Seen | Desktop: the badge sits 4 px over the start of the name and on top of the first card (`g-durak-1440.png`; 52 and 34 touch "David" and "Anna"). Phone and tablet: it sits on the BOT pill (`g-durak-390-seat1.png`). It's also `sm` here, while poker uses `md` on the same size of avatar |
| File | `games/durak/DurakSeat.tsx` (`Avatar`) |
| Fix | Use poker's rule, top-left: `className="absolute -top-1 -left-1.5"`, and `size={size === 'md' ? 'md' : 'sm'}`. The bubble is centred on phones and starts at `left-11` on desktop, so that corner is free. **Check:** at 1440, 820 and 390 the badge touches neither the name, the pill, the cards nor the bubble, including with a three-digit level |

### 8 · Medium — bronze, amethyst and ruby numbers are under 4.5:1

| | |
|---|---|
| Seen | White on the gradient, at the upper part of the digits (30% down): bronze 3.8:1, amethyst 3.7:1, ruby 4.4:1, at 9.6 to 11.2 px. Bronze is what every new player sees |
| File | `shared/ui/LevelBadge.tsx` |
| Fix | Darken the top stops: bronze `from-[#a56c38] to-[#6e421c]` (5.3:1), ruby `from-[#cc4957]` (5.6:1), amethyst `from-[#9266d6] to-[#4f2489]` (5.5:1). Raise `sm` from `text-[0.6rem]` to `text-[0.65rem]`. Keep the borders and glows |

### 9 · Medium — frames don't appear at poker and durak tables

| | |
|---|---|
| Seen | Poker and durak draw their own seat avatars, with a level but no frame, so a level-30 player looks unframed exactly where others see them most (`g-poker-1440.png`, `g-durak-1440.png`). `LevelItems` says frames show "everywhere", and §8 asks for consistency across games |
| File | `pages/RoomPage.tsx`, `games/registry.ts` (`BoardProps`), `games/poker/PokerBoard.tsx`, `games/durak/DurakSeat.tsx` |
| Fix | Pass `frames` by seat next to `levels` (`data.participants` already carries `frame`). When a seat isn't acting or winning, use the frame as the seat's ring: `gold` → `var(--color-gold)`, `legend` → the same conic gradient as `Avatar`. The turn and win rings keep priority |

### 10 · Medium — after "Later", nothing says a reward is waiting

| | |
|---|---|
| Seen | "Later" closes the popup, and the dashboard shows only the next level's reward (`p1-after-later.png`). It only comes back on a full reload or the next level-up, so a player can believe it's gone |
| File | `features/levels/components/LevelCard.tsx` |
| Fix | When `level.data.pending.length > 0`, add a row at the top of the card: text `level.pendingReward` (en "You have a reward waiting", ru «Вас ждёт награда», hy «Ձեզ պարգև է սպասում») and a small gold `Button` with `level.collect` that calls `offerLevelUp(user.id, true)`. The function already exists |

### 11 · Medium — waiting table: seats hidden under the centre panel

| | |
|---|---|
| Seen | 390: the left seat's "55" is half under the centre panel, and its name is cut by the screen edge (`w2-durak-390.png`). 820: the panel covers the face of the host's own seat (`w2-durak-820.png`). 1440 is fine |
| File | `features/play/components/WaitingTable.tsx` |
| Fix | Put the badge on the outer side for left-hand seats: `Math.cos(angle) < -0.1 ? '-left-1 bottom-0' : '-right-1 bottom-0'`. Change the table to `aspect-[3/4] md:aspect-[4/3] lg:aspect-[2/1]`, so at 820 the panel fits inside the oval. **Check:** at 390 and 820 with 4 seats, every face, badge and name is clear of the panel and the screen edge |

### 12 · Low — small things

1. **Menu and level card wrap.** In the 240 px menu, hy wraps at level 35, and en at level 99, to "LEVEL / 99" and "700,000 / 710,773 / XP" (`g-menu-35-hy.png`, `x-menu-99-en.png`). In `LevelProgress`, add `whitespace-nowrap` to the title and the XP, `flex-wrap` to the row, and in `compact` make the word sr-only (the badge already gives the number).
2. **"Progress to level 101"** is announced at the top level. Omit `aria-label`'s next level when `next === null` and use `level.top`.
3. **Tab order.** From `sm` up, Later is on the left but Collect comes first in the DOM (`order-first`). Put Later first in the DOM, with `flex-col-reverse sm:flex-row` so Collect stays on top on phones.
4. **Locked emojis on touch.** The explanation is only a `title`. Add a line under "Exclusive": `reactions.exclusiveHint` (en "Open as you level up", ru «Открываются с уровнем», hy «Բացվում են մակարդակի հետ»).
5. **Emoji chips break mid-pack** at 360 (`p4-jump-ru-360.png`). Add `inline-flex gap-0.5 whitespace-nowrap` to the emoji span.
6. **Level 100 on a 32 px avatar** covers the bottom of the initial (`s-100-menu-1440.png`). Use `-right-2` when `level >= 100`.

## Follow-ups (not blockers)

1. **TASK-058.** At 820 poker's desktop layout is crowded (David's and Mariam's seats touch; a bet chip covers Alex's stack; `g-poker-820.png`). That was there before this task. The 900 px switch should cover it; check the badges again then.
2. Add the five tiers and the top-left rule for table seats to `DESIGN_SYSTEM.md`.

## Clean-up

0 open rooms for the `rv47_*` accounts: one waiting room left by a crashed script run was set to aborted through tinker. The accounts stay in the dev database (`rv47_*@example.test`). No application code was changed.

## Fix status (2026-11-08)

All High, Medium and Low items are done, as specified above: issues 1–3 (w-fit, opaque tile, scrolling popup with a
sticky button row), 4–11, and the six Low items. Checked live in headless Chrome: Collect on screen at 844×390, profile
badge on the avatar at 390. Not re-reviewed by the design agent. Issue 11's waiting-table aspect change and frames at
seats are covered by types and tests, not by a new screenshot pass.
