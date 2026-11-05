import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { MaterialCard } from '@/components/cards/MaterialCard'
import { coverSrc } from '@/components/media/cover'
import { STUDENT_TYPE_LABELS } from '@/components/cards/typeLabels'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { catalogApi } from '@/modules/catalog/api/catalog'
import type { MaterialType } from '@/types/api'
import { communicationApi } from '../api'

const TYPE_FILTERS: MaterialType[] = ['video', 'external_video', 'pdf', 'audio', 'image', 'link', 'document', 'presentation', 'spreadsheet']
const GRID = 'grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7'

export function SearchPage() {
  const [params, setParams] = useSearchParams()
  const [term, setTerm] = useState(params.get('q') ?? '')
  const filters = useQuery({ queryKey: ['catalog', 'filters'], queryFn: catalogApi.filters })
  const query = (params.get('q') ?? '').trim()

  const results = useQuery({
    queryKey: ['search', params.toString()],
    queryFn: () => communicationApi.search(params),
    enabled: query.length >= 2,
    placeholderData: keepPreviousData,
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
      if (term !== (params.get('q') ?? '')) setFilter('q', term)
    }, 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [term])

  const data = query.length >= 2 ? results.data : undefined
  const empty = data && data.total === 0 && data.teachers.length === 0

  return (
    <div className="px-4 pt-24 pb-16 sm:px-8 lg:px-12">
      <div className="relative max-w-2xl">
        <Search aria-hidden className="absolute top-3 left-3 size-5 text-muted-foreground" />
        <Input autoFocus aria-label="Pesquisar" placeholder="Títulos, matérias, professores, tags" className="h-11 pl-10 text-base" value={term} onChange={(e) => setTerm(e.target.value)} />
      </div>

      <div className="mt-3 grid max-w-2xl gap-2 sm:grid-cols-3">
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
      </div>

      {query.length < 2 && <p className="mt-16 text-center text-muted-foreground">Digite ao menos duas letras para pesquisar.</p>}
      {results.isError && query.length >= 2 && <p role="alert" className="mt-8">Não foi possível pesquisar. Tente novamente.</p>}

      {empty && (
        <div className="mt-16 text-center">
          <p className="font-medium">Nada encontrado para “{query}”</p>
          <p className="mt-1 text-sm text-muted-foreground">Tente outra palavra ou remova alguns filtros.</p>
        </div>
      )}

      {data && !empty && (
        <div className="mt-8 grid gap-8">
          {data.groups.map((group) => (
            <section key={group.key} aria-label={group.label}>
              <h2 className="mb-3 font-heading text-lg font-bold tracking-tight sm:text-xl">{group.label}</h2>
              <div className={GRID}>{group.items.map((material) => <MaterialCard key={material.id} material={material} />)}</div>
            </section>
          ))}
          {data.teachers.length > 0 && (
            <section aria-label="Professores">
              <h2 className="mb-3 font-heading text-lg font-bold tracking-tight sm:text-xl">Professores relacionados</h2>
              <ul className="flex flex-wrap gap-3">
                {data.teachers.map((teacher) => {
                  const photo = coverSrc(teacher.photo_urls)

                  return (
                    <li key={teacher.id}>
                      <Link to={`/elenco/${teacher.slug}`} className="flex items-center gap-3 rounded-xl border bg-card p-2 pr-4 transition hover:bg-muted">
                        <span className="size-12 shrink-0 overflow-hidden rounded-lg bg-muted">{photo && <img src={photo} alt="" className="size-full object-cover" />}</span>
                        <span>
                          <span className="block font-medium">{teacher.name}</span>
                          <span className="block max-w-56 truncate text-xs text-muted-foreground">{teacher.subjects.join(' · ')}</span>
                        </span>
                      </Link>
                    </li>
                  )
                })}
              </ul>
            </section>
          )}
        </div>
      )}
    </div>
  )
}
