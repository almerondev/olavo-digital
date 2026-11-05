import logo from '@/assets/logo-olavo-tv.png'
import { cn } from '@/lib/utils'

export function Wordmark({ className }: { className?: string }) {
  return <img src={logo} alt="Olavo TV" className={cn('h-12 w-auto', className)} />
}
