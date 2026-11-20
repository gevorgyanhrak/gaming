import { AiFace } from './AiFace'
import { AiTag } from './AiTag'

/**
 * The mark of an AI player on a board's own seat (HayBet item 14): a small drawn AI face in the AI ring — the same
 * look as the AI Avatar — so against AI every opponent reads as AI at a glance. The seat's name already says "· AI"
 * (ADR 0022), so the tag shows only where the name doesn't (`tag`).
 */
export function AiSeatMark({ face, tag = false }: { face: string; tag?: boolean }) {
  return (
    <span className="inline-flex shrink-0 items-center gap-1 align-middle">
      <span className="ai-ring inline-flex size-5 overflow-hidden rounded-full">
        <AiFace name={face} className="size-full" />
      </span>
      {tag ? <AiTag /> : null}
    </span>
  )
}
