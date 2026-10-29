# Product team: PM, UI/UX and Gaming Developer agents

Three Claude Code agents improve the platform together. They share state only through files in this
repo, so every hand-off is reviewable in git.

| Agent | Definition | Owns | Writes to |
|---|---|---|---|
| PM | `.claude/agents/pm-agent.md` | what and why: research, briefs, backlog, final validation | `docs/product/`, `docs/team/` |
| UI/UX | `.claude/agents/ui-ux-agent.md` | how it looks and works: audit, specs, design review | `docs/design/` |
| Gaming Developer | `.claude/agents/gaming-agent.md` (the main session in this repo) | building it: code, tests, implementation reports | code, `docs/development/` |

The main session runs as **gaming-agent** (`.claude/settings.json`) and also orchestrates: it starts
the PM and UI/UX agents as subagents, passes them task IDs, and reads their files back. Subagents
cannot talk to each other directly; the main session relays everything through the files below.

## Workspace

```
docs/team/README.md                  this file: workflow, protocol, task format, rules
docs/team/DECISIONS.md               product and design decisions (architecture stays in docs/adr/)
docs/product/PRODUCT_RESEARCH.md     inventory + competitor analysis + opportunities (OPP-NNN)
docs/product/PRODUCT_BACKLOG.md      tasks (TASK-NNN) with status: the single task tracker
docs/product/briefs/TASK-NNN.md      long product briefs (optional)
docs/design/DESIGN_SYSTEM.md         tokens, surfaces, components, patterns in use
docs/design/UI_UX_AUDIT.md           audit findings (UX-NNN)
docs/design/DESIGN_SPECIFICATIONS.md one section per task in design
docs/design/DESIGN_REVIEW.md         one section per reviewed task
docs/development/IMPLEMENTATION_REPORT.md  one section per implemented task
docs/development/TECHNICAL_DEBT.md   debt found along the way
```

Project context is **not** duplicated here: architecture is `docs/ARCHITECTURE.md` + `docs/adr/`,
code knowledge is `.claude/agents/gaming-agent.md`.

## Workflow

| Phase | Agent | Output | Task status after |
|---|---|---|---|
| 1 Discovery | PM | `PRODUCT_RESEARCH.md` | RESEARCH |
| 2 UX audit | UI/UX | `UI_UX_AUDIT.md` | — |
| 3 Planning | PM | `PRODUCT_BACKLOG.md` (merged, deduplicated, batched) | PLANNED |
| 4 Spec | UI/UX | `DESIGN_SPECIFICATIONS.md#TASK-NNN` | DESIGN_IN_PROGRESS → READY_FOR_DEVELOPMENT |
| 5 Build | Developer | code + tests + `IMPLEMENTATION_REPORT.md#TASK-NNN` | IN_PROGRESS → READY_FOR_REVIEW |
| 6 Design review | UI/UX | `DESIGN_REVIEW.md#TASK-NNN` | APPROVED or NEEDS_REVISION (back to 5) |
| 7 Validation | PM | status + one-line reason in the backlog | APPROVED / NEEDS_REVISION / BLOCKED |

Statuses: `RESEARCH`, `PLANNED`, `DESIGN_IN_PROGRESS`, `READY_FOR_DEVELOPMENT`, `IN_PROGRESS`,
`READY_FOR_REVIEW`, `NEEDS_REVISION`, `APPROVED`, `BLOCKED`. Only the backlog holds a task's status.

## Hand-off protocol

Every hand-off is a short block appended to the receiving file's task section:

```
> HANDOFF  TASK-012  PM → UI/UX  2026-10-29
> Need: spec for the rematch button on the game-over panel
> Read: PRODUCT_BACKLOG.md#TASK-012, PRODUCT_RESEARCH.md#OPP-007
> Done when: spec section exists with states, copy keys and acceptance criteria
```

Rules: one task per hand-off; link, don't copy; the receiver may push back with `> QUESTION` and the
sender answers in place. If two agents disagree, both positions go into `DECISIONS.md` under
"Open — needs owner" and the task is `BLOCKED` until the owner decides.

## Task format (in `PRODUCT_BACKLOG.md`)

```
### TASK-NNN · Title                                   P1 · M · READY_FOR_DEVELOPMENT · Batch B2
Problem: one or two sentences, from the player's side.
User story: As a …, I want …, so that …
Why: business / retention reason. Evidence: OPP-NNN, UX-NNN, source URLs.
Scope: what changes. Out of scope: what doesn't.
Spec: DESIGN_SPECIFICATIONS.md#task-nnn (or "none needed")
Acceptance criteria:
  1. …   (observable, testable)
Tests: unit / feature / browser checks required.
Depends on: TASK-…   Approval: none | owner (why)
```

Complexity: S (≤ half a day), M (1–2 days), L (more; split it).

## Autonomy and approvals

May run without asking (autonomous mode):
- research, audits, specs, reviews, backlog edits;
- routine, reversible frontend work on a task that is `READY_FOR_DEVELOPMENT`, has acceptance criteria
  and needs no approval: committed to `main` after lint, typecheck, format, tests and build pass.

Needs the owner's explicit approval first:
- new backend domains or tables (ratings, friends, chat, spectating, achievements), breaking API
  changes, destructive database changes, changes to accepted ADRs;
- anything touching chips, payments or monetization (the platform is recreational only, forever);
- removing an existing feature; production infrastructure or deploys; new third-party services.

Never: invent research, test results or competitor features; register on or bypass logins of other
sites; expose secrets; present a UI-only feature as working.

## Running the team

- Whole cycle: ask the main session "run the product team" (or a phase: "PM: research poker").
- One task: "build TASK-012". The developer reads backlog + spec, implements, writes the report, then
  starts the UI/UX agent for review and the PM agent for validation.
- Agents in `.claude/agents/` are loaded at session start; restart Claude Code after editing them.
