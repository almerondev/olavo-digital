import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { StatusBadge } from '@/modules/materials/components/StatusBadge'
import { STATUS_LABELS, TYPE_LABELS } from '@/modules/materials/labels'
import { teacherPanelApi } from '../api'

export function TeacherMaterialsPage() {
  const [q, setQ] = useState('')
  const [status, setStatus] = useState('')
  const params = new URLSearchParams(Object.entries({ q, status }).filter(([, value]) => value))
  const materials = useQuery({
    queryKey: ['teacher', 'materials', params.toString()],
    queryFn: () => teacherPanelApi.materials(params),
    placeholderData: keepPreviousData,
  })
  const rows = materials.data?.data ?? []

  return (
    <div className="grid max-w-5xl gap-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <h1 className="font-heading text-3xl font-bold tracking-tight">Minhas publicações</h1>
        <Button render={<Link to="/professor/materiais/novo" />}><Plus className="size-4" />Nova publicação</Button>
      </header>

      <div className="grid gap-2 sm:grid-cols-2">
        <Input aria-label="Buscar por título" placeholder="Buscar por título" value={q} onChange={(event) => setQ(event.target.value)} />
        <NativeSelect aria-label="Status" value={status} onChange={(event) => setStatus(event.target.value)}>
          <option value="">Todos os status</option>
          {Object.entries(STATUS_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </NativeSelect>
      </div>

      {materials.isError && <p role="alert">Não foi possível carregar suas publicações. Recarregue a página.</p>}
      {materials.isSuccess && rows.length === 0 && (
        <div className="rounded-lg border bg-card p-10 text-center">
          <p className="font-medium">Nada por aqui</p>
          <p className="mt-1 text-sm text-muted-foreground">{params.size > 0 ? 'Ajuste os filtros.' : 'Crie sua primeira publicação.'}</p>
        </div>
      )}

      {rows.length > 0 && (
        <ul className="overflow-hidden rounded-lg border bg-card text-sm">
          {rows.map((row) => (
            <li key={row.id} className="border-b last:border-0">
              <Link to={`/professor/materiais/${row.id}`} className="flex items-center gap-3 p-3 hover:bg-muted/60">
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-medium">{row.title}</span>
                  <span className="block truncate text-xs text-muted-foreground">{TYPE_LABELS[row.type]} · {row.categories.map((category) => category.name).join(', ') || 'Sem categoria'}</span>
                  {row.review_note && <span className="block truncate text-xs text-amber-700">Revisão: {row.review_note}</span>}
                </span>
                <StatusBadge status={row.status} />
                <span className="hidden w-20 text-right text-xs text-muted-foreground tabular-nums sm:block">{row.views_count ?? 0} acessos</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
