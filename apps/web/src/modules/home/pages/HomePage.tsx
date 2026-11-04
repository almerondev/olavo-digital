import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'

/** Estado vazio real: a home dinâmica substitui esta tela quando a biblioteca existir (fases 2 e 4). */
export function HomePage() {
  const { data: user } = useCurrentUser()
  const firstName = user?.name.split(' ')[0]

  return (
    <section className="relative flex min-h-[75dvh] items-end overflow-hidden px-4 pt-24 pb-16 sm:px-8 lg:px-12">
      <div
        aria-hidden
        className="absolute inset-0 bg-[radial-gradient(60%_80%_at_75%_20%,oklch(0.8_0.16_78/0.28),transparent_70%),linear-gradient(to_top,var(--background),transparent_55%)]"
      />
      <div className="relative max-w-2xl">
        <h1 className="font-heading text-4xl font-bold tracking-tight text-balance sm:text-6xl">Olá, {firstName}.</h1>
        <p className="mt-4 text-lg text-foreground/75">
          Sua biblioteca ainda não tem conteúdos publicados. Eles aparecerão aqui assim que os professores começarem a
          publicar.
        </p>
      </div>
    </section>
  )
}
