import { LogOut } from 'lucide-react'
import { Link } from 'react-router'
import { coverSrc } from '@/components/media/cover'
import { useLogout } from '@/modules/auth/hooks/useLogout'
import type { User } from '@/types/api'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase()
}

export function UserMenu({ user }: { user: User }) {
  const logout = useLogout()
  const avatar = coverSrc(user.avatar_urls)

  return (
    <div className="flex items-center gap-2 sm:gap-3">
      <Link
        to="/perfil"
        aria-label={`Perfil de ${user.name}`}
        className="grid size-8 place-items-center overflow-hidden rounded-lg bg-brand text-xs font-bold text-brand-foreground"
      >
        {avatar ? <img src={avatar} alt="" className="size-full object-cover" /> : initials(user.name)}
      </Link>
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
