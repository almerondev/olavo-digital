import { useQuery } from '@tanstack/react-query'
import { ExternalLink } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { MaterialCard } from '@/components/cards/MaterialCard'
import { CAROUSEL_GUTTER, Carousel } from '@/components/carousels/Carousel'
import { coverSrc, coverSrcSet } from '@/components/media/cover'
import { ApiError } from '@/services/http'
import type { CardMaterial } from '@/types/api'
import { castApi } from '../api'

const NETWORK_LABELS: Record<string, string> = {
  instagram: 'Instagram',
  youtube: 'YouTube',
  tiktok: 'TikTok',
  linkedin: 'LinkedIn',
  site: 'Site',
}

function Row({ title, items }: { title: string; items: CardMaterial[] }) {
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

export function TeacherProfilePage() {
  const { slug = '' } = useParams()
  const profile = useQuery({ queryKey: ['cast', slug], queryFn: () => castApi.profile(slug) })

  if (profile.isPending) return <div role="status" aria-label="Carregando" className="min-h-[60dvh] animate-pulse bg-card/40" />

  if (profile.isError) {
    const missing = profile.error instanceof ApiError && profile.error.status === 404

    return (
      <div className="grid min-h-[70dvh] place-items-center px-4 text-center">
        <div>
          <h1 className="font-heading text-3xl font-bold">{missing ? 'Professor não encontrado' : 'Não foi possível carregar'}</h1>
          <Link to="/elenco" className="mt-4 inline-block text-brand hover:underline">Ver o Elenco</Link>
        </div>
      </div>
    )
  }

  const { teacher, videos, specials, materials, live } = profile.data
  const banner = coverSrc(teacher.banner_urls)
  const photo = coverSrc(teacher.photo_urls)
  const empty = videos.length + specials.length + materials.length + live.length === 0

  return (
    <>
      <section className="relative overflow-hidden px-4 pt-28 pb-10 sm:px-8 sm:pt-36 lg:px-12">
        {banner && <img src={banner} srcSet={coverSrcSet(teacher.banner_urls)} sizes="100vw" alt="" className="absolute inset-0 size-full object-cover opacity-60" />}
        <div aria-hidden className="absolute inset-0 bg-gradient-to-r from-background via-background/70 to-transparent" />
        <div aria-hidden className="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-background to-transparent" />

        <div className="relative flex flex-col gap-6 sm:flex-row sm:items-end">
          {photo && <img src={photo} srcSet={coverSrcSet(teacher.photo_urls)} sizes="192px" alt={`Foto de ${teacher.name}`} className="aspect-[3/4] w-36 shrink-0 rounded-xl object-cover shadow-xl sm:w-48" />}
          <div className="max-w-2xl">
            {teacher.subjects.length > 0 && <p className="text-sm font-semibold text-brand">{teacher.subjects.join(' · ')}</p>}
            <h1 className="mt-1 font-heading text-3xl leading-tight font-bold tracking-tight sm:text-5xl">{teacher.name}</h1>
            {teacher.bio && <p className="mt-3 text-base text-foreground/85">{teacher.bio}</p>}
            {teacher.specialties.length > 0 && (
              <ul aria-label="Especialidades" className="mt-3 flex flex-wrap gap-1.5">
                {teacher.specialties.map((specialty) => <li key={specialty} className="rounded-lg bg-white/10 px-2 py-0.5 text-xs">{specialty}</li>)}
              </ul>
            )}
            {teacher.socials.length > 0 && (
              <ul aria-label="Redes sociais" className="mt-3 flex flex-wrap gap-3 text-sm">
                {teacher.socials.map((social) => (
                  <li key={social.network}>
                    <a href={social.url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-foreground/80 hover:text-foreground hover:underline">
                      {NETWORK_LABELS[social.network] ?? social.network}
                      <ExternalLink className="size-3" />
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </section>

      <div className="grid gap-8 pt-4 pb-16">
        <Row title="Videoaulas" items={videos} />
        <Row title="Aulas especiais" items={specials} />
        <Row title="Materiais" items={materials} />
        <Row title="Participações na Olavo TV" items={live} />
        {empty && <p className="px-4 text-muted-foreground sm:px-8 lg:px-12">Este professor ainda não tem conteúdos liberados para você.</p>}
      </div>
    </>
  )
}
