import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { CURRENT_USER_KEY } from '@/modules/auth/hooks/useCurrentUser'
import { api } from '@/services/http'
import type { User } from '@/types/api'
import { downloadJson } from '@/utils/download'
import { formatDate } from '@/utils/format'
import { profileApi } from '../api'

const panel = 'grid grid-cols-[minmax(0,1fr)] gap-3 rounded-xl bg-card p-5'
const TYPES = [['material', 'Novos materiais'], ['video', 'Novos vídeos'], ['live', 'Transmissões ao vivo'], ['announcement', 'Avisos']] as const

export function PreferencesPanel({ user }: { user: User }) {
  const queryClient = useQueryClient()
  const [muted, setMuted] = useState<string[]>((user.preferences?.muted_notifications as string[] | undefined) ?? [])
  const save = useMutation({
    mutationFn: (next: string[]) => profileApi.update({ preferences: { ...user.preferences, muted_notifications: next } }),
    onSuccess: (updated) => queryClient.setQueryData(CURRENT_USER_KEY, updated),
  })

  function toggle(type: string, on: boolean) {
    const next = on ? muted.filter((item) => item !== type) : [...muted, type]
    setMuted(next)
    save.mutate(next)
  }

  return (
    <section aria-label="Notificações" className={panel}>
      <h2 className="font-heading text-lg font-bold">Notificações</h2>
      <p className="text-sm text-muted-foreground">Escolha o que quer receber. Mensagens diretas da equipe sempre chegam.</p>
      {TYPES.map(([type, label]) => (
        <Label key={type} className="flex items-center gap-2 font-normal">
          <Checkbox checked={!muted.includes(type)} onCheckedChange={(value) => toggle(type, value === true)} />
          {label}
        </Label>
      ))}
      {save.isError && <p role="alert" className="text-sm text-destructive">Não foi possível salvar. Tente novamente.</p>}
    </section>
  )
}

export function PrivacyPanel({ user }: { user: User }) {
  const queryClient = useQueryClient()
  const refresh = () => queryClient.invalidateQueries({ queryKey: CURRENT_USER_KEY })
  const exportData = useMutation({
    mutationFn: () => api<{ data: unknown }>('/me/export').then((r) => r.data),
    onSuccess: (data) => downloadJson('meus-dados-olavo.json', data),
  })
  const request = useMutation({ mutationFn: () => api<void>('/me/deletion-request', { method: 'POST' }), onSuccess: refresh })
  const cancel = useMutation({ mutationFn: () => api<void>('/me/deletion-request', { method: 'DELETE' }), onSuccess: refresh })

  return (
    <section aria-label="Privacidade" className={panel}>
      <h2 className="font-heading text-lg font-bold">Privacidade</h2>
      <p className="text-sm text-muted-foreground">Você pode baixar uma cópia dos seus dados ou pedir a exclusão deles.</p>
      <div className="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" disabled={exportData.isPending} onClick={() => exportData.mutate()}>Baixar meus dados</Button>
        {user.deletion_requested_at
          ? <Button variant="outline" size="sm" disabled={cancel.isPending} onClick={() => cancel.mutate()}>Cancelar pedido de exclusão</Button>
          : <Button variant="ghost" size="sm" disabled={request.isPending} onClick={() => request.mutate()}>Pedir exclusão</Button>}
      </div>
      {user.deletion_requested_at && <p role="status" className="text-sm text-muted-foreground">Pedido registrado em {formatDate(user.deletion_requested_at)}. A equipe vai concluir a exclusão.</p>}
      {(exportData.isError || request.isError || cancel.isError) && <p role="alert" className="text-sm text-destructive">Não foi possível concluir. Tente novamente.</p>}
    </section>
  )
}
