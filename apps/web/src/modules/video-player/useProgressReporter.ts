import { useEffect, useRef, type RefObject } from 'react'
import { catalogApi } from '@/modules/catalog/api/catalog'

const REPORT_EVERY_MS = 15000

/** Salva o ponto de reprodução a cada 15 segundos, ao pausar, ao terminar e ao sair da página. */
export function useProgressReporter(video: RefObject<HTMLVideoElement | null>, slug: string, enabled: boolean) {
  const watched = useRef(0)
  const lastTime = useRef(0)

  useEffect(() => {
    const element = video.current
    if (!element || !enabled) return

    function report(keepalive = false) {
      if (!element || element.currentTime === 0) return

      const seconds = Math.round(watched.current)
      watched.current = 0

      catalogApi
        .saveProgress(slug, {
          position: Math.floor(element.currentTime),
          duration: Number.isFinite(element.duration) ? Math.ceil(element.duration) : null,
          watched: seconds,
        }, keepalive)
        .catch(() => {
          watched.current += seconds
        })
    }

    function onTimeUpdate() {
      const delta = element!.currentTime - lastTime.current
      lastTime.current = element!.currentTime

      // Saltos na barra não contam como tempo assistido.
      if (delta > 0 && delta < 2) watched.current += delta
    }

    const onPause = () => report()
    // fetch com keepalive leva o cabeçalho CSRF; sendBeacon não permite cabeçalhos.
    const onLeave = () => report(true)
    const onHidden = () => document.visibilityState === 'hidden' && report(true)

    const timer = window.setInterval(() => {
      if (!element.paused) report()
    }, REPORT_EVERY_MS)

    element.addEventListener('timeupdate', onTimeUpdate)
    element.addEventListener('pause', onPause)
    window.addEventListener('pagehide', onLeave)
    document.addEventListener('visibilitychange', onHidden)

    return () => {
      report(true)
      window.clearInterval(timer)
      element.removeEventListener('timeupdate', onTimeUpdate)
      element.removeEventListener('pause', onPause)
      window.removeEventListener('pagehide', onLeave)
      document.removeEventListener('visibilitychange', onHidden)
    }
  }, [enabled, slug, video])
}
