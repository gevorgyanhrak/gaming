/**
 * The night sky behind the home pages' tops: a violet and a gold light drifting slowly, a faint green glow low down,
 * a fine grid fading out, and a fade into the page. Decoration only; still under reduced motion.
 */
export function NightSky() {
  return (
    <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
      <span className="absolute -top-40 -left-32 size-[38rem] rounded-full bg-[#7c5cff]/30 blur-[120px] motion-safe:animate-[aurora_18s_ease-in-out_infinite_alternate]" />
      <span className="absolute -top-24 right-[-10rem] size-[34rem] rounded-full bg-[#f5c04f]/22 blur-[120px] motion-safe:animate-[aurora_22s_ease-in-out_-6s_infinite_alternate-reverse]" />
      <span className="absolute bottom-[-18rem] left-1/3 size-[40rem] rounded-full bg-[#34d399]/10 blur-[140px]" />
      <span className="absolute inset-0 bg-[linear-gradient(rgb(255_255_255/0.035)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.035)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_50%_30%,black,transparent_75%)] bg-[size:56px_56px]" />
      <span className="absolute inset-x-0 bottom-0 h-40 bg-linear-to-b from-transparent to-bg" />
    </div>
  )
}
