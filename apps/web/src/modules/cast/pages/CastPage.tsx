import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { coverSrc, coverSrcSet } from '@/components/media/cover'
import { castApi } from '../api'

export function CastPage() {
  const cast = useQuery({ queryKey: ['cast'], queryFn: castApi.list })

  return (
    <div className="px-4 pt-24 pb-16 sm:px-8 lg:px-12">
      <h1 className="font-heading text-3xl font-bold tracking-tight sm:text-4xl">Elenco</h1>
      <p className="mt-1 text-muted-foreground">Os professores do Olavo.</p>

      {cast.isError && <p role="alert" className="mt-8">Não foi possível carregar o Elenco. Recarregue a página para tentar novamente.</p>}
      {cast.isSuccess && cast.data.length === 0 && <p className="mt-16 text-center text-muted-foreground">Nenhum professor publicado ainda.</p>}

      <ul className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
        {cast.data?.map((teacher) => {
          const photo = coverSrc(teacher.photo_urls)

          return (
            <li key={teacher.id}>
              <Link to={`/elenco/${teacher.slug}`} className="group relative block aspect-[3/4] overflow-hidden rounded-xl bg-card ring-brand transition hover:ring-2 focus-visible:ring-2">
                {photo && (
                  <img
                    src={photo}
                    srcSet={coverSrcSet(teacher.photo_urls)}
                    sizes="(min-width: 1024px) 18vw, 45vw"
                    alt=""
                    loading="lazy"
                    className="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105"
                  />
                )}
                <div aria-hidden className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 p-3">
                  <p className="font-heading font-bold text-white">{teacher.name}</p>
                  {teacher.subjects.length > 0 && <p className="truncate text-xs font-medium text-brand">{teacher.subjects.join(' · ')}</p>}
                  {teacher.short_bio && (
                    <p className="mt-1 line-clamp-3 max-h-0 overflow-hidden text-xs text-white/80 opacity-0 transition-all duration-300 group-hover:max-h-16 group-hover:opacity-100">
                      {teacher.short_bio}
                    </p>
                  )}
                </div>
              </Link>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
