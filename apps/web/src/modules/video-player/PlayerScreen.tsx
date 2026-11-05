import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import { catalogApi } from '@/modules/catalog/api/catalog'
import { ViewerOverlay } from '@/modules/materials/viewers/ViewerOverlay'
import type { CardMaterial, MaterialDetail } from '@/types/api'
import { VideoPlayer } from './VideoPlayer'

export function PlayerScreen({ material, related, onClose }: { material: MaterialDetail; related: CardMaterial[]; onClose: () => void }) {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()
  const playback = useQuery({
    queryKey: ['playback', material.slug],
    queryFn: () => catalogApi.playback(material.slug),
    staleTime: 0,
    gcTime: 0,
  })
  const complete = useMutation({ mutationFn: () => catalogApi.complete(material.slug) })

  function close() {
    onClose()
    // Atualiza Continuar assistindo e o progresso dos cards depois de sair do player.
    queryClient.invalidateQueries({ queryKey: ['home'] })
    queryClient.invalidateQueries({ queryKey: ['material', material.slug] })
  }

  if (playback.isPending) {
    return <ViewerOverlay title={material.title} onClose={close}><p role="status" className="p-8 text-center text-muted-foreground">Preparando o vídeo…</p></ViewerOverlay>
  }

  if (playback.isError) {
    return <ViewerOverlay title={material.title} onClose={close}><p role="alert" className="p-8 text-center">Não foi possível carregar este vídeo. Tente novamente.</p></ViewerOverlay>
  }

  return (
    <VideoPlayer
      key={material.slug}
      source={playback.data}
      title={material.title}
      slug={material.slug}
      saveProgress={user?.is_student ?? false}
      related={related}
      onClose={close}
      onComplete={() => complete.mutate()}
    />
  )
}
