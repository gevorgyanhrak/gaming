#!/usr/bin/env bash
# Keeps gaming-agent's project knowledge current.
#
#   knowledge-check.sh start  (UserPromptSubmit) records where the repository is when a turn begins.
#   knowledge-check.sh stop   (Stop) if the turn changed project files but none of the knowledge
#                             files, sends the agent back once to review and update them.
#
# The agent may decide nothing needs updating; Claude Code sets stop_hook_active on the
# second attempt, and the hook then lets it finish, so it can never loop.
set -euo pipefail

root="${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel)}"
state="$root/.claude/.turn-start"
input="$(cat)"

cd "$root"

# Uncommitted and untracked files with a content hash, so edits made before the turn don't count.
snapshot() {
  { git diff --name-only HEAD; git ls-files --others --exclude-standard; } | sort -u | grep -v '^\.claude/\.turn-start$' |
    while IFS= read -r f; do
      if [ -f "$f" ]; then printf '%s %s\n' "$(git hash-object "$f")" "$f"; else printf 'deleted %s\n' "$f"; fi
    done
}

case "${1:-}" in
  start)
    { git rev-parse HEAD; snapshot; } > "$state"
    exit 0
    ;;
  stop)
    [ "$(printf '%s' "$input" | jq -r '.stop_hook_active // false')" = "true" ] && exit 0
    [ -f "$state" ] || exit 0

    base="$(head -1 "$state")"
    before="$(tail -n +2 "$state" | sort)"
    # Changed this turn = committed since the turn began + working-tree files whose content changed.
    changed="$( {
      git diff --name-only "$base" HEAD 2>/dev/null || true
      comm -13 <(printf '%s\n' "$before") <(snapshot | sort) | cut -d' ' -f2-
    } | sed '/^$/d' | sort -u)"

    code="$(printf '%s\n' "$changed" | grep -E '^(apps/|docker/|compose\.yaml$|Makefile$|\.github/)' || true)"
    knowledge="$(printf '%s\n' "$changed" | grep -E '^(\.claude/(agents|skills)/|CLAUDE\.md$|docs/)' || true)"

    if [ -n "$code" ] && [ -z "$knowledge" ]; then
      areas="$(printf '%s\n' "$code" | cut -d/ -f1-2 | sort -u | head -12 | paste -sd ', ' -)"
      jq -n --arg areas "$areas" '{
        decision: "block",
        reason: ("Knowledge check: this turn changed project files (" + $areas + ") but no knowledge files. Review whether anything new should be recorded and update it now: .claude/skills/laravel/SKILL.md (backend conventions), .claude/agents/gaming-agent.md (project map, rules, frontend conventions), CLAUDE.md, docs/ARCHITECTURE.md (roadmap/status) or a new ADR. Record only durable conventions, decisions and structure, not task details. If nothing needs updating, say so in one line and finish.")
      }'
    fi
    exit 0
    ;;
esac
