import logo from '@/assets/logo-olavo-tv.png'
import { cn } from '@/lib/utils'

export function Wordmark({ className }: { className?: string }) {
  // self-start: dentro de colunas flex, o navegador esticaria a imagem até a largura do pai.
  return <img src={logo} alt="Olavo TV" className={cn('h-12 w-auto max-w-full shrink-0 self-start object-contain', className)} />
}
