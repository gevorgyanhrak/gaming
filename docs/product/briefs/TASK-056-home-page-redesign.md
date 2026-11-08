# Owner brief — TASK-056 · Home page redesign and game categories (2026-11-08)

Sent by the owner on 2026-11-08, while TASK-047 was in progress. Kept verbatim so any session can resume.

---

I think need redesig home page, games calusel, set categoryes by games becouse we need add games, pls ask ui ux agents change backgound colors, images in home page

---

Revision from the owner (2026-11-08, after seeing the first version), verbatim:

home page ui is better, but i think white is more, pls again redesign it, and you can set one big background image about casini, poker...

---

Notes for implementers (not the owner's words):

- Scope as read: a new home page; the games carousel reworked; games grouped into categories so that more games
  can be added; the UI/UX agent (`.claude/agents/ui-ux-agent.md`) changes the home page's background colours and
  images.
- Overlaps TASK-048 (complete platform redesign), which also covers the home page and game discovery. Decide
  whether this is delivered as part of 048 or ahead of it.
- Categories have to come from the backend catalog (admin-managed), not be hardcoded in the frontend.
