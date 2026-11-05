import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, X } from 'lucide-react'
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
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import { useCategories, useTeacherOptions } from '@/modules/materials/hooks'
import { liveAdminApi } from '@/modules/olavo-tv/api'
import { ApiError } from '@/services/http'
import type { LiveChannelItem, LiveScheduleItem, ScheduleStatus } from '@/types/api'
import { formatDate } from '@/utils/format'

const KEY = ['admin', 'live']
const TABS = [['schedule', 'Programação'], ['channels', 'Canais'], ['streams', 'Transmissões']] as const
type Tab = (typeof TABS)[number][0]

const STATUS_LABELS: Record<ScheduleStatus, string> = { scheduled: 'Agendada', live: 'Ao vivo', ended: 'Encerrada', cancelled: 'Cancelada' }

function failureOf(...errors: unknown[]) {
  return errors.find((error) => error instanceof ApiError) as ApiError | undefined
}

function ScheduleEditor({ item, channels, onDone, onClose }: { item: LiveScheduleItem | null; channels: LiveChannelItem[]; onDone: () => void; onClose: () => void }) {
  const teachers = useTeacherOptions()
  const categories = useCategories()
  const [form, setForm] = useState({
    live_channel_id: item?.live_channel_id ?? channels[0]?.id ?? null,
    title: item?.title ?? '',
    description: item?.description ?? '',
    teacher_id: item?.teacher?.id ?? null as number | null,
    category_id: item?.category?.id ?? null as number | null,
    starts_at: fromIso(item?.starts_at ?? null),
    ends_at: fromIso(item?.ends_at ?? null),
    notify_on_start: item?.notify_on_start ?? true,
    group_ids: item?.group_ids ?? [],
  })

  const save = useMutation({
    mutationFn: () => {
      const input = { ...form, starts_at: toIso(form.starts_at), ends_at: toIso(form.ends_at) }
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
        <h2 className="font-heading text-lg font-bold">{item ? 'Editar transmissão' : 'Nova transmissão'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}><X className="size-4" /></Button>
      </div>
      <div className="grid gap-2">
        <Label htmlFor="live-title">Título</Label>
        <Input id="live-title" value={form.title} aria-invalid={Boolean(error?.fieldError('title'))} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        {error?.fieldError('title') && <p className="text-sm text-destructive">{error.fieldError('title')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="live-description">Descrição</Label>
        <Textarea id="live-description" rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        <div className="grid gap-2">
          <Label htmlFor="live-channel">Canal</Label>
          <NativeSelect id="live-channel" value={form.live_channel_id ?? ''} onChange={(e) => setForm({ ...form, live_channel_id: Number(e.target.value) })}>
            {channels.map((channel) => <option key={channel.id} value={channel.id}>{channel.name}</option>)}
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="live-teacher">Professor</Label>
          <NativeSelect id="live-teacher" value={form.teacher_id ?? ''} onChange={(e) => setForm({ ...form, teacher_id: e.target.value ? Number(e.target.value) : null })}>
            <option value="">Nenhum</option>
            {teachers.data?.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="live-category">Matéria</Label>
          <NativeSelect id="live-category" value={form.category_id ?? ''} onChange={(e) => setForm({ ...form, category_id: e.target.value ? Number(e.target.value) : null })}>
            <option value="">Nenhuma</option>
            {categories.data?.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
          </NativeSelect>
        </div>
      </div>
      <PeriodFields prefix="live" startsAt={form.starts_at} endsAt={form.ends_at} error={error?.fieldError('ends_at') ?? error?.fieldError('starts_at')} onChange={(field, value) => setForm({ ...form, [field]: value })} />
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

function ScheduleTab({ channels }: { channels: LiveChannelItem[] }) {
  const queryClient = useQueryClient()
  const [selection, setSelection] = useState<number | 'new' | null>(null)
  const schedules = useQuery({ queryKey: [...KEY, 'schedules'], queryFn: liveAdminApi.schedules })
  const refresh = () => queryClient.invalidateQueries({ queryKey: KEY })
  const cancel = useMutation({ mutationFn: liveAdminApi.cancelSchedule, onSuccess: refresh })
  const selected = schedules.data?.find((item) => item.id === selection) ?? null
  const failure = failureOf(cancel.error)

  return (
    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_440px]">
      <div className="grid gap-2">
        <div><Button size="sm" onClick={() => setSelection('new')}><Plus className="size-4" />Nova transmissão</Button></div>
        {failure && <p role="alert" className="text-sm text-destructive">{failure.message}</p>}
        <ul className="overflow-hidden rounded-lg border bg-card">
          {schedules.data?.length === 0 && <li className="p-4 text-sm text-muted-foreground">Nenhuma transmissão cadastrada.</li>}
          {schedules.data?.map((item) => (
            <li key={item.id} className="flex items-center gap-2 border-b p-3 text-sm last:border-0">
              <button type="button" disabled={item.status !== 'scheduled'} onClick={() => setSelection(item.id)} className="min-w-0 flex-1 text-left">
                <span className="block truncate font-medium">{item.title}</span>
                <span className="block truncate text-xs text-muted-foreground tabular-nums">{formatDate(item.starts_at)}{item.teacher && ` · ${item.teacher.name}`}</span>
              </button>
              <span className={cn('rounded px-2 py-0.5 text-xs font-semibold', item.status === 'live' ? 'bg-live text-white' : 'bg-muted text-muted-foreground')}>{STATUS_LABELS[item.status]}</span>
              {item.status === 'scheduled' && <Button variant="ghost" size="sm" disabled={cancel.isPending} onClick={() => cancel.mutate(item.id)}>Cancelar</Button>}
            </li>
          ))}
        </ul>
      </div>
      {selection !== null && (
        <ScheduleEditor key={selection} item={selected} channels={channels} onClose={() => setSelection(null)} onDone={() => { setSelection(null); return refresh() }} />
      )}
    </div>
  )
}

function ChannelCard({ channel, simulated }: { channel: LiveChannelItem; simulated: boolean }) {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()
  const [key, setKey] = useState<string | null>(null)
  const [session, setSession] = useState<string | undefined>(undefined)
  const refresh = () => queryClient.invalidateQueries({ queryKey: KEY })
  const canSeeKey = user?.permissions.includes('live.keys.view')

  const show = useMutation({ mutationFn: () => liveAdminApi.key(channel.id), onSuccess: setKey })
  const rotate = useMutation({ mutationFn: () => liveAdminApi.rotateKey(channel.id), onSuccess: setKey })
  const simulate = useMutation({
    mutationFn: async (type: string) => {
      const id = session ?? crypto.randomUUID()
      setSession(id)
      await liveAdminApi.simulate(channel.id, type, id)
    },
    onSuccess: refresh,
  })
  const failure = failureOf(show.error, rotate.error, simulate.error)

  return (
    <li className="grid gap-3 rounded-lg border bg-card p-4 text-sm">
      <div className="flex items-center justify-between gap-3">
        <p className="font-heading text-base font-bold">{channel.name}</p>
        {channel.on_air && <span className="rounded bg-live px-2 py-0.5 text-xs font-semibold text-white">No ar</span>}
      </div>
      <dl className="grid gap-1 text-xs">
        <div><dt className="inline text-muted-foreground">RTMPS: </dt><dd className="inline break-all">{channel.ingest_rtmps_url ?? '—'}</dd></div>
        <div><dt className="inline text-muted-foreground">SRT: </dt><dd className="inline break-all">{channel.ingest_srt_url ?? '—'}</dd></div>
        {key && <div><dt className="inline text-muted-foreground">Chave: </dt><dd className="inline break-all font-mono">{key}</dd></div>}
      </dl>
      {canSeeKey && (
        <div className="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" disabled={show.isPending} onClick={() => (key ? setKey(null) : show.mutate())}>{key ? 'Ocultar chave' : 'Mostrar chave'}</Button>
          <Button variant="ghost" size="sm" disabled={rotate.isPending} onClick={() => rotate.mutate()}>Trocar chave</Button>
        </div>
      )}
      {simulated && (
        <div className="grid gap-2 rounded-lg bg-muted/60 p-3">
          <p className="text-xs text-muted-foreground">Provedor simulado: estes botões geram os eventos que o encoder enviaria.</p>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" disabled={simulate.isPending} onClick={() => simulate.mutate('connected')}>Iniciar sinal</Button>
            <Button variant="outline" size="sm" disabled={simulate.isPending} onClick={() => simulate.mutate('disconnected')}>Derrubar sinal</Button>
            <Button variant="outline" size="sm" disabled={simulate.isPending} onClick={() => simulate.mutate('recording_ready')}>Gravação pronta</Button>
          </div>
        </div>
      )}
      {failure && <p role="alert" className="text-destructive">{failure.message}</p>}
    </li>
  )
}

function ChannelsTab({ channels, simulated }: { channels: LiveChannelItem[]; simulated: boolean }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const create = useMutation({
    mutationFn: () => liveAdminApi.createChannel(name),
    onSuccess: () => {
      setName('')
      return queryClient.invalidateQueries({ queryKey: KEY })
    },
  })
  const error = create.error instanceof ApiError ? create.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    create.mutate()
  }

  return (
    <div className="grid max-w-3xl gap-4">
      <ul className="grid gap-3">{channels.map((channel) => <ChannelCard key={channel.id} channel={channel} simulated={simulated} />)}</ul>
      <form onSubmit={submit} className="flex gap-2">
        <Input aria-label="Nome do novo canal" placeholder="Novo canal" value={name} onChange={(event) => setName(event.target.value)} />
        <Button type="submit" variant="outline" disabled={create.isPending}>Criar canal</Button>
      </form>
      {error && <p role="alert" className="text-sm text-destructive">{error.fieldError('name') ?? error.message}</p>}
    </div>
  )
}

function StreamsTab() {
  const queryClient = useQueryClient()
  const streams = useQuery({ queryKey: [...KEY, 'streams'], queryFn: liveAdminApi.streams, refetchInterval: 15000 })
  const end = useMutation({ mutationFn: liveAdminApi.endStream, onSuccess: () => queryClient.invalidateQueries({ queryKey: KEY }) })
  const failure = failureOf(end.error)

  return (
    <div className="grid max-w-4xl gap-2">
      {failure && <p role="alert" className="text-sm text-destructive">{failure.message}</p>}
      <ul className="overflow-hidden rounded-lg border bg-card text-sm">
        {streams.data?.length === 0 && <li className="p-4 text-muted-foreground">Nenhuma transmissão realizada.</li>}
        {streams.data?.map((stream) => (
          <li key={stream.id} className="flex flex-wrap items-center gap-3 border-b p-3 last:border-0">
            <span className="min-w-0 flex-1">
              <span className="block truncate font-medium">{stream.title}</span>
              <span className="block text-xs text-muted-foreground tabular-nums">{formatDate(stream.started_at)} até {stream.ended_at ? formatDate(stream.ended_at) : 'agora'} · {stream.channel}</span>
            </span>
            {stream.reconnecting && <span className="text-xs text-amber-700">Sem sinal</span>}
            {!stream.ended_at && <Button variant="outline" size="sm" disabled={end.isPending} onClick={() => end.mutate(stream.id)}>Encerrar</Button>}
            {stream.material
              ? <Link to={`/admin/materiais/${stream.material.id}`} className="text-xs underline">{stream.material.status === 'published' ? 'Gravação publicada' : 'Completar gravação'}</Link>
              : stream.ended_at && <span className="text-xs text-muted-foreground">Aguardando gravação</span>}
          </li>
        ))}
      </ul>
    </div>
  )
}

export function LiveAdminPage() {
  const [tab, setTab] = useState<Tab>('schedule')
  const channels = useQuery({ queryKey: [...KEY, 'channels'], queryFn: liveAdminApi.channels })

  return (
    <div className="grid max-w-6xl gap-5">
      <header>
        <h1 className="font-heading text-3xl font-bold tracking-tight">Olavo TV</h1>
        <p className="mt-1 text-sm text-muted-foreground">Programação, canais e gravações das transmissões.</p>
      </header>
      <div role="tablist" aria-label="Seções" className="flex gap-1">
        {TABS.map(([value, label]) => (
          <button key={value} type="button" role="tab" aria-selected={tab === value} onClick={() => setTab(value)} className={cn('rounded-md px-3 py-1.5 text-sm transition', tab === value ? 'bg-primary text-primary-foreground' : 'hover:bg-muted')}>
            {label}
          </button>
        ))}
      </div>
      {channels.isError && <p role="alert">Não foi possível carregar a Olavo TV. Recarregue a página.</p>}
      {channels.data && tab === 'schedule' && <ScheduleTab channels={channels.data.data} />}
      {channels.data && tab === 'channels' && <ChannelsTab channels={channels.data.data} simulated={channels.data.meta.simulated} />}
      {tab === 'streams' && <StreamsTab />}
    </div>
  )
}
