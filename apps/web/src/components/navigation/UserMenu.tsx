import { LogOut } from 'lucide-react'
import { useLogout } from '@/modules/auth/hooks/useLogout'
import type { User } from '@/types/api'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase()
}

export function UserMenu({ user }: { user: User }) {
  const logout = useLogout()

  return (
    <div className="flex items-center gap-3">
      <span
        title={user.name}
        className="grid size-8 place-items-center rounded bg-brand text-xs font-bold text-brand-foreground"
      >
        {initials(user.name)}
      </span>
      <button
        type="button"
        onClick={() => logout.mutate()}
        disabled={logout.isPending}
        aria-label="Sair"
        className="rounded p-1 opacity-70 transition hover:opacity-100 disabled:opacity-40"
      >
        <LogOut className="size-4" />
      </button>
    </div>
  )
}
