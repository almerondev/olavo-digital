import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Check, Plus } from 'lucide-react'
import { useState } from 'react'
import { cn } from '@/lib/utils'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import { catalogApi } from '@/modules/catalog/api/catalog'

interface FavoriteButtonProps {
  slug: string
  title: string
  initial: boolean
  withLabel?: boolean
  className?: string
}

export function FavoriteButton({ slug, title, initial, withLabel, className }: FavoriteButtonProps) {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()
  const [favorite, setFavorite] = useState(initial)

  const toggle = useMutation({
    mutationFn: (next: boolean) => (next ? catalogApi.addFavorite(slug) : catalogApi.removeFavorite(slug)),
    onMutate: setFavorite,
    onError: (_error, next) => setFavorite(!next),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['favorites'] }),
  })

  if (!user?.is_student) return null

  const label = favorite ? 'Na minha lista' : 'Minha lista'
  const Icon = favorite ? Check : Plus

  return (
    <button
      type="button"
      aria-pressed={favorite}
      aria-label={`${favorite ? 'Remover' : 'Adicionar'} ${title} ${favorite ? 'da' : 'à'} minha lista`}
      disabled={toggle.isPending}
      onClick={() => toggle.mutate(!favorite)}
      className={cn(
        withLabel
          ? 'inline-flex items-center gap-2 rounded-lg bg-white/15 px-6 py-2.5 font-bold backdrop-blur transition hover:bg-white/25'
          : 'grid size-7 place-items-center rounded-full border border-white/40 transition hover:border-white',
        className,
      )}
    >
      <Icon className={withLabel ? 'size-5' : 'size-3.5'} />
      {withLabel && label}
    </button>
  )
}
