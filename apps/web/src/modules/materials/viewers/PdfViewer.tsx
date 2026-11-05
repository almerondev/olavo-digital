import { ZoomIn, ZoomOut } from 'lucide-react'
import * as pdfjs from 'pdfjs-dist'
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import { useEffect, useRef, useState } from 'react'
import { useInView } from '@/hooks/useInView'
import { ViewerOverlay } from './ViewerOverlay'

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl

const MIN_ZOOM = 0.6
const MAX_ZOOM = 2.4
const ZOOM_STEP = 0.2
const MAX_PAGE_WIDTH = 900

type PdfDocument = Awaited<ReturnType<typeof pdfjs.getDocument>['promise']>

export default function PdfViewer({ url, title, onClose }: { url: string; title: string; onClose: () => void }) {
  const [document, setDocument] = useState<PdfDocument | null>(null)
  const [failed, setFailed] = useState(false)
  const [zoom, setZoom] = useState(1)

  useEffect(() => {
    let cancelled = false
    const task = pdfjs.getDocument({ url })

    task.promise
      .then((loaded) => {
        if (!cancelled) setDocument(loaded)
      })
      .catch(() => {
        // Fechar o leitor também rejeita a promessa, e isso não é erro de abertura.
        if (!cancelled) setFailed(true)
      })

    return () => {
      cancelled = true
      void task.destroy()
    }
  }, [url])

  const button = 'grid size-9 place-items-center rounded transition hover:bg-white/10 disabled:opacity-40'

  return (
    <ViewerOverlay
      title={title}
      onClose={onClose}
      toolbar={
        <div className="flex items-center gap-1 text-xs tabular-nums">
          {document && <span className="mr-2 text-muted-foreground">{document.numPages} {document.numPages === 1 ? 'página' : 'páginas'}</span>}
          <button type="button" aria-label="Diminuir zoom" className={button} disabled={zoom <= MIN_ZOOM} onClick={() => setZoom((value) => value - ZOOM_STEP)}>
            <ZoomOut className="size-4" />
          </button>
          <span className="w-10 text-center">{Math.round(zoom * 100)}%</span>
          <button type="button" aria-label="Aumentar zoom" className={button} disabled={zoom >= MAX_ZOOM} onClick={() => setZoom((value) => value + ZOOM_STEP)}>
            <ZoomIn className="size-4" />
          </button>
        </div>
      }
    >
      {failed && <p role="alert" className="p-8 text-center">Não foi possível abrir este PDF. Tente novamente.</p>}
      {!document && !failed && <p role="status" className="p-8 text-center text-muted-foreground">Abrindo documento…</p>}
      {document && (
        <div className="grid justify-items-center gap-3 p-3">
          {Array.from({ length: document.numPages }, (_, index) => (
            <PdfPage key={index} document={document} number={index + 1} zoom={zoom} />
          ))}
        </div>
      )}
    </ViewerOverlay>
  )
}

/** Cada página só é desenhada ao se aproximar da tela. */
function PdfPage({ document, number, zoom }: { document: PdfDocument; number: number; zoom: number }) {
  const { ref, inView } = useInView<HTMLCanvasElement>('800px')
  const rendering = useRef<ReturnType<Awaited<ReturnType<PdfDocument['getPage']>>['render']> | null>(null)

  useEffect(() => {
    if (!inView) return
    let cancelled = false

    async function draw() {
      const page = await document.getPage(number)

      // O pdf.js não aceita dois desenhos no mesmo canvas: espera o anterior terminar.
      rendering.current?.cancel()
      await rendering.current?.promise.catch(() => undefined)

      const canvas = ref.current
      if (cancelled || !canvas) return

      const fit = Math.min(canvas.parentElement!.clientWidth - 24, MAX_PAGE_WIDTH) / page.getViewport({ scale: 1 }).width
      const ratio = window.devicePixelRatio || 1
      const viewport = page.getViewport({ scale: fit * zoom * ratio })

      canvas.width = viewport.width
      canvas.height = viewport.height
      canvas.style.width = `${viewport.width / ratio}px`
      rendering.current = page.render({ canvas, viewport })
      await rendering.current.promise
    }

    draw().catch(() => undefined)

    return () => {
      cancelled = true
    }
  }, [document, inView, number, ref, zoom])

  return <canvas ref={ref} aria-label={`Página ${number}`} className="max-w-none min-h-96 min-w-64 bg-white" />
}
