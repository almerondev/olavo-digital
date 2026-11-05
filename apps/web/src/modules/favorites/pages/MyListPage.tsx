import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router'
import { MaterialCard } from '@/components/cards/MaterialCard'
import { NativeSelect } from '@/components/ui/native-select'
import { catalogApi } from '@/modules/catalog/api/catalog'

export function MyListPage() {
  const [params, setParams] = useSearchParams()
  const category = params.get('categoria') ?? ''
  const filters = useQuery({ queryKey: ['catalog', 'filters'], queryFn: catalogApi.filters })
  const favorites = useQuery({ queryKey: ['favorites', category], queryFn: () => catalogApi.favorites(category) })
  const items = favorites.data?.data ?? []

  return (
    <div className="px-4 pt-24 pb-16 sm:px-8 lg:px-12">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <h1 className="font-heading text-3xl font-bold tracking-tight sm:text-4xl">Minha lista</h1>
        <NativeSelect
          aria-label="Filtrar por categoria"
          className="w-56"
          value={category}
          onChange={(event) => setParams(event.target.value ? { categoria: event.target.value } : {}, { replace: true })}
        >
          <option value="">Todas as categorias</option>
          {filters.data?.categories.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
        </NativeSelect>
      </div>

      {favorites.isError && <p role="alert" className="mt-8">Não foi possível carregar sua lista. Recarregue a página para tentar novamente.</p>}

      {favorites.isSuccess && items.length === 0 && (
        <div className="mt-20 text-center">
          <p className="font-medium">{category ? 'Nada salvo nesta categoria' : 'Sua lista está vazia'}</p>
          <p className="mt-1 text-sm text-muted-foreground">Use o botão + em um conteúdo para guardá-lo aqui.</p>
          <Link to="/" className="mt-4 inline-block text-brand hover:underline">Ver conteúdos</Link>
        </div>
      )}

      <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7">
        {items.map((material) => <MaterialCard key={material.id} material={material} />)}
      </div>
    </div>
  )
}
