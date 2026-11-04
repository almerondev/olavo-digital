import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'

/** Estado vazio real: a publicação de materiais entra na fase 5. */
export function TeacherHomePage() {
  const { data: user } = useCurrentUser()

  return (
    <div className="max-w-2xl">
      <h1 className="font-heading text-3xl font-bold tracking-tight">Olá, {user?.name}</h1>
      <p className="mt-2 text-muted-foreground">Você ainda não tem publicações. Elas aparecerão aqui.</p>
    </div>
  )
}
