import { useEffect, useState } from 'react'
import { NavLink, Outlet } from 'react-router'
import { UserMenu } from '@/components/navigation/UserMenu'
import { Wordmark } from '@/components/navigation/Wordmark'
import { cn } from '@/lib/utils'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'

// Explorar, Olavo TV, Elenco e Minha lista entram aqui quando cada página existir.
const NAV_ITEMS = [{ to: '/', label: 'Início' }]

export function StudentLayout() {
  const { data: user } = useCurrentUser()
  const [scrolled, setScrolled] = useState(false)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <div className="dark min-h-dvh bg-background text-foreground">
      <header
        className={cn(
          'fixed inset-x-0 top-0 z-50 transition-colors duration-300',
          scrolled ? 'bg-background shadow-lg shadow-black/40' : 'bg-gradient-to-b from-black/70 to-transparent',
        )}
      >
        <div className="flex h-16 items-center gap-8 px-4 sm:px-8 lg:px-12">
          <Wordmark />
          <nav aria-label="Principal" className="flex items-center gap-5 text-sm">
            {NAV_ITEMS.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end
                className={({ isActive }) => cn('transition hover:opacity-100', isActive ? 'font-semibold' : 'opacity-70')}
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto">{user && <UserMenu user={user} />}</div>
        </div>
      </header>
      <Outlet />
    </div>
  )
}
