import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { UploadField } from '@/components/media/UploadField'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { AudienceField, fromIso, toIso } from '@/modules/admin/home-builder/components/SharedFields'
import { coursesApi } from '@/modules/categories/api/courses'
import { ApiError } from '@/services/http'
import type { AdminAnnouncement, Upload } from '@/types/api'
import { formatDate } from '@/utils/format'
import { communicationAdminApi } from '../api'

const KEY = ['admin', 'announcements']
const MODE_LABELS: Record<string, string> = { banner: 'Banner', card: 'Card', section: 'Seção própria' }
const PRIORITY_LABELS = ['Normal', 'Importante', 'Urgente']

function Readers({ id }: { id: number }) {
  const reads = useQuery({ queryKey: [...KEY, id, 'reads'], queryFn: () => communicationAdminApi.reads(id) })

  if (!reads.data?.length) return <p className="text-sm text-muted-foreground">Ninguém confirmou ainda.</p>

  return (
    <ul className="max-h-40 overflow-auto rounded-lg border text-sm">
      {reads.data.map((read, index) => (
        <li key={index} className="flex justify-between gap-3 border-b px-3 py-1.5 last:border-0">
          <span className="truncate">{read.name ?? 'Conta removida'}</span>
          <span className="shrink-0 text-xs text-muted-foreground tabular-nums">{formatDate(read.read_at)}</span>
        </li>
      ))}
    </ul>
  )
}

function Editor({ item, onDone, onClose }: { item: AdminAnnouncement | null; onDone: () => void; onClose: () => void }) {
  const courses = useQuery({ queryKey: ['admin', 'courses'], queryFn: coursesApi.list })
  const [image, setImage] = useState<Upload | null>(item?.image ?? null)
  const [confirming, setConfirming] = useState(false)
  const [form, setForm] = useState({
    title: item?.title ?? '',
    body: item?.body ?? '',
    display_mode: item?.display_mode ?? 'card',
    priority: item?.priority ?? 0,
    course_id: item?.course?.id ?? null,
    published_at: fromIso(item?.published_at ?? new Date().toISOString()),
    expires_at: fromIso(item?.expires_at ?? null),
    is_pinned: item?.is_pinned ?? false,
    requires_ack: item?.requires_ack ?? false,
    send_notification: item?.send_notification ?? false,
    group_ids: item?.group_ids ?? [],
  })

  const save = useMutation({
    mutationFn: () => {
      const input = { ...form, image_upload_id: image?.id ?? null, published_at: toIso(form.published_at), expires_at: toIso(form.expires_at) }
      return item ? communicationAdminApi.updateAnnouncement(item.id, input) : communicationAdminApi.createAnnouncement(input)
    },
    onSuccess: onDone,
  })
  const remove = useMutation({ mutationFn: () => communicationAdminApi.deleteAnnouncement(item!.id), onSuccess: onDone })
  const error = save.error instanceof ApiError ? save.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <div className="flex items-center justify-between">
        <h2 className="font-heading text-lg font-bold">{item ? 'Editar aviso' : 'Novo aviso'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}><X className="size-4" /></Button>
      </div>
      <div className="grid gap-2">
        <Label htmlFor="ann-title">Título</Label>
        <Input id="ann-title" value={form.title} aria-invalid={Boolean(error?.fieldError('title'))} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        {error?.fieldError('title') && <p className="text-sm text-destructive">{error.fieldError('title')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="ann-body">Descrição</Label>
        <Textarea id="ann-body" rows={4} value={form.body} aria-invalid={Boolean(error?.fieldError('body'))} onChange={(e) => setForm({ ...form, body: e.target.value })} />
        {error?.fieldError('body') && <p className="text-sm text-destructive">{error.fieldError('body')}</p>}
      </div>
      <UploadField label="Imagem (opcional)" purpose="image" accept=".jpg,.jpeg,.png,.webp" value={image} onChange={setImage} />
      <div className="grid gap-3 sm:grid-cols-3">
        <div className="grid gap-2">
          <Label htmlFor="ann-mode">Aparece como</Label>
          <NativeSelect id="ann-mode" value={form.display_mode} onChange={(e) => setForm({ ...form, display_mode: e.target.value as typeof form.display_mode })}>
            {Object.entries(MODE_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="ann-priority">Prioridade</Label>
          <NativeSelect id="ann-priority" value={form.priority} onChange={(e) => setForm({ ...form, priority: Number(e.target.value) })}>
            {PRIORITY_LABELS.map((label, value) => <option key={label} value={value}>{label}</option>)}
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="ann-course">Curso</Label>
          <NativeSelect id="ann-course" value={form.course_id ?? ''} onChange={(e) => setForm({ ...form, course_id: e.target.value ? Number(e.target.value) : null })}>
            <option value="">Todos os cursos</option>
            {courses.data?.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}
          </NativeSelect>
        </div>
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="ann-start">Data de publicação</Label>
          <Input id="ann-start" type="datetime-local" value={form.published_at} aria-invalid={Boolean(error?.fieldError('published_at'))} onChange={(e) => setForm({ ...form, published_at: e.target.value })} />
          {error?.fieldError('published_at') && <p className="text-sm text-destructive">{error.fieldError('published_at')}</p>}
        </div>
        <div className="grid gap-2">
          <Label htmlFor="ann-end">Data de expiração</Label>
          <Input id="ann-end" type="datetime-local" value={form.expires_at} aria-invalid={Boolean(error?.fieldError('expires_at'))} onChange={(e) => setForm({ ...form, expires_at: e.target.value })} />
          {error?.fieldError('expires_at') && <p className="text-sm text-destructive">{error.fieldError('expires_at')}</p>}
        </div>
      </div>
      <AudienceField value={form.group_ids} onChange={(group_ids) => setForm({ ...form, group_ids })} />
      <div className="grid gap-2">
        <Label className="flex items-center gap-2 font-normal"><Checkbox checked={form.is_pinned} onCheckedChange={(value) => setForm({ ...form, is_pinned: value === true })} />Fixar na home</Label>
        <Label className="flex items-center gap-2 font-normal"><Checkbox checked={form.requires_ack} onCheckedChange={(value) => setForm({ ...form, requires_ack: value === true })} />Exigir confirmação de leitura</Label>
        <Label className="flex items-center gap-2 font-normal"><Checkbox checked={form.send_notification} disabled={Boolean(item?.notified_at)} onCheckedChange={(value) => setForm({ ...form, send_notification: value === true })} />{item?.notified_at ? 'Notificação já enviada' : 'Enviar notificação ao publicar'}</Label>
      </div>
      {item?.requires_ack && <div className="grid gap-2"><p className="text-sm font-medium">Confirmações</p><Readers id={item.id} /></div>}
      {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar'}</Button>
        {item && <Button type="button" variant={confirming ? 'destructive' : 'ghost'} disabled={remove.isPending} onClick={() => (confirming ? remove.mutate() : setConfirming(true))}>{confirming ? 'Confirmar exclusão' : 'Excluir'}</Button>}
      </div>
    </form>
  )
}

export function AdminAnnouncementsPage() {
  const queryClient = useQueryClient()
  const [selection, setSelection] = useState<number | 'new' | null>(null)
  const list = useQuery({ queryKey: KEY, queryFn: communicationAdminApi.announcements })
  const selected = list.data?.find((item) => item.id === selection) ?? null
  const now = new Date().toISOString()

  return (
    <div className="grid max-w-6xl gap-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Avisos</h1>
          <p className="mt-1 text-sm text-muted-foreground">Comunicados exibidos na home dos alunos.</p>
        </div>
        <Button onClick={() => setSelection('new')}><Plus className="size-4" />Novo aviso</Button>
      </header>
      {list.isError && <p role="alert">Não foi possível carregar os avisos. Recarregue a página.</p>}
      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_480px]">
        <ul className="overflow-hidden rounded-lg border bg-card">
          {list.data?.length === 0 && <li className="p-4 text-sm text-muted-foreground">Nenhum aviso criado.</li>}
          {list.data?.map((item) => {
            const expired = item.expires_at !== null && item.expires_at < now
            const scheduled = item.published_at > now

            return (
              <li key={item.id} className={cn('border-b last:border-0', selection === item.id && 'bg-muted')}>
                <button type="button" onClick={() => setSelection(item.id)} className="w-full p-3 text-left text-sm hover:bg-muted/60">
                  <span className="block truncate font-medium">{item.title}</span>
                  <span className="block truncate text-xs text-muted-foreground tabular-nums">
                    {expired ? 'Expirado' : scheduled ? 'Agendado' : 'No ar'} · {MODE_LABELS[item.display_mode]} · {item.course?.name ?? 'Todos os cursos'} · {formatDate(item.published_at)}
                    {item.requires_ack && ` · ${item.reads_count} ${item.reads_count === 1 ? 'confirmação' : 'confirmações'}`}
                  </span>
                </button>
              </li>
            )
          })}
        </ul>
        {selection !== null && <Editor key={selection} item={selected} onClose={() => setSelection(null)} onDone={() => { setSelection(null); return queryClient.invalidateQueries({ queryKey: KEY }) }} />}
      </div>
    </div>
  )
}
