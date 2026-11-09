export function Skeleton({ className = '' }: { className?: string }) {
  return (
    <div aria-hidden="true" className={`animate-pulse rounded-lg bg-emerald-600/45 ${className}`} />
  )
}
