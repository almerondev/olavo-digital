import { Capacitor } from '@capacitor/core'
import { PushNotifications } from '@capacitor/push-notifications'
import { api } from '@/services/http'

let currentToken: string | null = null

export function isNativeApp(): boolean {
  return Capacitor.isNativePlatform()
}

/** Pede permissão e liga o aparelho à conta. Sem Firebase no app, registrar derrubaria o Android. */
export async function startPush({ onOpen, onReceive }: { onOpen: (url: string) => void; onReceive: () => void }) {
  await PushNotifications.removeAllListeners()

  await PushNotifications.addListener('registration', ({ value }) => {
    currentToken = value
    void api<void>('/me/push-devices', { method: 'POST', body: { token: value, platform: Capacitor.getPlatform() } })
  })
  await PushNotifications.addListener('pushNotificationReceived', onReceive)
  await PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
    const url = notification.data?.url as string | undefined
    if (url) onOpen(url)
  })

  const permission = await PushNotifications.requestPermissions()
  if (permission.receive !== 'granted') return

  await PushNotifications.createChannel({ id: 'avisos', name: 'Avisos do Olavo TV+', importance: 4, visibility: 1 })
  await PushNotifications.register()
}

/** Ao sair, o aparelho deixa de receber avisos daquela conta. */
export async function stopPush() {
  if (!currentToken) return

  const token = currentToken
  currentToken = null
  await api<void>('/me/push-devices', { method: 'DELETE', body: { token } }).catch(() => undefined)
}
