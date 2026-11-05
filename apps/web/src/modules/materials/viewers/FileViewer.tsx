import { useQuery } from '@tanstack/react-query'
import { lazy, Suspense } from 'react'
import { catalogApi } from '@/modules/catalog/api/catalog'
import type { MaterialDetail } from '@/types/api'
import { ViewerOverlay } from './ViewerOverlay'

// pdf.js é pesado: só baixa quando alguém abre um PDF.
const PdfViewer = lazy(() => import('./PdfViewer'))

export function ExternalVideoViewer({ material, onClose }: { material: MaterialDetail; onClose: () => void }) {
  return (
    <ViewerOverlay title={material.title} onClose={onClose}>
      <div className="grid h-full place-items-center p-3">
        <iframe
          src={material.embed_url ?? undefined}
          title={material.title}
          allow="autoplay; encrypted-media; fullscreen; picture-in-picture"
          allowFullScreen
          referrerPolicy="strict-origin-when-cross-origin"
          className="aspect-video max-h-full w-full max-w-6xl bg-black"
        />
      </div>
    </ViewerOverlay>
  )
}

export function FileViewer({ material, onClose }: { material: MaterialDetail; onClose: () => void }) {
  const file = useQuery({
    queryKey: ['material-file', material.slug],
    queryFn: () => catalogApi.file(material.slug),
    staleTime: 0,
    gcTime: 0,
  })

  if (file.isPending) {
    return <ViewerOverlay title={material.title} onClose={onClose}><p role="status" className="p-8 text-center text-muted-foreground">Abrindo…</p></ViewerOverlay>
  }

  if (file.isError) {
    return <ViewerOverlay title={material.title} onClose={onClose}><p role="alert" className="p-8 text-center">Não foi possível abrir este conteúdo. Tente novamente.</p></ViewerOverlay>
  }

  const { url } = file.data

  switch (material.file?.class) {
    case 'pdf':
      return <Suspense fallback={null}><PdfViewer url={url} title={material.title} onClose={onClose} /></Suspense>
    case 'image':
      return (
        <ViewerOverlay title={material.title} onClose={onClose}>
          <div className="grid min-h-full place-items-center p-3"><img src={url} alt={material.title} className="max-w-full" /></div>
        </ViewerOverlay>
      )
    case 'audio':
      return (
        <ViewerOverlay title={material.title} onClose={onClose}>
          <div className="grid h-full place-items-center p-6"><audio src={url} controls autoPlay className="w-full max-w-xl" /></div>
        </ViewerOverlay>
      )
    default:
      return null
  }
}
