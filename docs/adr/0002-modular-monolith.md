# 0002. Laravel modular monolith

**Status:** Accepted (2026-10-27)

## Context
Five turn-based games, one team, and an MVP-first plan. Microservices would add network, deployment
and consistency costs without a scaling need that justifies them.

## Decision
A single Laravel application with explicit module boundaries: `Platform/` (cross-cutting services),
`Gameplay/` (shared, rule-agnostic game infrastructure) and `Games/<Game>/` (one module per game).
Stateless API processes scale horizontally; long-running processes (Reverb, Horizon, scheduler)
run as separate containers from the same image.

## Consequences
- One deployable backend, transactional consistency for critical game operations.
- Module boundaries must be respected by convention and review: shared infrastructure never depends on a specific game.
