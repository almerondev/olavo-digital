import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { CURRENT_USER_KEY } from '@/modules/auth/hooks/useCurrentUser'
import { useLogout } from '@/modules/auth/hooks/useLogout'
import { api } from '@/services/http'
import { usePublicConfig } from '../config'

export function TermsGate() {
  const queryClient = useQueryClient()
  const config = usePublicConfig()
  const logout = useLogout()
  const accept = useMutation({
    mutationFn: () => api<void>('/me/terms/accept', { method: 'POST' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CURRENT_USER_KEY }),
  })
  const terms = config.data?.['legal.terms'] as string | null | undefined
  const privacy = config.data?.['legal.privacy'] as string | null | undefined

  return (
    <main className="dark grid min-h-dvh place-items-center bg-background p-4 text-foreground">
      <div className="grid w-full max-w-2xl gap-4 rounded-xl bg-card p-6">
        <h1 className="font-heading text-2xl font-bold">Antes de continuar</h1>
        <p className="text-sm text-muted-foreground">Leia e aceite os termos para usar a plataforma.</p>
        <div className="grid max-h-[55dvh] gap-4 overflow-auto rounded-lg border p-4 text-sm leading-relaxed">
          {terms && <section><h2 className="mb-1 font-semibold">Termos de uso</h2><p className="whitespace-pre-line">{terms}</p></section>}
          {privacy && <section><h2 className="mb-1 font-semibold">Política de privacidade</h2><p className="whitespace-pre-line">{privacy}</p></section>}
        </div>
        {accept.isError && <p role="alert" className="text-sm text-destructive">Não foi possível registrar. Tente novamente.</p>}
        <div className="flex flex-wrap gap-2">
          <Button disabled={accept.isPending} onClick={() => accept.mutate()}>Li e aceito</Button>
          <Button variant="ghost" disabled={logout.isPending} onClick={() => logout.mutate()}>Sair</Button>
        </div>
      </div>
    </main>
  )
}
