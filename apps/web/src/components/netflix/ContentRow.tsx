import { useInfiniteQuery } from '@tanstack/react-query'
import { MaterialCard } from '@/components/cards/MaterialCard'
import { CAROUSEL_GUTTER, Carousel } from '@/components/carousels/Carousel'
import { useInView } from '@/hooks/useInView'
import { catalogApi } from '@/modules/catalog/api/catalog'
import type { HomeSection } from '@/types/api'

/** Busca os cards quando a seção se aproxima da tela e a próxima página no fim do carrossel. */
export function ContentRow({ section, cacheScope = 'home' }: { section: HomeSection; cacheScope?: string }) {
  const { ref, inView } = useInView<HTMLElement>()
  const preloaded = section.items !== undefined

  const query = useInfiniteQuery({
    queryKey: ['home', 'section', cacheScope, section.id],
    queryFn: ({ pageParam }) => catalogApi.sectionItems(section.id, pageParam),
    initialPageParam: 1,
    getNextPageParam: (last, pages) => (last.has_more ? pages.length + 1 : undefined),
    enabled: inView || preloaded,
    // Cards que vieram com a página não precisam de uma segunda busca.
    staleTime: preloaded ? Infinity : 0,
    initialData: preloaded
      ? { pages: [{ items: section.items!, has_more: section.has_more ?? false }], pageParams: [1] }
      : undefined,
  })

  const items = query.data?.pages.flatMap((page) => page.items) ?? []

  if (query.isSuccess && items.length === 0) return null

  return (
    <section ref={ref} aria-label={section.title} className="min-h-40">
      <div className={`mb-2 ${CAROUSEL_GUTTER}`}>
        <h2 className="font-heading text-lg font-bold tracking-tight sm:text-xl">{section.title}</h2>
        {section.description && <p className="text-sm text-muted-foreground">{section.description}</p>}
      </div>
      {section.layout === 'grid' && items.length > 0 ? (
        <div className={`grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 ${CAROUSEL_GUTTER}`}>
          {items.map((material) => <MaterialCard key={material.id} material={material} />)}
        </div>
      ) : items.length > 0 ? (
        <Carousel
          label={section.title}
          large={section.layout === 'carousel_large'}
          onReachEnd={() => {
            if (query.hasNextPage && !query.isFetchingNextPage) query.fetchNextPage()
          }}
        >
          {items.map((material) => <MaterialCard key={material.id} material={material} />)}
        </Carousel>
      ) : (
        <div aria-hidden className={`flex gap-3 overflow-hidden ${CAROUSEL_GUTTER}`}>
          {[0, 1, 2, 3, 4].map((skeleton) => (
            <div key={skeleton} className="aspect-[2/3] flex-[0_0_42%] animate-pulse rounded-xl bg-card sm:flex-[0_0_26%] md:flex-[0_0_20%] lg:flex-[0_0_15.5%]" />
          ))}
        </div>
      )}
    </section>
  )
}
