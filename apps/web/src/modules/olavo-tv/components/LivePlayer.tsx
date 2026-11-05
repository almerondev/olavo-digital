import { useQuery } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { tvApi } from '../api'

/** Inicia sem som, como exigem os navegadores para tocar sozinho; o aluno ativa nos controles. */
export function LivePlayer({ streamId, title, reconnecting }: { streamId: number; title: string; reconnecting: boolean }) {
  const video = useRef<HTMLVideoElement>(null)
  const [failed, setFailed] = useState(false)
  const playback = useQuery({ queryKey: ['tv', 'playback', streamId], queryFn: () => tvApi.playback(streamId), staleTime: 5 * 60 * 1000 })
  const source = playback.data

  useEffect(() => {
    const element = video.current
    if (!element || !source) return

    let destroy: (() => void) | undefined
    let cancelled = false

    if (source.kind === 'hls' && !element.canPlayType('application/vnd.apple.mpegurl')) {
      import('hls.js').then(({ default: Hls }) => {
        if (cancelled || !Hls.isSupported()) return

        const hls = new Hls({ liveDurationInfinity: true })
        destroy = () => hls.destroy()
        hls.loadSource(source.url)
        hls.attachMedia(element)
        hls.on(Hls.Events.ERROR, (_event, data) => {
          if (data.fatal) setFailed(true)
        })
      })
    } else {
      element.src = source.url
    }

    return () => {
      cancelled = true
      destroy?.()
    }
  }, [source])

  return (
    <div className="relative aspect-video w-full overflow-hidden rounded-xl bg-black">
      <video
        ref={video}
        aria-label={`Transmissão: ${title}`}
        muted
        autoPlay
        playsInline
        controls
        loop={source?.kind === 'file'}
        onError={() => setFailed(true)}
        className="size-full"
      />
      {reconnecting && <p role="status" className="absolute inset-x-0 top-0 bg-black/70 p-2 text-center text-sm">Sinal instável. Aguarde, a transmissão volta em instantes.</p>}
      {(failed || playback.isError) && <p role="alert" className="absolute inset-0 grid place-items-center bg-black/80 p-6 text-center">Não foi possível reproduzir a transmissão. Recarregue a página.</p>}
    </div>
  )
}
