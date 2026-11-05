import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router'
import { UserMenu } from '@/components/navigation/UserMenu'
import { Wordmark } from '@/components/navigation/Wordmark'
import { cn } from '@/lib/utils'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'

const NAV_ITEMS = [
  { to: '/', label: 'Início' },
  { to: '/materiais', label: 'Materiais' },
  { to: '/explorar', label: 'Explorar' },
  { to: '/olavo-tv', label: 'Olavo TV' },
  { to: '/elenco', label: 'Elenco' },
  { to: '/minha-lista', label: 'Minha lista', studentOnly: true },
]

export function StudentLayout() {
  const { data: user } = useCurrentUser()
  const [scrolled, setScrolled] = useState(false)
  const { pathname } = useLocation()

  useEffect(() => {
    window.scrollTo(0, 0)
  }, [pathname])

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
        <div className="flex h-16 items-center gap-3 px-4 sm:gap-8 sm:px-8 lg:px-12">
          <Wordmark className="h-10 shrink-0 sm:h-12" />
          <nav aria-label="Principal" className="flex min-w-0 items-center gap-3 overflow-x-auto whitespace-nowrap text-xs sm:gap-5 sm:text-sm">
            {NAV_ITEMS.filter((item) => !item.studentOnly || user?.is_student).map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                className={({ isActive }) => cn('transition hover:opacity-100', isActive ? 'font-semibold' : 'opacity-70')}
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
          <div className="ml-auto shrink-0">{user && <UserMenu user={user} />}</div>
        </div>
      </header>
      <Outlet />
    </div>
  )
}
