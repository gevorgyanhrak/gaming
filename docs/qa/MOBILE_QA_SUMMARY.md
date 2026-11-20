# Mobile QA summary (2026-11-18 … 2026-11-20)

Full details, every bug and the iteration log: `MOBILE_QA_REPORT.md`. Test suite: `apps/web/e2e` (README there).

| | |
|---|---|
| Routes tested | 32 (9 signed out in en/ru/hy, 23 signed in) + 14 admin pages |
| Games tested | 11 against the AI, with real moves from the server's legal actions |
| Slots tested | 13 house slots, a real spin each |
| Viewports | 320, 344, 360, 375, 390, 414, 430, 768, 1024, 1280, 1440 (+ 812×375 phone sideways for slots) |
| Bugs found | 31 (P1 3 · P2 17 · P3 11) |
| Fixed | 27 — commits 9908aad, 3a2f51a |
| Won't fix | 2 — Nardi points 21 px at 320 (board width), Filament admin links (vendor) |
| Open | 1 — leaderboard player link 20 px tall (P3) |
| Iterations | 1 full + fixes; iteration 2 partial (stopped by the owner): no overflow anywhere it ran |
| Desktop regressions | none seen at 1024–1440 in the iteration-2 pages that ran; reviewers checked desktop for every fix |

Not verified: iteration 2 for the signed-in routes and a visual review of its screenshots; why 7 game tests failed in
the stopped run.
