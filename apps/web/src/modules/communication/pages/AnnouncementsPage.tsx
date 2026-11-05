import { useInfiniteQuery } from '@tanstack/react-query'
import { coverSrc } from '@/components/media/cover'
import { Button } from '@/components/ui/button'
import { formatDate } from '@/utils/format'
import { communicationApi } from '../api'
import { AnnouncementAction, PriorityTag } from '../components/AnnouncementsArea'

export function AnnouncementsPage() {
  const list = useInfiniteQuery({
    queryKey: ['announcements'],
    queryFn: ({ pageParam }) => communicationApi.announcements(pageParam),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  })
  const items = list.data?.pages.flatMap((page) => page.data) ?? []

  return (
    <div className="mx-auto max-w-3xl px-4 pt-24 pb-16 sm:px-8">
      <h1 className="font-heading text-3xl font-bold tracking-tight sm:text-4xl">Avisos</h1>
      {list.isError && <p role="alert" className="mt-6">Não foi possível carregar os avisos. Recarregue a página.</p>}
      {list.isSuccess && items.length === 0 && <p className="mt-10 text-center text-muted-foreground">Nenhum aviso no momento.</p>}
      <div className="mt-5 grid gap-3">
        {items.map((item) => {
          const image = coverSrc(item.image_urls)

          return (
            <article key={item.id} className="overflow-hidden rounded-xl border bg-card">
              {image && <img src={image} alt="" className="aspect-[3/1] w-full object-cover" />}
              <div className="grid gap-2 p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="font-heading text-lg font-bold">{item.title}</h2>
                  <PriorityTag priority={item.priority} />
                </div>
                <p className="text-xs text-muted-foreground tabular-nums">{formatDate(item.published_at)}</p>
                <p className="whitespace-pre-line text-sm text-foreground/85">{item.body}</p>
                <AnnouncementAction item={item} />
              </div>
            </article>
          )
        })}
      </div>
      {list.hasNextPage && <div className="mt-4 text-center"><Button variant="outline" disabled={list.isFetchingNextPage} onClick={() => list.fetchNextPage()}>Carregar mais</Button></div>}
    </div>
  )
}
