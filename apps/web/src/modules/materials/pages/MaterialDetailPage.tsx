import { useQuery } from '@tanstack/react-query'
import { Download, ExternalLink, Play } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router'
import { MaterialCard } from '@/components/cards/MaterialCard'
import { STUDENT_TYPE_LABELS } from '@/components/cards/typeLabels'
import { CAROUSEL_GUTTER, Carousel } from '@/components/carousels/Carousel'
import { coverSrc, coverSrcSet } from '@/components/media/cover'
import { catalogApi } from '@/modules/catalog/api/catalog'
import { FavoriteButton } from '@/modules/favorites/FavoriteButton'
import { PlayerScreen } from '@/modules/video-player/PlayerScreen'
import { ApiError } from '@/services/http'
import type { CardMaterial, MaterialDetail } from '@/types/api'
import { formatBytes } from '@/utils/format'
import { embedUrl, ExternalVideoViewer, FileViewer } from '../viewers/FileViewer'

const INLINE_CLASSES = ['pdf', 'image', 'audio', 'video']

function accessLabel(material: MaterialDetail): string {
  if (material.type === 'pdf') return 'Abrir leitor'
  if (material.type === 'image') return 'Ver imagem'
  if (material.type === 'audio') return 'Ouvir'
  if (material.type === 'link') return 'Abrir link'
  return 'Assistir agora'
}

export function MaterialDetailPage() {
  const { slug = '' } = useParams()
  const [params] = useSearchParams()
  const material = useQuery({ queryKey: ['material', slug], queryFn: () => catalogApi.material(slug) })
  const related = useQuery({ queryKey: ['material', slug, 'related'], queryFn: () => catalogApi.related(slug), enabled: material.isSuccess })

  if (material.isPending) return <div role="status" aria-label="Carregando" className="min-h-[60dvh] animate-pulse bg-card/40" />

  if (material.isError) {
    const missing = material.error instanceof ApiError && material.error.status === 404

    return (
      <div className="grid min-h-[70dvh] place-items-center px-4 text-center">
        <div>
          <h1 className="font-heading text-3xl font-bold">{missing ? 'Conteúdo indisponível' : 'Não foi possível carregar'}</h1>
          <p className="mt-2 text-muted-foreground">{missing ? 'Ele não existe ou não está liberado para você.' : 'Recarregue a página para tentar novamente.'}</p>
          <Link to="/explorar" className="mt-5 inline-block text-brand hover:underline">Ir para Explorar</Link>
        </div>
      </div>
    )
  }

  return (
    <MaterialView
      key={material.data.slug}
      material={material.data}
      openOnLoad={params.get('abrir') === '1'}
      related={related.data?.related ?? []}
      fromTeacher={related.data?.from_teacher ?? []}
    />
  )
}

interface MaterialViewProps {
  material: MaterialDetail
  openOnLoad: boolean
  related: CardMaterial[]
  fromTeacher: CardMaterial[]
}

function MaterialView({ material, openOnLoad, related, fromTeacher }: MaterialViewProps) {
  const opensInline = INLINE_CLASSES.includes(material.file?.class ?? '')
  const embeds = embedUrl(material) !== null
  const canOpenHere = opensInline || embeds
  const [viewing, setViewing] = useState(openOnLoad && canOpenHere)
  const [downloadError, setDownloadError] = useState<string | null>(null)
  const closeViewer = useCallback(() => setViewing(false), [])
  const usesPlayer = material.type === 'video'

  useEffect(() => {
    if (viewing && !usesPlayer) catalogApi.access(material.slug).catch(() => undefined)
  }, [material.slug, usesPlayer, viewing])
  const banner = Object.keys(material.banner_urls).length ? material.banner_urls : material.image_urls
  const src = coverSrc(banner)

  async function download() {
    setDownloadError(null)

    try {
      const { url, name } = await catalogApi.file(material.slug, true)
      const link = document.createElement('a')
      link.href = url
      link.download = name
      link.click()
    } catch (error) {
      setDownloadError(error instanceof ApiError ? error.message : 'Não foi possível baixar o arquivo.')
    }
  }

  const facts: [string, string][] = [
    ['Professor', material.teacher ?? '—'],
    ['Matéria', material.category ?? '—'],
    ['Categorias', material.categories.join(', ') || '—'],
    ['Publicado em', material.published_at ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(new Date(material.published_at)) : '—'],
    ['Tipo', STUDENT_TYPE_LABELS[material.type]],
  ]

  return (
    <>
      <section className="relative overflow-hidden px-4 pt-28 pb-10 sm:px-8 sm:pt-40 lg:px-12">
        {src && <img src={src} srcSet={coverSrcSet(banner)} sizes="100vw" alt="" className="absolute inset-0 size-full object-cover opacity-70" />}
        <div aria-hidden className="absolute inset-0 bg-gradient-to-r from-background via-background/70 to-transparent" />
        <div aria-hidden className="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-background to-transparent" />

        <div className="relative max-w-2xl">
          <p className="mb-3 text-sm font-medium text-foreground/80">{STUDENT_TYPE_LABELS[material.type]}{material.category && ` · ${material.category}`}</p>
          <h1 className="font-heading text-3xl leading-[1.06] font-bold tracking-tight text-balance sm:text-5xl">{material.title}</h1>
          {material.description && <p className="mt-4 text-base text-foreground/85 sm:text-lg">{material.description}</p>}

          <div className="mt-6 flex flex-wrap gap-3">
            {canOpenHere && (
              <button type="button" onClick={() => setViewing(true)} className="inline-flex items-center gap-2 rounded bg-white px-6 py-2.5 font-bold text-black transition hover:bg-white/85">
                <Play className="size-5 fill-current" />
                {usesPlayer && material.progress && !material.progress.completed && material.progress.percent > 0 ? 'Continuar assistindo' : accessLabel(material)}
              </button>
            )}
            {material.external_url && !embeds && (
              <a href={material.external_url} target="_blank" rel="noreferrer" onClick={() => catalogApi.access(material.slug).catch(() => undefined)} className="inline-flex items-center gap-2 rounded bg-white px-6 py-2.5 font-bold text-black transition hover:bg-white/85">
                <ExternalLink className="size-5" />
                {accessLabel(material)}
              </a>
            )}
            <FavoriteButton slug={material.slug} title={material.title} initial={material.is_favorite} withLabel />
            {material.allow_download && material.file && (
              <button type="button" onClick={download} className="inline-flex items-center gap-2 rounded bg-white/15 px-6 py-2.5 font-bold backdrop-blur transition hover:bg-white/25">
                <Download className="size-5" />
                Baixar {material.file.size_bytes ? `(${formatBytes(material.file.size_bytes)})` : ''}
              </button>
            )}
          </div>

          {material.file && !opensInline && !material.allow_download && (
            <p className="mt-4 text-sm text-muted-foreground">Este formato ainda não abre na plataforma, e o download não foi liberado pelo professor.</p>
          )}
          {downloadError && <p role="alert" className="mt-3 text-sm text-destructive">{downloadError}</p>}
        </div>
      </section>

      <div className="px-4 py-6 sm:px-8 lg:px-12">
        <dl className="grid max-w-4xl gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
          {facts.map(([label, value]) => (
            <div key={label}>
              <dt className="text-muted-foreground">{label}</dt>
              <dd className="font-medium">
                {label === 'Professor' && material.teacher_slug ? <Link to={`/elenco/${material.teacher_slug}`} className="hover:underline">{value}</Link> : value}
              </dd>
            </div>
          ))}
        </dl>
        {material.tags.length > 0 && (
          <ul aria-label="Tags" className="mt-5 flex flex-wrap gap-1.5">
            {material.tags.map((tag) => (
              <li key={tag}>
                <Link to={`/explorar?q=${encodeURIComponent(tag)}`} className="inline-block rounded bg-white/10 px-2 py-0.5 text-xs transition hover:bg-white/20">{tag}</Link>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="grid gap-8 pt-2 pb-16">
        <RelatedRow title="Materiais relacionados" items={related} />
        <RelatedRow title={`Mais de ${material.teacher ?? 'este professor'}`} items={fromTeacher} />
      </div>

      {viewing && usesPlayer && <PlayerScreen material={material} related={related} onClose={closeViewer} />}
      {viewing && embeds && <ExternalVideoViewer material={material} onClose={closeViewer} />}
      {viewing && !usesPlayer && !embeds && <FileViewer material={material} onClose={closeViewer} />}
    </>
  )
}

function RelatedRow({ title, items }: { title: string; items: CardMaterial[] }) {
  if (items.length === 0) return null

  return (
    <section aria-label={title}>
      <h2 className={`mb-2 font-heading text-lg font-bold tracking-tight sm:text-xl ${CAROUSEL_GUTTER}`}>{title}</h2>
      <Carousel label={title}>
        {items.map((item) => <MaterialCard key={item.id} material={item} />)}
      </Carousel>
    </section>
  )
}
