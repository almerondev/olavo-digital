import { useInfiniteQuery, useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import { MaterialCard } from '@/components/cards/MaterialCard'
import { STUDENT_TYPE_LABELS } from '@/components/cards/typeLabels'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { useInView } from '@/hooks/useInView'
import type { MaterialType } from '@/types/api'
import { catalogApi } from '../api/catalog'

const TYPE_FILTERS: MaterialType[] = ['pdf', 'video', 'audio', 'image', 'link', 'document', 'presentation', 'spreadsheet']

export function ExplorePage() {
  const [params, setParams] = useSearchParams()
  const [search, setSearch] = useState(params.get('q') ?? '')
  const filters = useQuery({ queryKey: ['catalog', 'filters'], queryFn: catalogApi.filters })

  const results = useInfiniteQuery({
    queryKey: ['catalog', params.toString()],
    queryFn: ({ pageParam }) => catalogApi.materials(params, pageParam),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  })

  function setFilter(name: string, value: string) {
    setParams((current) => {
      const next = new URLSearchParams(current)
      if (value) next.set(name, value)
      else next.delete(name)
      return next
    }, { replace: true })
  }

  useEffect(() => {
    const timer = setTimeout(() => {
      if (search !== (params.get('q') ?? '')) setFilter('q', search)
    }, 350)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search])

  const items = results.data?.pages.flatMap((page) => page.data) ?? []
  const total = results.data?.pages[0].meta.total

  return (
    <div className="px-4 pt-24 pb-16 sm:px-8 lg:px-12">
      <h1 className="font-heading text-3xl font-bold tracking-tight sm:text-4xl">Explorar</h1>

      <div className="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
        <div className="relative sm:col-span-2 lg:col-span-2">
          <Search aria-hidden className="absolute top-2 left-2.5 size-4 text-muted-foreground" />
          <Input aria-label="Pesquisar conteúdos" placeholder="Pesquisar conteúdos" className="pl-8" value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
        <NativeSelect aria-label="Matéria" value={params.get('category') ?? ''} onChange={(e) => setFilter('category', e.target.value)}>
          <option value="">Todas as matérias</option>
          {filters.data?.categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
        </NativeSelect>
        <NativeSelect aria-label="Professor" value={params.get('teacher') ?? ''} onChange={(e) => setFilter('teacher', e.target.value)}>
          <option value="">Todos os professores</option>
          {filters.data?.teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
        </NativeSelect>
        <NativeSelect aria-label="Tipo de conteúdo" value={params.get('type') ?? ''} onChange={(e) => setFilter('type', e.target.value)}>
          <option value="">Todos os tipos</option>
          {TYPE_FILTERS.map((type) => <option key={type} value={type}>{STUDENT_TYPE_LABELS[type]}</option>)}
        </NativeSelect>
        <NativeSelect aria-label="Ordenar por" value={params.get('sort') ?? 'recent'} onChange={(e) => setFilter('sort', e.target.value === 'recent' ? '' : e.target.value)}>
          <option value="recent">Mais recentes</option>
          <option value="relevance">Mais relevantes</option>
        </NativeSelect>
      </div>

      <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
        <label className="flex items-center gap-2">
          Publicado de
          <Input type="date" className="w-auto" value={params.get('from') ?? ''} onChange={(e) => setFilter('from', e.target.value)} />
        </label>
        <label className="flex items-center gap-2">
          até
          <Input type="date" className="w-auto" value={params.get('to') ?? ''} onChange={(e) => setFilter('to', e.target.value)} />
        </label>
      </div>

      {total !== undefined && <p role="status" className="mt-5 text-sm text-muted-foreground tabular-nums">{total} {total === 1 ? 'resultado' : 'resultados'}</p>}

      {results.isError && <p role="alert" className="mt-8">Não foi possível carregar o catálogo. Recarregue a página para tentar novamente.</p>}

      {results.isSuccess && items.length === 0 && (
        <div className="mt-16 text-center">
          <p className="font-medium">Nada encontrado</p>
          <p className="mt-1 text-sm text-muted-foreground">Tente outra palavra ou remova alguns filtros.</p>
        </div>
      )}

      <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7">
        {items.map((material) => <MaterialCard key={material.id} material={material} />)}
      </div>

      {results.hasNextPage && <LoadMore key={items.length} onVisible={() => results.fetchNextPage()} />}
    </div>
  )
}

function LoadMore({ onVisible }: { onVisible: () => void }) {
  const { ref, inView } = useInView<HTMLDivElement>('200px')

  useEffect(() => {
    if (inView) onVisible()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [inView])

  return <div ref={ref} aria-hidden className="h-10" />
}
