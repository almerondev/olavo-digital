import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { X } from 'lucide-react'
import { useEffect, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'
import { coursesApi } from '@/modules/categories/api/courses'
import { useGroupOptions } from '@/modules/materials/hooks'
import { ApiError } from '@/services/http'
import { formatDate } from '@/utils/format'
import { communicationAdminApi } from '../api'

const KEY = ['admin', 'notifications']
const TYPE_LABELS: Record<string, string> = { manual: 'Manual', material: 'Material', video: 'Vídeo', live: 'Ao vivo', announcement: 'Aviso' }

function StudentPicker({ value, onChange }: { value: { id: number; name: string }[]; onChange: (value: { id: number; name: string }[]) => void }) {
  const [term, setTerm] = useState('')
  const [query, setQuery] = useState('')
  const found = useQuery({ queryKey: [...KEY, 'recipients', query], queryFn: () => communicationAdminApi.recipients(query), enabled: query.length >= 2 })

  useEffect(() => {
    const timer = setTimeout(() => setQuery(term.trim()), 300)
    return () => clearTimeout(timer)
  }, [term])

  return (
    <div className="grid gap-2">
      <Label htmlFor="note-student">Alunos</Label>
      {value.length > 0 && (
        <ul className="flex flex-wrap gap-1.5">
          {value.map((student) => (
            <li key={student.id} className="inline-flex items-center gap-1 rounded bg-muted px-2 py-0.5 text-sm">
              {student.name}
              <button type="button" aria-label={`Remover ${student.name}`} onClick={() => onChange(value.filter((item) => item.id !== student.id))}><X className="size-3" /></button>
            </li>
          ))}
        </ul>
      )}
      <Input id="note-student" placeholder="Busque por nome ou e-mail" value={term} onChange={(e) => setTerm(e.target.value)} />
      {found.data && found.data.length > 0 && term.trim().length >= 2 && (
        <ul className="overflow-hidden rounded-lg border text-sm">
          {found.data.map((student) => (
            <li key={student.id} className="border-b last:border-0">
              <button
                type="button"
                className="w-full px-3 py-1.5 text-left hover:bg-muted"
                onClick={() => {
                  if (!value.some((item) => item.id === student.id)) onChange([...value, { id: student.id, name: student.name }])
                  setTerm('')
                }}
              >
                {student.name} <span className="text-muted-foreground">{student.email}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

export function AdminNotificationsPage() {
  const queryClient = useQueryClient()
  const history = useQuery({ queryKey: KEY, queryFn: communicationAdminApi.notifications, refetchInterval: 15000 })
  const courses = useQuery({ queryKey: ['admin', 'courses'], queryFn: coursesApi.list })
  const groups = useGroupOptions()
  const empty = { title: '', body: '', url: '', target: 'all', course: '', group: '' }
  const [form, setForm] = useState(empty)
  const [students, setStudents] = useState<{ id: number; name: string }[]>([])

  const send = useMutation({
    mutationFn: () => communicationAdminApi.sendNotification({
      title: form.title,
      body: form.body,
      url: form.url || null,
      target: form.target,
      ids: form.target === 'course' && form.course ? [Number(form.course)]
        : form.target === 'group' && form.group ? [Number(form.group)]
          : form.target === 'user' ? students.map((student) => student.id) : [],
    }),
    onSuccess: () => {
      setForm(empty)
      setStudents([])
      return queryClient.invalidateQueries({ queryKey: KEY })
    },
  })
  const error = send.error instanceof ApiError ? send.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    send.mutate()
  }

  return (
    <div className="grid max-w-6xl gap-5">
      <header>
        <h1 className="font-heading text-3xl font-bold tracking-tight">Notificações</h1>
        <p className="mt-1 text-sm text-muted-foreground">Materiais, vídeos, avisos e transmissões já notificam sozinhos. Aqui você envia uma mensagem avulsa.</p>
      </header>
      <div className="grid items-start gap-6 lg:grid-cols-[440px_minmax(0,1fr)]">
        <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
          <div className="grid gap-2">
            <Label htmlFor="note-title">Título</Label>
            <Input id="note-title" value={form.title} aria-invalid={Boolean(error?.fieldError('title'))} onChange={(e) => setForm({ ...form, title: e.target.value })} />
            {error?.fieldError('title') && <p className="text-sm text-destructive">{error.fieldError('title')}</p>}
          </div>
          <div className="grid gap-2">
            <Label htmlFor="note-body">Mensagem</Label>
            <Textarea id="note-body" rows={3} value={form.body} aria-invalid={Boolean(error?.fieldError('body'))} onChange={(e) => setForm({ ...form, body: e.target.value })} />
            {error?.fieldError('body') && <p className="text-sm text-destructive">{error.fieldError('body')}</p>}
          </div>
          <div className="grid gap-2">
            <Label htmlFor="note-url">Link de destino (opcional)</Label>
            <Input id="note-url" placeholder="/ao-vivo ou https://..." value={form.url} aria-invalid={Boolean(error?.fieldError('url'))} onChange={(e) => setForm({ ...form, url: e.target.value })} />
            {error?.fieldError('url') && <p className="text-sm text-destructive">Use um caminho da plataforma, como /materiais, ou um link https.</p>}
          </div>
          <div className="grid gap-2">
            <Label htmlFor="note-target">Enviar para</Label>
            <NativeSelect id="note-target" value={form.target} onChange={(e) => setForm({ ...form, target: e.target.value })}>
              <option value="all">Todos os alunos</option>
              <option value="course">Um curso</option>
              <option value="group">Uma turma ou grupo</option>
              <option value="user">Alunos específicos</option>
            </NativeSelect>
          </div>
          {form.target === 'course' && (
            <div className="grid gap-2">
              <Label htmlFor="note-course">Curso</Label>
              <NativeSelect id="note-course" value={form.course} aria-invalid={Boolean(error?.fieldError('ids'))} onChange={(e) => setForm({ ...form, course: e.target.value })}>
                <option value="">Escolha</option>
                {courses.data?.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}
              </NativeSelect>
            </div>
          )}
          {form.target === 'group' && (
            <div className="grid gap-2">
              <Label htmlFor="note-group">Turma ou grupo</Label>
              <NativeSelect id="note-group" value={form.group} aria-invalid={Boolean(error?.fieldError('ids'))} onChange={(e) => setForm({ ...form, group: e.target.value })}>
                <option value="">Escolha</option>
                {groups.data?.map((group) => <option key={group.id} value={group.id}>{group.name}</option>)}
              </NativeSelect>
            </div>
          )}
          {form.target === 'user' && <StudentPicker value={students} onChange={setStudents} />}
          {error?.fieldError('ids') && <p className="text-sm text-destructive">Escolha quem vai receber.</p>}
          {send.isSuccess && <p role="status" className="text-sm text-muted-foreground">Notificação enviada.</p>}
          {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
          <div><Button type="submit" disabled={send.isPending}>{send.isPending ? 'Enviando…' : 'Enviar notificação'}</Button></div>
        </form>

        <section aria-label="Enviadas" className="grid gap-2">
          <h2 className="font-heading text-lg font-bold">Enviadas</h2>
          {history.isError && <p role="alert">Não foi possível carregar o histórico.</p>}
          <ul className="overflow-hidden rounded-lg border bg-card text-sm">
            {history.data?.length === 0 && <li className="p-4 text-muted-foreground">Nada enviado ainda.</li>}
            {history.data?.map((item) => (
              <li key={item.id} className="border-b p-3 last:border-0">
                <p className="truncate font-medium">{item.title}</p>
                <p className="truncate text-muted-foreground">{item.body}</p>
                <p className="text-xs text-muted-foreground tabular-nums">
                  {TYPE_LABELS[item.type] ?? item.type} · {formatDate(item.created_at)} · {item.read_count} de {item.recipients_count} {item.recipients_count === 1 ? 'leu' : 'leram'}{item.sender && ` · ${item.sender}`}
                </p>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </div>
  )
}
