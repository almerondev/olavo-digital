import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Play, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { STUDENT_TYPE_LABELS } from '@/components/cards/typeLabels'
import { coverSrc } from '@/components/media/cover'
import { Button } from '@/components/ui/button'
import { catalogApi } from '@/modules/catalog/api/catalog'
import { formatDate } from '@/utils/format'

const HISTORY_KEY = ['history']

export function HistoryPage() {
  const queryClient = useQueryClient()
  const [confirming, setConfirming] = useState(false)
  const history = useQuery({ queryKey: HISTORY_KEY, queryFn: catalogApi.history })
  const refresh = () => queryClient.invalidateQueries({ queryKey: HISTORY_KEY })

  const clear = useMutation({
    mutationFn: catalogApi.clearHistory,
    onSuccess: () => {
      setConfirming(false)
      return refresh()
    },
  })
  const remove = useMutation({ mutationFn: catalogApi.removeHistoryEntry, onSuccess: refresh })
  const entries = history.data?.data ?? []

  return (
    <div className="mx-auto max-w-4xl px-4 pt-24 pb-16 sm:px-8">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-heading text-3xl font-bold tracking-tight sm:text-4xl">Histórico</h1>
        {entries.length > 0 && (
          <Button variant={confirming ? 'destructive' : 'ghost'} disabled={clear.isPending} onClick={() => (confirming ? clear.mutate() : setConfirming(true))}>
            {confirming ? 'Confirmar limpeza' : 'Limpar histórico'}
          </Button>
        )}
      </div>

      {history.isError && <p role="alert" className="mt-8">Não foi possível carregar o histórico. Recarregue a página para tentar novamente.</p>}

      {history.isSuccess && entries.length === 0 && (
        <p className="mt-16 text-center text-muted-foreground">Os conteúdos que você acessar aparecerão aqui.</p>
      )}

      <ul className="mt-6 grid gap-2">
        {entries.map((entry) => {
          const { material } = entry
          const cover = coverSrc(material.image_urls)
          const resumable = !entry.completed && (material.progress?.percent ?? 0) > 0

          return (
            <li key={entry.id} className="flex items-center gap-3 rounded-xl bg-card p-2">
              <Link to={`/materiais/${material.slug}`} className="flex min-w-0 flex-1 items-center gap-3">
                {cover ? <img src={cover} alt="" loading="lazy" className="h-20 w-14 shrink-0 rounded-lg object-cover" /> : <span aria-hidden className="h-20 w-14 shrink-0 rounded-lg bg-muted" />}
                <span className="min-w-0">
                  <span className="line-clamp-2 font-semibold">{material.title}</span>
                  <span className="block text-xs text-muted-foreground">{STUDENT_TYPE_LABELS[material.type]} · {formatDate(entry.accessed_at)}</span>
                  {entry.completed && (
                    <span className="mt-1 inline-flex items-center gap-1 text-xs text-brand">
                      <CheckCircle2 className="size-3.5" />
                      Concluído
                    </span>
                  )}
                  {resumable && <span className="mt-1 block text-xs text-muted-foreground">{material.progress?.percent}% assistido</span>}
                </span>
              </Link>
              {resumable && (
                <Link to={`/materiais/${material.slug}?abrir=1`} aria-label={`Continuar ${material.title}`} className="grid size-9 shrink-0 place-items-center rounded-full bg-white text-black transition hover:bg-white/85">
                  <Play className="size-4 fill-current" />
                </Link>
              )}
              <Button variant="ghost" size="icon" aria-label={`Remover ${material.title} do histórico`} disabled={remove.isPending} onClick={() => remove.mutate(entry.id)}>
                <Trash2 className="size-4" />
              </Button>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
