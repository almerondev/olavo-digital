import { useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useNavigate } from 'react-router'
import { UNREAD_KEY } from '@/modules/communication/components/NotificationBell'
import { usePublicConfig } from '@/modules/settings/config'
import { isNativeApp, startPush } from './push'

export function PushRegistration() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data } = usePublicConfig()
  const enabled = isNativeApp() && data?.['push.enabled'] === true

  useEffect(() => {
    if (!enabled) return

    startPush({
      onOpen: (url) => {
        if (url.startsWith('/')) navigate(url)
        else window.open(url, '_blank', 'noopener,noreferrer')
      },
      onReceive: () => queryClient.invalidateQueries({ queryKey: UNREAD_KEY }),
    }).catch((error: unknown) => console.error('Não foi possível ligar as notificações.', error))
  }, [enabled, navigate, queryClient])

  return null
}
