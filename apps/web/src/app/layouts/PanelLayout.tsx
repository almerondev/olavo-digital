import { Link, NavLink, Outlet } from 'react-router'
import type { LucideIcon } from 'lucide-react'
import { UserMenu } from '@/components/navigation/UserMenu'
import { Wordmark } from '@/components/navigation/Wordmark'
import { cn } from '@/lib/utils'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'

export interface PanelNavItem {
  to: string
  label: string
  icon: LucideIcon
  permission?: string
}

export function PanelLayout({ title, items }: { title: string; items: PanelNavItem[] }) {
  const { data: user } = useCurrentUser()
  const visible = items.filter((item) => !item.permission || user?.permissions.includes(item.permission))

  return (
    <div className="flex min-h-dvh flex-col bg-muted/40 text-foreground md:flex-row">
      <aside className="dark flex shrink-0 flex-col bg-background text-foreground md:sticky md:top-0 md:h-dvh md:w-60">
        <div className="flex items-center justify-between gap-3 p-4 md:block">
          <Link to="/" aria-label="Ir para a área do aluno">
            <Wordmark className="text-lg" />
          </Link>
          <p className="text-xs text-muted-foreground md:mt-1">{title}</p>
        </div>
        <nav aria-label={title} className="flex gap-1 overflow-x-auto px-2 pb-2 text-sm md:flex-col md:pb-0">
          {visible.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                cn(
                  'flex shrink-0 items-center gap-2.5 rounded-md px-3 py-2 transition',
                  isActive ? 'bg-brand font-semibold text-brand-foreground' : 'text-foreground/75 hover:bg-white/10',
                )
              }
            >
              <item.icon className="size-4" />
              {item.label}
            </NavLink>
          ))}
        </nav>
        <div className="mt-auto hidden items-center justify-between gap-2 p-4 md:flex">
          <span className="truncate text-xs text-muted-foreground">{user?.name}</span>
          {user && <UserMenu user={user} />}
        </div>
      </aside>
      <main className="min-w-0 flex-1 p-4 sm:p-8">
        <Outlet />
      </main>
    </div>
  )
}
