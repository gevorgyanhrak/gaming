import { useTranslation } from 'react-i18next'

export type DealerMood = 'neutral' | 'happy' | 'wow' | 'sorry' | 'thanks' | 'delighted'

/**
 * The dealer: a drawn croupier (vest, bow tie, sleeve garters) behind the shoe, whose face follows the table —
 * smiling at your win, wide-eyed at a blackjack, sorry when you lose, bowing for a tip — with a short line in a
 * speech bubble. A drawing, not a person; the line is also announced to screen readers.
 */
export function Dealer({
  mood,
  line,
  lineKey,
  size = 'md',
}: {
  mood: DealerMood
  /** What the dealer says, if anything. */
  line: string | null
  /** Changes for every new line, so it pops in again. */
  lineKey: number
  size?: 'sm' | 'md'
}) {
  const { t } = useTranslation()
  const box = size === 'sm' ? 'size-14' : 'size-20'
  const bowing = mood === 'thanks' || mood === 'delighted'

  return (
    <div className="relative flex flex-col items-center" data-blackjack-dealer="">
      <span
        className={`${box} block overflow-hidden rounded-full border-2 border-gold/70 bg-[#173d2c] shadow-[0_8px_22px_rgb(0_0_0/0.55)] transition-transform duration-500 ${
          bowing ? 'motion-safe:translate-y-1 motion-safe:rotate-[-4deg]' : ''
        } ${mood === 'delighted' ? 'shadow-[0_0_24px_rgb(240_196_98/0.75)]' : ''}`}
        role="img"
        aria-label={t('blackjack.dealerName')}
      >
        <DealerFace mood={mood} />
      </span>
      {line ? (
        <span
          key={lineKey}
          role="status"
          className="absolute top-1/2 left-full z-30 ml-2 w-max max-w-40 -translate-y-1/2 rounded-2xl rounded-bl-sm border border-gold/40 bg-[#fff8e7] px-2.5 py-1.5 text-[0.7rem] leading-tight font-bold text-[#2a1b0c] shadow-lg motion-safe:animate-turn-pop min-[901px]:max-w-52 min-[901px]:text-xs"
        >
          {line}
        </span>
      ) : null}
    </div>
  )
}

function DealerFace({ mood }: { mood: DealerMood }) {
  const skin = '#e9b98f'
  const eyes =
    mood === 'wow' || mood === 'delighted' ? (
      <>
        <circle cx="26" cy="29" r="2.4" fill="#1c1c1c" />
        <circle cx="38" cy="29" r="2.4" fill="#1c1c1c" />
      </>
    ) : mood === 'happy' || mood === 'thanks' ? (
      <path
        d="M23 29q3-3 6 0M35 29q3-3 6 0"
        stroke="#1c1c1c"
        strokeWidth="1.8"
        fill="none"
        strokeLinecap="round"
      />
    ) : (
      <>
        <circle cx="26" cy="29" r="1.7" fill="#1c1c1c" />
        <circle cx="38" cy="29" r="1.7" fill="#1c1c1c" />
      </>
    )
  const mouth =
    mood === 'wow' ? (
      <ellipse cx="32" cy="38" rx="2.6" ry="3" fill="#5a1f17" />
    ) : mood === 'sorry' ? (
      <path
        d="M28 39q4-3 8 0"
        stroke="#5a1f17"
        strokeWidth="1.6"
        fill="none"
        strokeLinecap="round"
      />
    ) : mood === 'neutral' ? (
      <path
        d="M28.5 37.5q3.5 1.5 7 0"
        stroke="#5a1f17"
        strokeWidth="1.6"
        fill="none"
        strokeLinecap="round"
      />
    ) : (
      <path
        d="M27 36q5 5 10 0Z"
        fill="#5a1f17"
        stroke="#5a1f17"
        strokeWidth="1"
        strokeLinejoin="round"
      />
    )
  return (
    <svg viewBox="0 0 64 64" aria-hidden="true" className="size-full">
      <rect width="64" height="64" fill="#1b4a35" />
      {/* Shirt, black vest and the bow tie */}
      <path d="M8 64c2-11 12-17 24-17s22 6 24 17Z" fill="#f4f1ea" />
      <path d="M8 64c2-11 9-16 17-17l7 17ZM56 64c-2-11-9-16-17-17l-7 17Z" fill="#1b1b1f" />
      <path d="M26 49l6 3 6-3v6l-6-3-6 3Z" fill="#b3172c" />
      <circle cx="32" cy="52" r="1.2" fill="#7a0f1d" />
      <rect x="27" y="39" width="10" height="9" rx="3" fill={skin} />
      <ellipse cx="32" cy="30" rx="13" ry="15" fill={skin} />
      {/* Slicked dark hair with a parting */}
      <path d="M19 28c-1-11 5-17 13-17s14 6 13 17c-2-6-6-9-9-10-4 1-11 2-17 10Z" fill="#2b1a12" />
      <path d="M36 15c-2 2-3 4-3 6" stroke="#4a2e1c" strokeWidth="1" fill="none" />
      <path d="M22.5 25h6.5M35 25h6.5" stroke="#2b1a12" strokeWidth="1.5" strokeLinecap="round" />
      {eyes}
      {mood === 'delighted' || mood === 'happy' ? (
        <>
          <circle cx="22.5" cy="34" r="2.2" fill="#e8846f" opacity="0.45" />
          <circle cx="41.5" cy="34" r="2.2" fill="#e8846f" opacity="0.45" />
        </>
      ) : null}
      {mouth}
      {/* A thin moustache */}
      <path
        d="M27.5 34.5q4.5-2 9 0"
        stroke="#2b1a12"
        strokeWidth="1.4"
        fill="none"
        strokeLinecap="round"
      />
    </svg>
  )
}
