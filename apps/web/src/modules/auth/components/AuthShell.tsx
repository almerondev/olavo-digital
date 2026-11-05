import type { ReactNode } from 'react'
import { Wordmark } from '@/components/navigation/Wordmark'

export function AuthShell({ title, subtitle, children }: { title: string; subtitle?: string; children: ReactNode }) {
  return (
    <main className="dark relative grid min-h-dvh bg-background text-foreground lg:grid-cols-[1.15fr_1fr]">
      <div
        aria-hidden
        className="absolute inset-0 bg-[radial-gradient(70%_60%_at_12%_18%,oklch(0.8_0.16_78/0.2),transparent_70%),radial-gradient(50%_50%_at_90%_95%,oklch(0.5_0.12_270/0.22),transparent_70%)]"
      />
      <section className="relative hidden flex-col justify-between p-12 lg:flex">
        <Wordmark className="h-20" />
        <p className="max-w-lg font-heading text-5xl leading-[1.05] font-bold tracking-tight text-balance">
          Aulas, materiais e transmissões do Olavo em um só lugar.
        </p>
      </section>
      <section className="relative flex items-center justify-center px-4 py-12 sm:px-8">
        <div className="w-full max-w-sm">
          <Wordmark className="mb-10 h-16 lg:hidden" />
          <h1 className="font-heading text-3xl font-bold tracking-tight">{title}</h1>
          {subtitle && <p className="mt-2 text-sm text-muted-foreground">{subtitle}</p>}
          <div className="mt-8">{children}</div>
        </div>
      </section>
    </main>
  )
}
