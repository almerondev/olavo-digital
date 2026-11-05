import { useQuery } from '@tanstack/react-query'
import { Bell } from 'lucide-react'
import { Link } from 'react-router'
import { communicationApi } from '../api'

export const UNREAD_KEY = ['notifications', 'unread']

export function NotificationBell() {
  const unread = useQuery({ queryKey: UNREAD_KEY, queryFn: communicationApi.unreadCount, refetchInterval: 30000 })
  const count = unread.data ?? 0

  return (
    <Link to="/notificacoes" aria-label={count > 0 ? `Notificações: ${count} não lidas` : 'Notificações'} className="relative rounded p-1 opacity-70 transition hover:opacity-100">
      <Bell className="size-4" />
      {count > 0 && (
        <span aria-hidden className="absolute -top-1 -right-1 grid h-4 min-w-4 place-items-center rounded-full bg-live px-1 text-[10px] font-bold text-white tabular-nums">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </Link>
  )
}
