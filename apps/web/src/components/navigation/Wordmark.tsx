import { cn } from '@/lib/utils'

export function Wordmark({ className }: { className?: string }) {
  return (
    <span className={cn('font-heading text-xl font-bold tracking-tight', className)}>
      <span className="text-brand">OLAVO</span>
      <span className="ml-1 font-medium opacity-90">digital</span>
    </span>
  )
}
