import { X } from 'lucide-react'
import { useEffect, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

export function ViewerOverlay({ title, onClose, toolbar, children }: { title: string; onClose: () => void; toolbar?: ReactNode; children: ReactNode }) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose()
    const overflow = document.body.style.overflow

    window.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'

    return () => {
      window.removeEventListener('keydown', onKey)
      document.body.style.overflow = overflow
    }
  }, [onClose])

  return createPortal(
    <div role="dialog" aria-modal="true" aria-label={title} className="dark fixed inset-0 z-[60] flex flex-col bg-black/95 text-foreground">
      <header className="flex h-14 shrink-0 items-center gap-3 border-b border-white/10 px-3 sm:px-5">
        <h2 className="min-w-0 flex-1 truncate text-sm font-semibold">{title}</h2>
        {toolbar}
        <button type="button" aria-label="Fechar" autoFocus onClick={onClose} className="grid size-9 place-items-center rounded transition hover:bg-white/10">
          <X className="size-5" />
        </button>
      </header>
      <div className="min-h-0 flex-1 overflow-auto">{children}</div>
    </div>,
    document.body,
  )
}
