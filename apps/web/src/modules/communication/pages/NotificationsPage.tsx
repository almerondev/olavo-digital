import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Bell, FileText, Megaphone, Radio, Video, type LucideIcon } from 'lucide-react'
import { Link, useNavigate } from 'react-router'
import { coverSrc } from '@/components/media/cover'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import type { AppNotification } from '@/types/api'
import { formatDate } from '@/utils/format'
import { communicationApi } from '../api'
import { UNREAD_KEY } from '../components/NotificationBell'

const KEY = ['notifications', 'list']
const ICONS: Record<string, LucideIcon> = { material: FileText, video: Video, live: Radio, announcement: Megaphone }

function Row({ item, onOpen }: { item: AppNotification; onOpen: (item: AppNotification) => void }) {
  const Icon = ICONS[item.type] ?? Bell
  const image = coverSrc(item.image_urls)

  return (
    <li className="border-b last:border-0">
      <button type="button" onClick={() => onOpen(item)} className={cn('flex w-full items-start gap-3 p-3 text-left transition hover:bg-muted/50', !item.read_at && 'bg-muted/30')}>
        <span className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-muted">
          {image ? <img src={image} alt="" className="size-full object-cover" /> : <Icon aria-hidden className="size-4" />}
        </span>
        <span className="min-w-0 flex-1">
          <span className={cn('block', !item.read_at && 'font-semibold')}>{item.title}</span>
          <span className="block text-sm text-foreground/75">{item.body}</span>
          <span className="block text-xs text-muted-foreground tabular-nums">{formatDate(item.created_at)}</span>
        </span>
        {!item.read_at && <span className="mt-2 size-2 shrink-0 rounded-full bg-live"><span className="sr-only">Não lida</span></span>}
      </button>
    </li>
  )
}

export function NotificationsPage() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const list = useInfiniteQuery({
    queryKey: KEY,
    queryFn: ({ pageParam }) => communicationApi.notifications(pageParam),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
  })
  const refresh = () => Promise.all([queryClient.invalidateQueries({ queryKey: KEY }), queryClient.invalidateQueries({ queryKey: UNREAD_KEY })])
  const readAll = useMutation({ mutationFn: communicationApi.readAll, onSuccess: refresh })
  const items = list.data?.pages.flatMap((page) => page.data) ?? []

  function open(item: AppNotification) {
    if (!item.read_at) void communicationApi.readNotification(item.id).then(refresh)
    if (!item.url) return
    if (item.url.startsWith('/')) navigate(item.url)
    else window.open(item.url, '_blank', 'noopener,noreferrer')
  }

  return (
    <div className="mx-auto max-w-3xl px-4 pt-24 pb-16 sm:px-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h1 className="font-heading text-3xl font-bold tracking-tight sm:text-4xl">Notificações</h1>
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" render={<Link to="/avisos" />}>Ver avisos</Button>
          <Button variant="outline" size="sm" disabled={readAll.isPending || items.every((item) => item.read_at)} onClick={() => readAll.mutate()}>Marcar todas como lidas</Button>
        </div>
      </div>

      {list.isError && <p role="alert" className="mt-6">Não foi possível carregar as notificações. Recarregue a página.</p>}
      {list.isSuccess && items.length === 0 && <p className="mt-10 text-center text-muted-foreground">Você não tem notificações por enquanto.</p>}

      {items.length > 0 && <ul className="mt-5 overflow-hidden rounded-xl border bg-card">{items.map((item) => <Row key={item.id} item={item} onOpen={open} />)}</ul>}
      {list.hasNextPage && <div className="mt-4 text-center"><Button variant="outline" disabled={list.isFetchingNextPage} onClick={() => list.fetchNextPage()}>Carregar mais</Button></div>}
    </div>
  )
}
