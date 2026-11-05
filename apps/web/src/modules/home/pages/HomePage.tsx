import { useQuery } from '@tanstack/react-query'
import { ContentRow } from '@/components/netflix/ContentRow'
import { HeroBanner } from '@/components/netflix/HeroBanner'
import { catalogApi, type Surface } from '@/modules/catalog/api/catalog'
import { AnnouncementsArea } from '@/modules/communication/components/AnnouncementsArea'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import type { HomeData } from '@/types/api'

export function SurfaceContent({ data, cacheScope }: { data: HomeData; cacheScope?: string }) {
  return (
    <>
      {data.hero[0] ? <HeroBanner hero={data.hero[0]} /> : <div className="h-20" />}
      <div className="relative z-10 grid gap-8 pb-16 sm:-mt-10">
        <AnnouncementsArea items={data.announcements ?? []} readOnly={Boolean(cacheScope)} />
        {data.sections.map((section) => <ContentRow key={section.id} section={section} cacheScope={cacheScope} />)}
      </div>
    </>
  )
}

function SurfacePage({ surface }: { surface: Surface }) {
  const { data: user } = useCurrentUser()
  const home = useQuery({ queryKey: ['home', surface], queryFn: () => catalogApi.home(surface) })

  if (home.isPending) return <div aria-label="Carregando" role="status" className="min-h-[78dvh] animate-pulse bg-card/40" />

  if (home.isError) {
    return (
      <div className="grid min-h-[70dvh] place-items-center px-4 text-center">
        <p role="alert">Não foi possível carregar a página inicial. Recarregue para tentar novamente.</p>
      </div>
    )
  }

  if (home.data.hero.length === 0 && home.data.sections.length === 0 && !home.data.announcements?.length) {
    return (
      <section className="flex min-h-[70dvh] items-end px-4 pt-24 pb-16 sm:px-8 lg:px-12">
        <div className="max-w-2xl">
          <h1 className="font-heading text-4xl font-bold tracking-tight sm:text-6xl">Olá, {user?.name.split(' ')[0]}.</h1>
          <p className="mt-4 text-lg text-foreground/75">Ainda não há conteúdos liberados para você. Eles aparecerão aqui assim que forem publicados.</p>
        </div>
      </section>
    )
  }

  return <SurfaceContent data={home.data} />
}

/** Início: vídeos e produções audiovisuais. */
export function HomePage() {
  return <SurfacePage key="home" surface="home" />
}

/** Materiais: PDFs, imagens, documentos, áudios e links, na mesma estrutura de cards. */
export function MaterialsPage() {
  return <SurfacePage key="materials" surface="materials" />
}
