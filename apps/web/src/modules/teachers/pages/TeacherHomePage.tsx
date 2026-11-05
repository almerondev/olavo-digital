import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import { StatusBadge } from '@/modules/materials/components/StatusBadge'
import { teacherPanelApi } from '../api'

export function TeacherHomePage() {
  const { data: user } = useCurrentUser()
  const overview = useQuery({ queryKey: ['teacher', 'overview'], queryFn: teacherPanelApi.overview })

  if (overview.isPending) return <p className="text-muted-foreground">Carregando…</p>
  if (overview.isError) return <p role="alert">Não foi possível carregar o painel. Recarregue a página.</p>

  const { totals, top_materials: top, can_publish: canPublish } = overview.data
  const tiles: [string, number][] = [
    ['Publicados', totals.published],
    ['Em revisão', totals.pending_review],
    ['Rascunhos', totals.drafts],
    ['Acessos', totals.views],
    ['Alunos alcançados', totals.students],
  ]

  return (
    <div className="grid max-w-4xl gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Olá, {user?.name.split(' ')[0]}</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {canPublish ? 'Suas publicações vão direto para os alunos.' : 'Suas publicações passam pela aprovação do administrador.'}
          </p>
        </div>
        <Button render={<Link to="/professor/materiais/novo" />}><Plus className="size-4" />Nova publicação</Button>
      </header>

      <dl className="grid grid-cols-2 gap-3 sm:grid-cols-5">
        {tiles.map(([label, value]) => (
          <div key={label} className="rounded-lg border bg-card p-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-heading text-2xl font-bold tabular-nums">{value}</dd>
          </div>
        ))}
      </dl>

      <section>
        <h2 className="mb-2 font-heading text-lg font-bold">Mais acessados</h2>
        {top.length === 0 ? (
          <p className="rounded-lg border bg-card p-4 text-sm text-muted-foreground">Você ainda não tem publicações. Elas aparecerão aqui.</p>
        ) : (
          <ul className="overflow-hidden rounded-lg border bg-card text-sm">
            {top.map((material) => (
              <li key={material.id} className="border-b last:border-0">
                <Link to={`/professor/materiais/${material.id}`} className="flex items-center gap-3 p-3 hover:bg-muted/60">
                  <span className="min-w-0 flex-1 truncate font-medium">{material.title}</span>
                  <StatusBadge status={material.status} />
                  <span className="w-20 text-right text-xs text-muted-foreground tabular-nums">{material.views} {material.views === 1 ? 'acesso' : 'acessos'}</span>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
