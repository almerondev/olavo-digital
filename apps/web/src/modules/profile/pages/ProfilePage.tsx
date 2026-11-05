import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { UploadField } from '@/components/media/UploadField'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { CURRENT_USER_KEY, useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import { ApiError } from '@/services/http'
import type { User } from '@/types/api'
import { formatDate } from '@/utils/format'
import { profileApi } from '../api'

const panel = 'grid grid-cols-[minmax(0,1fr)] gap-4 rounded-xl bg-card p-5'

function deviceName(userAgent: string | null): string {
  if (!userAgent) return 'Dispositivo desconhecido'

  // A ordem importa: Edge e Opera também se identificam como Chrome, e o Chrome como Safari.
  const browsers: [string, string][] = [['Edg/', 'Edge'], ['OPR/', 'Opera'], ['Firefox/', 'Firefox'], ['Chrome/', 'Chrome'], ['Safari/', 'Safari']]
  const browser = browsers.find(([token]) => userAgent.includes(token))?.[1] ?? 'Navegador'
  const system = /Android/.test(userAgent) ? 'Android' : /iPhone|iPad/.test(userAgent) ? 'iOS' : /Windows/.test(userAgent) ? 'Windows'
    : /Mac OS/.test(userAgent) ? 'Mac' : /Linux/.test(userAgent) ? 'Linux' : 'outro sistema'

  return `${browser} no ${system}`
}

function DetailsForm({ user }: { user: User }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState(user.name)
  const [phone, setPhone] = useState(user.phone ?? '')

  const save = useMutation({
    mutationFn: profileApi.update,
    onSuccess: (updated) => queryClient.setQueryData(CURRENT_USER_KEY, updated),
  })
  const error = save.error instanceof ApiError ? save.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate({ name, phone: phone || null })
  }

  return (
    <form onSubmit={submit} className={panel} noValidate>
      <h2 className="font-heading text-lg font-bold">Dados pessoais</h2>
      <UploadField
        label="Foto"
        purpose="avatar"
        accept=".jpg,.jpeg,.png,.webp"
        value={null}
        onChange={(upload) => upload && save.mutate({ avatar_upload_id: upload.id })}
      />
      <div className="grid gap-2">
        <Label htmlFor="profile-name">Nome</Label>
        <Input id="profile-name" value={name} aria-invalid={Boolean(error?.fieldError('name'))} onChange={(e) => setName(e.target.value)} />
        {error?.fieldError('name') && <p className="text-sm text-destructive">{error.fieldError('name')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="profile-email">E-mail</Label>
        <Input id="profile-email" value={user.email} disabled />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="profile-phone">Telefone</Label>
        <Input id="profile-phone" type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} />
      </div>
      {save.isSuccess && <p role="status" className="text-sm text-muted-foreground">Dados salvos.</p>}
      {error && !error.fieldError('name') && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      <div><Button type="submit" disabled={save.isPending}>Salvar</Button></div>
    </form>
  )
}

function PasswordForm() {
  const empty = { current_password: '', password: '', password_confirmation: '' }
  const [form, setForm] = useState(empty)
  const change = useMutation({ mutationFn: profileApi.password, onSuccess: () => setForm(empty) })
  const error = change.error instanceof ApiError ? change.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    change.mutate(form)
  }

  return (
    <form onSubmit={submit} className={panel} noValidate>
      <h2 className="font-heading text-lg font-bold">Alterar senha</h2>
      <div className="grid gap-2">
        <Label htmlFor="current-password">Senha atual</Label>
        <Input id="current-password" type="password" autoComplete="current-password" value={form.current_password} onChange={(e) => setForm({ ...form, current_password: e.target.value })} />
        {error?.fieldError('current_password') && <p className="text-sm text-destructive">{error.fieldError('current_password')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="new-password">Nova senha</Label>
        <Input id="new-password" type="password" autoComplete="new-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
        {error?.fieldError('password') && <p className="text-sm text-destructive">{error.fieldError('password')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="confirm-password">Confirme a nova senha</Label>
        <Input id="confirm-password" type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} />
      </div>
      {change.isSuccess && <p role="status" className="text-sm text-muted-foreground">Senha alterada. As outras sessões foram encerradas.</p>}
      <div><Button type="submit" disabled={change.isPending}>Alterar senha</Button></div>
    </form>
  )
}

function SessionsPanel() {
  const queryClient = useQueryClient()
  const sessions = useQuery({ queryKey: ['sessions'], queryFn: profileApi.sessions })
  const revoke = useMutation({
    mutationFn: profileApi.revokeSession,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['sessions'] }),
  })

  return (
    <section className={panel}>
      <h2 className="font-heading text-lg font-bold">Sessões ativas</h2>
      {sessions.isError && <p role="alert">Não foi possível carregar as sessões.</p>}
      <ul className="grid gap-2 text-sm">
        {sessions.data?.map((session) => (
          <li key={session.id} className="flex items-center justify-between gap-3">
            <span className="min-w-0">
              <span className="block truncate">{deviceName(session.user_agent)}</span>
              <span className="text-xs text-muted-foreground">{session.is_current ? 'Esta sessão' : `Último acesso em ${formatDate(session.last_activity_at)}`}</span>
            </span>
            {!session.is_current && <Button variant="ghost" size="sm" disabled={revoke.isPending} onClick={() => revoke.mutate(session.id)}>Encerrar</Button>}
          </li>
        ))}
      </ul>
    </section>
  )
}

export function ProfilePage() {
  const { data: user } = useCurrentUser()

  if (!user) return null

  return (
    <div className="mx-auto grid max-w-2xl grid-cols-[minmax(0,1fr)] gap-4 px-4 pt-24 pb-16 sm:px-8">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-heading text-3xl font-bold tracking-tight sm:text-4xl">Perfil</h1>
        {user.is_student && <Link to="/historico" className="text-sm text-brand hover:underline">Ver histórico</Link>}
      </div>
      <DetailsForm key={user.id} user={user} />
      <PasswordForm />
      <SessionsPanel />
    </div>
  )
}
