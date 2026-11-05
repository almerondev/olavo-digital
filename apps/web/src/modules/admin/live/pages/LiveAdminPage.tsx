import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Radio, X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { AudienceField, fromIso, PeriodFields, toIso } from '@/modules/admin/home-builder/components/SharedFields'
import { coursesApi } from '@/modules/categories/api/courses'
import { flattenTree, useCategories, useTeacherOptions } from '@/modules/materials/hooks'
import { liveAdminApi, type ScheduleInput } from '@/modules/olavo-tv/api'
import { ApiError } from '@/services/http'
import type { LiveScheduleItem, ScheduleStatus } from '@/types/api'
import { formatDate } from '@/utils/format'

const KEY = ['admin', 'live']
const STATUS_LABELS: Record<ScheduleStatus, string> = { scheduled: 'Agendada', live: 'Ao vivo', ended: 'Encerrada', cancelled: 'Cancelada' }

function failureOf(...errors: unknown[]) {
  return errors.find((error) => error instanceof ApiError) as ApiError | undefined
}

interface LiveFormState {
  title: string
  description: string
  youtube_url: string
  teacher_id: number | null
  category_id: number | null
  course_id: number | null
  notify_on_start: boolean
  group_ids: number[]
}

function emptyForm(item?: LiveScheduleItem | null): LiveFormState {
  return {
    title: item?.title ?? '',
    description: item?.description ?? '',
    youtube_url: item?.youtube_url ?? '',
    teacher_id: item?.teacher?.id ?? null,
    category_id: item?.category?.id ?? null,
    course_id: item?.course?.id ?? null,
    notify_on_start: item?.notify_on_start ?? true,
    group_ids: item?.group_ids ?? [],
  }
}

function LiveFields({ form, error, onChange }: { form: LiveFormState; error: ApiError | null; onChange: (form: LiveFormState) => void }) {
  const teachers = useTeacherOptions()
  const categories = useCategories()
  const courses = useQuery({ queryKey: ['admin', 'courses'], queryFn: coursesApi.list })
  const subjects = flattenTree((categories.data ?? []).filter((category) => !form.course_id || category.course_id === form.course_id || category.course_id === null))

  return (
    <>
      <div className="grid gap-2">
        <Label htmlFor="live-url">Link da live no YouTube</Label>
        <Input id="live-url" type="url" placeholder="https://www.youtube.com/live/..." value={form.youtube_url} aria-invalid={Boolean(error?.fieldError('youtube_url'))} onChange={(e) => onChange({ ...form, youtube_url: e.target.value })} />
        {error?.fieldError('youtube_url') && <p className="text-sm text-destructive">{error.fieldError('youtube_url')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="live-title">Título</Label>
        <Input id="live-title" value={form.title} aria-invalid={Boolean(error?.fieldError('title'))} onChange={(e) => onChange({ ...form, title: e.target.value })} />
        {error?.fieldError('title') && <p className="text-sm text-destructive">{error.fieldError('title')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="live-description">Descrição</Label>
        <Textarea id="live-description" rows={2} value={form.description} onChange={(e) => onChange({ ...form, description: e.target.value })} />
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        <div className="grid gap-2">
          <Label htmlFor="live-course">Curso</Label>
          <NativeSelect id="live-course" value={form.course_id ?? ''} onChange={(e) => onChange({ ...form, course_id: e.target.value ? Number(e.target.value) : null, category_id: null })}>
            <option value="">Todos os cursos</option>
            {courses.data?.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="live-category">Matéria</Label>
          <NativeSelect id="live-category" value={form.category_id ?? ''} onChange={(e) => onChange({ ...form, category_id: e.target.value ? Number(e.target.value) : null })}>
            <option value="">Nenhuma</option>
            {subjects.map(({ category }) => <option key={category.id} value={category.id}>{category.name}{category.course_name && !form.course_id ? ` · ${category.course_name}` : ''}</option>)}
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="live-teacher">Professor</Label>
          <NativeSelect id="live-teacher" value={form.teacher_id ?? ''} onChange={(e) => onChange({ ...form, teacher_id: e.target.value ? Number(e.target.value) : null })}>
            <option value="">Nenhum</option>
            {teachers.data?.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
          </NativeSelect>
        </div>
      </div>
    </>
  )
}

function GoLivePanel() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<LiveFormState>(emptyForm())
  const streams = useQuery({ queryKey: [...KEY, 'streams'], queryFn: liveAdminApi.streams, refetchInterval: 15000 })
  const refresh = () => queryClient.invalidateQueries({ queryKey: KEY })
  const onAir = streams.data?.find((stream) => !stream.ended_at)

  const go = useMutation({
    mutationFn: () => {
      const now = new Date()
      const input: ScheduleInput = { ...form, starts_at: now.toISOString(), ends_at: new Date(now.getTime() + 3600000).toISOString() }
      return liveAdminApi.goLive(input)
    },
    onSuccess: () => {
      setForm(emptyForm())
      return refresh()
    },
  })
  const end = useMutation({ mutationFn: liveAdminApi.endStream, onSuccess: refresh })
  const error = go.error instanceof ApiError ? go.error : null
  const endError = end.error instanceof ApiError ? end.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    go.mutate()
  }

  if (onAir) {
    return (
      <section className="grid gap-3 rounded-lg border-2 border-live bg-card p-5" aria-label="Transmissão no ar">
        <p className="inline-flex items-center gap-2 text-sm font-bold text-live"><Radio className="size-4" />No ar agora</p>
        <p className="font-heading text-xl font-bold">{onAir.title}</p>
        <p className="text-sm text-muted-foreground">Desde {formatDate(onAir.started_at)}{onAir.course && ` · ${onAir.course}`}</p>
        {endError && <p role="alert" className="text-sm text-destructive">{endError.message}</p>}
        <div><Button variant="destructive" disabled={end.isPending} onClick={() => end.mutate(onAir.id)}>{end.isPending ? 'Encerrando…' : 'Encerrar transmissão'}</Button></div>
      </section>
    )
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <div>
        <h2 className="font-heading text-lg font-bold">Entrar ao vivo</h2>
        <p className="text-sm text-muted-foreground">Inicie a live no YouTube, cole o link aqui e publique para os alunos.</p>
      </div>
      <LiveFields form={form} error={error} onChange={setForm} />
      <AudienceField value={form.group_ids} onChange={(group_ids) => setForm({ ...form, group_ids })} />
      <Label className="flex items-center gap-2 font-normal">
        <Checkbox checked={form.notify_on_start} onCheckedChange={(value) => setForm({ ...form, notify_on_start: value === true })} />
        Notificar os alunos
      </Label>
      {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      <div><Button type="submit" disabled={go.isPending}>{go.isPending ? 'Iniciando…' : 'Entrar ao vivo'}</Button></div>
    </form>
  )
}

function ScheduleEditor({ item, onDone, onClose }: { item: LiveScheduleItem | null; onDone: () => void; onClose: () => void }) {
  const [form, setForm] = useState(emptyForm(item))
  const [period, setPeriod] = useState({ starts_at: fromIso(item?.starts_at ?? null), ends_at: fromIso(item?.ends_at ?? null) })

  const save = useMutation({
    mutationFn: () => {
      const input: ScheduleInput = { ...form, youtube_url: form.youtube_url || null, starts_at: toIso(period.starts_at), ends_at: toIso(period.ends_at) }
      return item ? liveAdminApi.updateSchedule(item.id, input) : liveAdminApi.createSchedule(input)
    },
    onSuccess: onDone,
  })
  const error = save.error instanceof ApiError ? save.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <div className="flex items-center justify-between">
        <h2 className="font-heading text-lg font-bold">{item ? 'Editar programação' : 'Nova programação'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}><X className="size-4" /></Button>
      </div>
      <LiveFields form={form} error={error} onChange={setForm} />
      <PeriodFields prefix="schedule" startsAt={period.starts_at} endsAt={period.ends_at} error={error?.fieldError('ends_at') ?? error?.fieldError('starts_at')} onChange={(field, value) => setPeriod({ ...period, [field]: value })} />
      <AudienceField value={form.group_ids} onChange={(group_ids) => setForm({ ...form, group_ids })} />
      <Label className="flex items-center gap-2 font-normal">
        <Checkbox checked={form.notify_on_start} onCheckedChange={(value) => setForm({ ...form, notify_on_start: value === true })} />
        Notificar os alunos ao iniciar
      </Label>
      {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      <div><Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar'}</Button></div>
    </form>
  )
}

function SchedulePanel() {
  const queryClient = useQueryClient()
  const [selection, setSelection] = useState<number | 'new' | null>(null)
  const settings = useQuery({ queryKey: [...KEY, 'settings'], queryFn: liveAdminApi.settings })
  const schedules = useQuery({ queryKey: [...KEY, 'schedules'], queryFn: liveAdminApi.schedules })
  const refresh = () => queryClient.invalidateQueries({ queryKey: KEY })
  const toggle = useMutation({ mutationFn: liveAdminApi.updateSettings, onSuccess: refresh })
  const cancel = useMutation({ mutationFn: liveAdminApi.cancelSchedule, onSuccess: refresh })
  const start = useMutation({ mutationFn: liveAdminApi.startSchedule, onSuccess: refresh })
  const selected = schedules.data?.find((item) => item.id === selection) ?? null
  const failure = failureOf(cancel.error, start.error, toggle.error)
  const enabled = settings.data?.schedule_enabled ?? true

  return (
    <section className="grid gap-3" aria-label="Programação">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-heading text-lg font-bold">Programação</h2>
        <Label className="flex items-center gap-2 font-normal">
          <Checkbox checked={enabled} disabled={toggle.isPending} onCheckedChange={(value) => toggle.mutate(value === true)} />
          Mostrar programação aos alunos
        </Label>
      </div>
      {!enabled && <p className="rounded-lg bg-muted px-3 py-2 text-sm text-muted-foreground">Programação oculta: os alunos veem apenas a transmissão ao vivo e as gravações.</p>}
      {failure && <p role="alert" className="text-sm text-destructive">{failure.message}</p>}
      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_440px]">
        <div className="grid gap-2">
          <div><Button size="sm" variant="outline" onClick={() => setSelection('new')}><Plus className="size-4" />Nova programação</Button></div>
          <ul className="overflow-hidden rounded-lg border bg-card">
            {schedules.data?.length === 0 && <li className="p-4 text-sm text-muted-foreground">Nada programado.</li>}
            {schedules.data?.map((item) => (
              <li key={item.id} className="flex flex-wrap items-center gap-2 border-b p-3 text-sm last:border-0">
                <button type="button" disabled={item.status !== 'scheduled'} onClick={() => setSelection(item.id)} className="min-w-0 flex-1 text-left">
                  <span className="block truncate font-medium">{item.title}</span>
                  <span className="block truncate text-xs text-muted-foreground tabular-nums">{formatDate(item.starts_at)}{item.course && ` · ${item.course.name}`}{item.teacher && ` · ${item.teacher.name}`}</span>
                </button>
                <span className={cn('rounded px-2 py-0.5 text-xs font-semibold', item.status === 'live' ? 'bg-live text-white' : 'bg-muted text-muted-foreground')}>{STATUS_LABELS[item.status]}</span>
                {item.status === 'scheduled' && (
                  <>
                    <Button variant="outline" size="sm" disabled={start.isPending || !item.youtube_url} title={item.youtube_url ? undefined : 'Edite e cole o link do YouTube'} onClick={() => start.mutate(item.id)}>Iniciar</Button>
                    <Button variant="ghost" size="sm" disabled={cancel.isPending} onClick={() => cancel.mutate(item.id)}>Cancelar</Button>
                  </>
                )}
              </li>
            ))}
          </ul>
        </div>
        {selection !== null && <ScheduleEditor key={selection} item={selected} onClose={() => setSelection(null)} onDone={() => { setSelection(null); return refresh() }} />}
      </div>
    </section>
  )
}

function HistoryPanel() {
  const streams = useQuery({ queryKey: [...KEY, 'streams'], queryFn: liveAdminApi.streams })
  const finished = streams.data?.filter((stream) => stream.ended_at) ?? []

  if (finished.length === 0) return null

  return (
    <section className="grid gap-2" aria-label="Transmissões realizadas">
      <h2 className="font-heading text-lg font-bold">Transmissões realizadas</h2>
      <ul className="overflow-hidden rounded-lg border bg-card text-sm">
        {finished.map((stream) => (
          <li key={stream.id} className="flex flex-wrap items-center gap-3 border-b p-3 last:border-0">
            <span className="min-w-0 flex-1">
              <span className="block truncate font-medium">{stream.title}</span>
              <span className="block text-xs text-muted-foreground tabular-nums">{formatDate(stream.started_at)}{stream.course && ` · ${stream.course}`}</span>
            </span>
            {stream.material && <Link to={`/admin/materiais/${stream.material.id}`} className="text-xs underline">{stream.material.status === 'published' ? 'Gravação publicada' : 'Completar e publicar gravação'}</Link>}
          </li>
        ))}
      </ul>
    </section>
  )
}

export function LiveAdminPage() {
  return (
    <div className="grid max-w-6xl gap-6">
      <header>
        <h1 className="font-heading text-3xl font-bold tracking-tight">Ao vivo agora</h1>
        <p className="mt-1 text-sm text-muted-foreground">Transmita pelo YouTube e controle quem assiste na plataforma.</p>
      </header>
      <GoLivePanel />
      <SchedulePanel />
      <HistoryPanel />
    </div>
  )
}
