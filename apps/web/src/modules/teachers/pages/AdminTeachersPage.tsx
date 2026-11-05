import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { cn } from '@/lib/utils'
import { courseLabel, useCategories } from '@/modules/materials/hooks'
import { ApiError } from '@/services/http'
import type { TeacherAdmin } from '@/types/api'
import { adminTeachersApi } from '../api'
import { TeacherProfileFields, toProfileInput, type ProfileValues } from '../components/TeacherProfileFields'

const KEY = ['admin', 'teachers']

export function AdminTeachersPage() {
  const queryClient = useQueryClient()
  const [selection, setSelection] = useState<number | 'new' | null>(null)
  const teachers = useQuery({ queryKey: KEY, queryFn: adminTeachersApi.list })
  const policy = useQuery({ queryKey: ['admin', 'publish-policy'], queryFn: adminTeachersApi.policy })
  const setPolicy = useMutation({
    mutationFn: adminTeachersApi.setPolicy,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'publish-policy'] }),
  })

  const selected = teachers.data?.find((teacher) => teacher.id === selection) ?? null

  return (
    <div className="grid max-w-6xl gap-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Professores</h1>
          <p className="mt-1 text-sm text-muted-foreground">Cadastro, Elenco e permissões de publicação.</p>
        </div>
        <Button onClick={() => setSelection('new')}><Plus className="size-4" />Novo professor</Button>
      </header>

      <div className="flex flex-wrap items-center gap-3 rounded-lg border bg-card p-3 text-sm">
        <Label htmlFor="publish-policy">Publicações dos professores</Label>
        <NativeSelect id="publish-policy" className="w-64" value={policy.data ?? 'approval'} disabled={setPolicy.isPending} onChange={(event) => setPolicy.mutate(event.target.value)}>
          <option value="approval">Aguardam aprovação</option>
          <option value="immediate">São publicadas imediatamente</option>
        </NativeSelect>
      </div>

      {teachers.isError && <p role="alert">Não foi possível carregar os professores. Recarregue a página.</p>}

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_440px]">
        <ul className="overflow-hidden rounded-lg border bg-card">
          {teachers.data?.map((teacher) => (
            <li key={teacher.id} className={cn('border-b last:border-0', selection === teacher.id && 'bg-muted')}>
              <button type="button" onClick={() => setSelection(teacher.id)} className="flex w-full items-center gap-3 p-3 text-left text-sm hover:bg-muted/60">
                {Object.values(teacher.photo_urls)[0]
                  ? <img src={Object.values(teacher.photo_urls)[0]} alt="" loading="lazy" className="size-10 rounded-lg object-cover" />
                  : <span aria-hidden className="size-10 rounded-lg bg-muted" />}
                <span className="min-w-0 flex-1">
                  <span className={cn('block truncate font-medium', teacher.status === 'inactive' && 'text-muted-foreground line-through')}>{teacher.name}</span>
                  <span className="block truncate text-xs text-muted-foreground">{teacher.subjects.join(', ') || 'Sem matérias'} · {teacher.materials_count ?? 0} materiais</span>
                </span>
                {!teacher.show_in_cast && <span className="text-xs text-muted-foreground">Fora do Elenco</span>}
              </button>
            </li>
          ))}
        </ul>

        {selection !== null && (
          <TeacherEditor
            key={selection}
            teacher={selection === 'new' ? null : selected}
            onDone={() => {
              setSelection(null)
              return queryClient.invalidateQueries({ queryKey: KEY })
            }}
            onClose={() => setSelection(null)}
          />
        )}
      </div>
    </div>
  )
}

function TeacherEditor({ teacher, onDone, onClose }: { teacher: TeacherAdmin | null; onDone: () => void; onClose: () => void }) {
  const categories = useCategories()
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [account, setAccount] = useState({
    name: teacher?.name ?? '',
    email: '',
    publish_mode: teacher?.publish_mode ?? 'inherit',
    status: teacher?.status ?? 'active',
    show_in_cast: teacher?.show_in_cast ?? true,
    category_ids: teacher?.category_ids ?? [],
  })
  const [profile, setProfile] = useState<ProfileValues>({
    short_bio: teacher?.short_bio ?? '',
    bio: teacher?.bio ?? '',
    specialties: teacher?.specialties ?? [],
    photo: teacher?.photo ?? null,
    banner: teacher?.banner ?? null,
    socials: teacher?.socials ?? [],
  })

  const save = useMutation({
    mutationFn: () => {
      const { email, ...rest } = account
      const input = { ...rest, ...toProfileInput(profile) }
      return teacher ? adminTeachersApi.update(teacher.id, input) : adminTeachersApi.create({ ...input, email: email || null })
    },
    onSuccess: onDone,
  })
  const remove = useMutation({ mutationFn: () => adminTeachersApi.remove(teacher!.id), onSuccess: onDone })
  const error = save.error instanceof ApiError ? save.error : null
  const removeError = remove.error instanceof ApiError ? remove.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <div className="flex items-center justify-between">
        <h2 className="font-heading text-lg font-bold">{teacher ? 'Editar professor' : 'Novo professor'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}><X className="size-4" /></Button>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="teacher-name">Nome</Label>
        <Input id="teacher-name" value={account.name} aria-invalid={Boolean(error?.fieldError('name'))} onChange={(event) => setAccount({ ...account, name: event.target.value })} />
        {error?.fieldError('name') && <p className="text-sm text-destructive">{error.fieldError('name')}</p>}
      </div>

      {teacher ? (
        <p className="text-sm text-muted-foreground">Conta de acesso: {teacher.email ?? 'sem conta no painel'}</p>
      ) : (
        <div className="grid gap-2">
          <Label htmlFor="teacher-email">E-mail de acesso (opcional)</Label>
          <Input id="teacher-email" type="email" value={account.email} aria-invalid={Boolean(error?.fieldError('email'))} onChange={(event) => setAccount({ ...account, email: event.target.value })} />
          <p className="text-xs text-muted-foreground">O professor recebe um link para criar a própria senha.</p>
          {error?.fieldError('email') && <p className="text-sm text-destructive">{error.fieldError('email')}</p>}
        </div>
      )}

      <TeacherProfileFields value={profile} error={error} onChange={setProfile} />

      <fieldset className="grid gap-2">
        <legend className="mb-1 text-sm font-medium">Matérias autorizadas</legend>
        <p className="text-xs text-muted-foreground">Marque quantas precisar, de qualquer curso. O professor só publica nas matérias marcadas.</p>
        <div className="grid max-h-48 gap-1 overflow-y-auto rounded-lg border p-2 sm:grid-cols-2">
          {categories.data?.map((category) => (
            <Label key={category.id} className="flex items-center gap-2 font-normal">
              <Checkbox
                checked={account.category_ids.includes(category.id)}
                onCheckedChange={(value) => setAccount({
                  ...account,
                  category_ids: value === true ? [...account.category_ids, category.id] : account.category_ids.filter((id) => id !== category.id),
                })}
              />
              {courseLabel(category)}
            </Label>
          ))}
        </div>
      </fieldset>

      <div className="grid gap-3 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="teacher-publish">Publicação</Label>
          <NativeSelect id="teacher-publish" value={account.publish_mode} onChange={(event) => setAccount({ ...account, publish_mode: event.target.value as TeacherAdmin['publish_mode'] })}>
            <option value="inherit">Seguir regra geral</option>
            <option value="immediate">Publicar imediatamente</option>
            <option value="approval">Sempre aguardar aprovação</option>
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="teacher-status">Status</Label>
          <NativeSelect id="teacher-status" value={account.status} onChange={(event) => setAccount({ ...account, status: event.target.value as TeacherAdmin['status'] })}>
            <option value="active">Ativo</option>
            <option value="inactive">Inativo</option>
          </NativeSelect>
        </div>
      </div>

      <Label className="flex items-center gap-2 font-normal">
        <Checkbox checked={account.show_in_cast} onCheckedChange={(value) => setAccount({ ...account, show_in_cast: value === true })} />
        Mostrar no Elenco
      </Label>

      {removeError && <p role="alert" className="text-sm text-destructive">{removeError.message}</p>}
      {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}

      <div className="flex flex-wrap items-center justify-between gap-2">
        <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar'}</Button>
        {teacher && (
          <Button type="button" variant={confirmingDelete ? 'destructive' : 'ghost'} disabled={remove.isPending} onClick={() => (confirmingDelete ? remove.mutate() : setConfirmingDelete(true))}>
            {confirmingDelete ? 'Confirmar exclusão' : 'Excluir'}
          </Button>
        )}
      </div>
    </form>
  )
}
