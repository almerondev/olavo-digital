import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { ApiError } from '@/services/http'
import { formatDate } from '@/utils/format'
import { materialsApi, type BulkAction, type BulkResult } from '../api/materials'
import { StatusBadge } from '../components/StatusBadge'
import { flattenTree, useCategories, useTeacherOptions } from '../hooks'
import { STATUS_LABELS, TYPE_LABELS, VISIBILITY_LABELS } from '../labels'

const LIBRARY_KEY = ['admin', 'materials']

export function MaterialsLibraryPage() {
  const [params, setParams] = useSearchParams()
  const [search, setSearch] = useState(params.get('q') ?? '')
  const [selected, setSelected] = useState<number[]>([])
  const categories = useCategories()
  const teachers = useTeacherOptions()

  const library = useQuery({
    queryKey: [...LIBRARY_KEY, params.toString()],
    queryFn: () => materialsApi.list(params),
    placeholderData: keepPreviousData,
  })

  function setFilter(name: string, value: string) {
    setSelected([])
    setParams((current) => {
      const next = new URLSearchParams(current)
      if (value) next.set(name, value)
      else next.delete(name)
      next.delete('page')
      return next
    })
  }

  useEffect(() => {
    const timer = setTimeout(() => {
      if (search !== (params.get('q') ?? '')) setFilter('q', search)
    }, 350)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search])

  const rows = library.data?.data ?? []
  const allSelected = rows.length > 0 && rows.every((row) => selected.includes(row.id))

  return (
    <div className="grid gap-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Biblioteca de materiais</h1>
          <p className="mt-1 text-sm text-muted-foreground tabular-nums">
            {library.data ? `${library.data.meta.total} ${library.data.meta.total === 1 ? "material" : "materiais"}` : "Carregando…"}
          </p>
        </div>
        <Button render={<Link to="/admin/materiais/novo" />}>
          <Plus className="size-4" />
          Novo material
        </Button>
      </header>

      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <Input aria-label="Buscar por título" placeholder="Buscar por título" value={search} onChange={(e) => setSearch(e.target.value)} />
        <NativeSelect aria-label="Matéria ou categoria" value={params.get('category_id') ?? ''} onChange={(e) => setFilter('category_id', e.target.value)}>
          <option value="">Todas as categorias</option>
          {flattenTree(categories.data ?? []).map(({ category, depth }) => (
            <option key={category.id} value={category.id}>
              {'— '.repeat(depth)}
              {category.name}
            </option>
          ))}
        </NativeSelect>
        <NativeSelect aria-label="Professor" value={params.get('teacher_id') ?? ''} onChange={(e) => setFilter('teacher_id', e.target.value)}>
          <option value="">Todos os professores</option>
          {teachers.data?.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
        </NativeSelect>
        <NativeSelect aria-label="Tipo de arquivo" value={params.get('type') ?? ''} onChange={(e) => setFilter('type', e.target.value)}>
          <option value="">Todos os tipos</option>
          {Object.entries(TYPE_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </NativeSelect>
        <NativeSelect aria-label="Status" value={params.get('status') ?? ''} onChange={(e) => setFilter('status', e.target.value)}>
          <option value="">Todos os status</option>
          {Object.entries(STATUS_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </NativeSelect>
        <NativeSelect aria-label="Visibilidade" value={params.get('visibility') ?? ''} onChange={(e) => setFilter('visibility', e.target.value)}>
          <option value="">Qualquer visibilidade</option>
          {Object.entries(VISIBILITY_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </NativeSelect>
        <Input type="date" aria-label="Criado a partir de" value={params.get('from') ?? ''} onChange={(e) => setFilter('from', e.target.value)} />
        <Input type="date" aria-label="Criado até" value={params.get('to') ?? ''} onChange={(e) => setFilter('to', e.target.value)} />
      </div>

      {selected.length > 0 && <BulkBar ids={selected} onDone={() => setSelected([])} />}

      {library.isError ? (
        <p role="alert">Não foi possível carregar a biblioteca. Recarregue a página para tentar novamente.</p>
      ) : rows.length === 0 && !library.isPending ? (
        <div className="rounded-lg border bg-card p-10 text-center">
          <p className="font-medium">Nenhum material encontrado</p>
          <p className="mt-1 text-sm text-muted-foreground">
            {params.size > 0 ? 'Ajuste os filtros para ver outros resultados.' : 'Crie o primeiro material para começar.'}
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto rounded-lg border bg-card">
          <table className="w-full min-w-[760px] text-sm">
            <thead className="text-left text-xs text-muted-foreground">
              <tr className="border-b">
                <th className="w-10 p-3">
                  <Checkbox
                    aria-label="Selecionar todos"
                    checked={allSelected}
                    onCheckedChange={(checked) => setSelected(checked === true ? rows.map((row) => row.id) : [])}
                  />
                </th>
                <th className="p-3 font-medium">Material</th>
                <th className="p-3 font-medium">Professor</th>
                <th className="p-3 font-medium">Tipo</th>
                <th className="p-3 font-medium">Status</th>
                <th className="p-3 font-medium">Publicação</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b last:border-0 hover:bg-muted/40">
                  <td className="p-3">
                    <Checkbox
                      aria-label={`Selecionar ${row.title}`}
                      checked={selected.includes(row.id)}
                      onCheckedChange={(checked) =>
                        setSelected(checked === true ? [...selected, row.id] : selected.filter((id) => id !== row.id))
                      }
                    />
                  </td>
                  <td className="p-3">
                    <Link to={`/admin/materiais/${row.id}`} className="flex items-center gap-3 font-medium hover:underline">
                      {row.cover && Object.values(row.cover.image_urls)[0] ? (
                        <img src={Object.values(row.cover.image_urls)[0]} alt="" loading="lazy" className="h-10 w-16 rounded object-cover" />
                      ) : (
                        <span aria-hidden className="h-10 w-16 shrink-0 rounded bg-muted" />
                      )}
                      <span>
                        {row.title}
                        <span className="block text-xs font-normal text-muted-foreground">
                          {row.categories.map((category) => category.name).join(', ') || 'Sem categoria'}
                        </span>
                      </span>
                    </Link>
                  </td>
                  <td className="p-3">{row.teacher?.name ?? '—'}</td>
                  <td className="p-3">{TYPE_LABELS[row.type]}</td>
                  <td className="p-3"><StatusBadge status={row.status} /></td>
                  <td className="p-3 whitespace-nowrap tabular-nums">{formatDate(row.published_at ?? row.publish_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {library.data && library.data.meta.last_page > 1 && (
        <nav aria-label="Páginas" className="flex items-center justify-end gap-3 text-sm">
          <Button variant="outline" size="sm" disabled={library.data.meta.current_page === 1} onClick={() => setFilter('page', String(library.data.meta.current_page - 1))}>
            Anterior
          </Button>
          <span className="tabular-nums">
            {library.data.meta.current_page} de {library.data.meta.last_page}
          </span>
          <Button variant="outline" size="sm" disabled={library.data.meta.current_page === library.data.meta.last_page} onClick={() => setFilter('page', String(library.data.meta.current_page + 1))}>
            Próxima
          </Button>
        </nav>
      )}
    </div>
  )
}

function BulkBar({ ids, onDone }: { ids: number[]; onDone: () => void }) {
  const queryClient = useQueryClient()
  const categories = useCategories()
  const teachers = useTeacherOptions()
  const [confirming, setConfirming] = useState(false)
  const [result, setResult] = useState<BulkResult | null>(null)

  const bulk = useMutation({
    mutationFn: materialsApi.bulk,
    onSuccess: async (data) => {
      setResult(data)
      setConfirming(false)
      await queryClient.invalidateQueries({ queryKey: LIBRARY_KEY })
      if (data.failed.length === 0) onDone()
    },
  })

  const run = (action: BulkAction, extra: { teacher_id?: number; category_id?: number } = {}) =>
    bulk.mutate({ action, ids, ...extra })

  return (
    <div className="grid gap-2 rounded-lg border bg-card p-3 text-sm">
      <div className="flex flex-wrap items-center gap-2">
        <span className="mr-2 font-medium tabular-nums">{ids.length} selecionados</span>
        <Button size="sm" variant="outline" disabled={bulk.isPending} onClick={() => run('publish')}>Publicar</Button>
        <Button size="sm" variant="outline" disabled={bulk.isPending} onClick={() => run('archive')}>Arquivar</Button>
        <NativeSelect
          aria-label="Alterar professor"
          className="w-44"
          value=""
          disabled={bulk.isPending}
          onChange={(e) => run('set_teacher', { teacher_id: Number(e.target.value) })}
        >
          <option value="">Alterar professor…</option>
          {teachers.data?.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
        </NativeSelect>
        <NativeSelect
          aria-label="Adicionar categoria"
          className="w-44"
          value=""
          disabled={bulk.isPending}
          onChange={(e) => run('add_category', { category_id: Number(e.target.value) })}
        >
          <option value="">Adicionar categoria…</option>
          {categories.data?.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
        </NativeSelect>
        <Button
          size="sm"
          variant={confirming ? 'destructive' : 'ghost'}
          disabled={bulk.isPending}
          onClick={() => (confirming ? run('delete') : setConfirming(true))}
        >
          {confirming ? `Confirmar exclusão de ${ids.length}` : 'Excluir'}
        </Button>
      </div>
      {bulk.error instanceof ApiError && <p role="alert" className="text-destructive">{bulk.error.message}</p>}
      {result && (
        <div role="status">
          <p>{result.processed === 1 ? "1 material atualizado." : `${result.processed} materiais atualizados.`}</p>
          {result.failed.map((item) => (
            <p key={item.id} className="text-destructive">{item.title}: {item.reason}</p>
          ))}
        </div>
      )}
    </div>
  )
}
