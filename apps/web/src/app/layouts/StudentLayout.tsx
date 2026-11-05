import { Compass, House, Library, Radio, Search, UsersRound } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router'
import { SiteFooter } from '@/components/navigation/SiteFooter'
import { UserMenu } from '@/components/navigation/UserMenu'
import { Wordmark } from '@/components/navigation/Wordmark'
import { cn } from '@/lib/utils'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import { NotificationBell } from '@/modules/communication/components/NotificationBell'
import { PushRegistration } from '@/modules/native/PushRegistration'

const NAV_ITEMS = [
  { to: '/', label: 'Início' },
  { to: '/materiais', label: 'Materiais' },
  { to: '/explorar', label: 'Explorar' },
  { to: '/ao-vivo', label: 'Ao vivo agora' },
  { to: '/elenco', label: 'Elenco' },
  { to: '/minha-lista', label: 'Minha lista', studentOnly: true },
]

const TAB_ITEMS = [
  { to: '/', label: 'Início', icon: House },
  { to: '/materiais', label: 'Materiais', icon: Library },
  { to: '/explorar', label: 'Explorar', icon: Compass },
  { to: '/ao-vivo', label: 'Ao vivo', icon: Radio },
  { to: '/elenco', label: 'Elenco', icon: UsersRound },
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
        <div className="flex h-16 items-center gap-3 px-4 sm:gap-8 sm:px-8 lg:px-12" style={{ marginTop: 'env(safe-area-inset-top)' }}>
          <Wordmark className="h-10 shrink-0 sm:h-12" />
          <nav aria-label="Principal" className="hidden min-w-0 items-center gap-5 overflow-x-auto text-sm whitespace-nowrap md:flex">
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
          <div className="ml-auto flex shrink-0 items-center gap-2 sm:gap-3">
            <Link to="/pesquisa" aria-label="Pesquisar" className="rounded p-1 opacity-70 transition hover:opacity-100"><Search className="size-4" /></Link>
            {user?.is_student && <NotificationBell />}
            {user && <UserMenu user={user} compact />}
          </div>
        </div>
      </header>
      <div className="pb-[calc(5rem+env(safe-area-inset-bottom))] md:pb-0">
        <Outlet />
        <SiteFooter />
      </div>
      <nav
        aria-label="Navegação"
        style={{ bottom: 'calc(0.875rem + env(safe-area-inset-bottom))' }}
        className="fixed left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full border border-white/15 bg-black/40 p-1.5 shadow-lg shadow-black/40 backdrop-blur-xl md:hidden"
      >
        {TAB_ITEMS.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === '/'}
            aria-label={item.label}
            title={item.label}
            className={({ isActive }) => cn('grid size-11 place-items-center rounded-full transition', isActive ? 'bg-white/15 text-brand' : 'text-white/75')}
          >
            <item.icon aria-hidden className="size-5" />
          </NavLink>
        ))}
      </nav>
      {user?.is_student && <PushRegistration />}
    </div>
  )
}
