import { Navigate, Outlet, useLocation } from 'react-router'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import { landingPath } from '@/modules/auth/landing'
import { TermsGate } from '@/modules/settings/components/TermsGate'

function FullScreenLoader() {
  return <div role="status" aria-label="Carregando" className="min-h-dvh bg-[oklch(0.145_0.006_270)]" />
}

export function RequireAuth() {
  const { data: user, isPending } = useCurrentUser()
  const location = useLocation()

  if (isPending) return <FullScreenLoader />
  if (!user) return <Navigate to="/entrar" replace state={{ from: location.pathname + location.search }} />
  if (user.terms_pending) return <TermsGate />
  return <Outlet />
}

export function GuestOnly() {
  const { data: user, isPending } = useCurrentUser()

  if (isPending) return <FullScreenLoader />
  if (user) return <Navigate to={landingPath(user)} replace />
  return <Outlet />
}

/** Só melhora a navegação: quem garante o acesso é a API, que recusa com 403. */
export function RequirePermission({ permission }: { permission: string }) {
  const { data: user } = useCurrentUser()

  if (!user?.permissions.includes(permission)) return <Navigate to={user ? landingPath(user) : '/entrar'} replace />
  return <Outlet />
}
