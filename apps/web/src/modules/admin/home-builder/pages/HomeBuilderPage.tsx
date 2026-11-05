import { closestCenter, DndContext, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core'
import { arrayMove, SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { EyeOff, GripVertical, Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { ApiError } from '@/services/http'
import type { BuilderBanner, BuilderLayout, BuilderSection } from '@/types/api'
import { formatDate } from '@/utils/format'
import { homeBuilderApi } from '../api'
import { BannerEditor } from '../components/BannerEditor'
import { SectionEditor } from '../components/SectionEditor'
import { SOURCE_LABELS } from '../labels'

const SURFACES = [
  { value: 'home', label: 'Início' },
  { value: 'materials', label: 'Materiais' },
] as const

type Surface = (typeof SURFACES)[number]['value']
type Editing = { kind: 'section' | 'banner'; id: number | 'new' } | null

function periodLabel(item: { starts_at: string | null; ends_at: string | null }): string | null {
  if (!item.starts_at && !item.ends_at) return null
  return `${item.starts_at ? formatDate(item.starts_at) : 'agora'} até ${item.ends_at ? formatDate(item.ends_at) : 'sem fim'}`
}

function SectionRow({ section, selected, onEdit, onRemove }: { section: BuilderSection; selected: boolean; onEdit: () => void; onRemove: () => void }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: section.id })
  const details = [SOURCE_LABELS[section.source_type], periodLabel(section), section.group_ids.length > 0 && `${section.group_ids.length} turma(s)`].filter(Boolean).join(' · ')

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn('flex items-center gap-2 border-b bg-card px-2 py-2 last:border-0', selected && 'bg-muted', isDragging && 'relative z-10 shadow-lg')}
    >
      <button type="button" aria-label={`Reordenar ${section.title}`} className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-muted" {...attributes} {...listeners}>
        <GripVertical className="size-4" />
      </button>
      <button type="button" onClick={onEdit} className="min-w-0 flex-1 text-left text-sm">
        <span className={cn('block truncate font-medium', !section.is_active && 'text-muted-foreground line-through')}>{section.title}</span>
        <span className="block truncate text-xs text-muted-foreground">{details}</span>
      </button>
      {!section.is_active && <EyeOff aria-label="Inativa" className="size-4 text-muted-foreground" />}
      <Button variant="ghost" size="icon" aria-label={`Editar ${section.title}`} onClick={onEdit}><Pencil className="size-4" /></Button>
      <Button variant="ghost" size="icon" aria-label={`Excluir ${section.title}`} onClick={onRemove}><Trash2 className="size-4" /></Button>
    </li>
  )
}

function BannerRow({ banner, selected, onEdit, onRemove }: { banner: BuilderBanner; selected: boolean; onEdit: () => void; onRemove: () => void }) {
  const title = banner.title ?? banner.material?.title ?? 'Destaque sem título'

  return (
    <li className={cn('flex items-center gap-2 border-b px-3 py-2 last:border-0', selected && 'bg-muted')}>
      <button type="button" onClick={onEdit} className="min-w-0 flex-1 text-left text-sm">
        <span className={cn('block truncate font-medium', !banner.is_active && 'text-muted-foreground line-through')}>{title}</span>
        {periodLabel(banner) && <span className="block truncate text-xs text-muted-foreground">{periodLabel(banner)}</span>}
      </button>
      <Button variant="ghost" size="icon" aria-label={`Excluir ${title}`} onClick={onRemove}><Trash2 className="size-4" /></Button>
    </li>
  )
}

function Builder({ surface, layout, setNotice }: { surface: Surface; layout: BuilderLayout; setNotice: (notice: string | null) => void }) {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Editing>(null)
  const [confirmingPublish, setConfirmingPublish] = useState(false)
  const key = ['admin', 'home-draft', surface]
  const versionsKey = ['admin', 'home-versions', surface]
  const refresh = () => queryClient.invalidateQueries({ queryKey: key })
  const versions = useQuery({ queryKey: versionsKey, queryFn: () => homeBuilderApi.versions(surface) })

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const order = useMutation({ mutationFn: (ids: number[]) => homeBuilderApi.orderSections(layout.id, ids), onError: refresh })
  const removeSection = useMutation({ mutationFn: homeBuilderApi.removeSection, onSuccess: refresh })
  const removeBanner = useMutation({ mutationFn: homeBuilderApi.removeBanner, onSuccess: refresh })

  const publish = useMutation({
    mutationFn: () => homeBuilderApi.publish(layout.id),
    onSuccess: async () => {
      setConfirmingPublish(false)
      setEditing(null)
      setNotice('Publicado. Os alunos já veem esta versão.')
      await Promise.all([refresh(), queryClient.invalidateQueries({ queryKey: versionsKey }), queryClient.invalidateQueries({ queryKey: ['home'] })])
    },
  })

  const discard = useMutation({
    mutationFn: () => homeBuilderApi.discard(layout.id),
    onSuccess: () => {
      setEditing(null)
      setNotice('Alterações descartadas.')
      return refresh()
    },
  })

  const restore = useMutation({
    mutationFn: homeBuilderApi.restore,
    onSuccess: () => {
      setEditing(null)
      setNotice('Versão carregada no rascunho. Revise e publique.')
      return refresh()
    },
  })

  function onDragEnd({ active, over }: DragEndEvent) {
    if (!over || active.id === over.id) return

    const from = layout.sections.findIndex((section) => section.id === active.id)
    const to = layout.sections.findIndex((section) => section.id === over.id)
    const sections = arrayMove(layout.sections, from, to)

    queryClient.setQueryData(key, { ...layout, sections })
    order.mutate(sections.map((section) => section.id))
  }

  function saved() {
    setEditing(null)
    setNotice(null)
    return refresh()
  }

  const failure = [publish.error, discard.error, restore.error, order.error, removeSection.error, removeBanner.error].find((error) => error instanceof ApiError) as ApiError | undefined
  const section = editing?.kind === 'section' ? layout.sections.find((item) => item.id === editing.id) ?? null : null
  const banner = editing?.kind === 'banner' ? layout.banners.find((item) => item.id === editing.id) ?? null : null

  return (
    <>
      <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-card p-3">
        <p className="mr-auto text-sm text-muted-foreground">Rascunho: os alunos só veem as mudanças depois de publicar.</p>
        <Button variant="outline" render={<Link to={`/admin/home/${layout.id}/previa`} />}>Pré-visualizar</Button>
        <Button variant="ghost" disabled={discard.isPending} onClick={() => discard.mutate()}>Descartar</Button>
        <Button variant={confirmingPublish ? 'destructive' : 'default'} disabled={publish.isPending} onClick={() => (confirmingPublish ? publish.mutate() : setConfirmingPublish(true))}>
          {publish.isPending ? 'Publicando…' : confirmingPublish ? 'Confirmar publicação' : 'Publicar'}
        </Button>
      </div>

      {failure && <p role="alert" className="text-sm text-destructive">{failure.message}</p>}

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
        <div className="grid gap-6">
          <section className="grid gap-2" aria-label="Destaques do topo">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-heading text-lg font-bold">Hero</h2>
              <Button variant="outline" size="sm" onClick={() => setEditing({ kind: 'banner', id: 'new' })}><Plus className="size-4" />Destaque</Button>
            </div>
            {layout.banners.length === 0 && (
              <p className="rounded-lg border bg-card p-3 text-sm text-muted-foreground">Sem destaque configurado: o topo mostra o conteúdo marcado como destaque mais recente.</p>
            )}
            {layout.banners.length > 0 && (
              <ul className="overflow-hidden rounded-lg border bg-card">
                {layout.banners.map((item) => (
                  <BannerRow key={item.id} banner={item} selected={editing?.kind === 'banner' && editing.id === item.id} onEdit={() => setEditing({ kind: 'banner', id: item.id })} onRemove={() => removeBanner.mutate(item.id)} />
                ))}
              </ul>
            )}
          </section>

          <section className="grid gap-2" aria-label="Seções">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-heading text-lg font-bold">Seções</h2>
              <Button variant="outline" size="sm" onClick={() => setEditing({ kind: 'section', id: 'new' })}><Plus className="size-4" />Seção</Button>
            </div>
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
              <SortableContext items={layout.sections.map((item) => item.id)} strategy={verticalListSortingStrategy}>
                <ul className="overflow-hidden rounded-lg border bg-card">
                  {layout.sections.map((item) => (
                    <SectionRow key={item.id} section={item} selected={editing?.kind === 'section' && editing.id === item.id} onEdit={() => setEditing({ kind: 'section', id: item.id })} onRemove={() => removeSection.mutate(item.id)} />
                  ))}
                </ul>
              </SortableContext>
            </DndContext>
            {layout.sections.length === 0 && <p className="rounded-lg border bg-card p-3 text-sm text-muted-foreground">Nenhuma seção. Crie a primeira para montar esta página.</p>}
          </section>

          {(versions.data?.length ?? 0) > 0 && (
            <section className="grid gap-2" aria-label="Versões">
              <h2 className="font-heading text-lg font-bold">Versões publicadas</h2>
              <ul className="overflow-hidden rounded-lg border bg-card text-sm">
                {versions.data?.map((version) => (
                  <li key={version.id} className="flex items-center justify-between gap-3 border-b px-3 py-2 last:border-0">
                    <span className="tabular-nums">{formatDate(version.published_at)}{version.status === 'published' && ' · no ar'}</span>
                    {version.status === 'archived' && <Button variant="ghost" size="sm" disabled={restore.isPending} onClick={() => restore.mutate(version.id)}>Restaurar</Button>}
                  </li>
                ))}
              </ul>
            </section>
          )}
        </div>

        <div className="lg:sticky lg:top-4">
          {editing?.kind === 'section' && <SectionEditor key={String(editing.id)} layoutId={layout.id} section={section} onSaved={saved} onClose={() => setEditing(null)} />}
          {editing?.kind === 'banner' && <BannerEditor key={String(editing.id)} layoutId={layout.id} banner={banner} onSaved={saved} onClose={() => setEditing(null)} />}
          {!editing && <p className="rounded-lg border border-dashed p-5 text-sm text-muted-foreground">Selecione um item para editar, ou arraste as seções para mudar a ordem.</p>}
        </div>
      </div>
    </>
  )
}

export function HomeBuilderPage() {
  const [surface, setSurface] = useState<Surface>('home')
  // Fica fora do Builder: publicar gera um rascunho novo, e a tela é recriada em seguida.
  const [notice, setNotice] = useState<string | null>(null)
  const draft = useQuery({ queryKey: ['admin', 'home-draft', surface], queryFn: () => homeBuilderApi.draft(surface) })

  return (
    <div className="grid max-w-6xl gap-5">
      <header>
        <h1 className="font-heading text-3xl font-bold tracking-tight">Construtor da home</h1>
        <p className="mt-1 text-sm text-muted-foreground">Monte o que os alunos veem em Início e Materiais.</p>
      </header>

      <div role="tablist" aria-label="Página" className="flex gap-1">
        {SURFACES.map((item) => (
          <button
            key={item.value}
            type="button"
            role="tab"
            aria-selected={surface === item.value}
            onClick={() => {
              setSurface(item.value)
              setNotice(null)
            }}
            className={cn('rounded-md px-3 py-1.5 text-sm transition', surface === item.value ? 'bg-primary text-primary-foreground' : 'hover:bg-muted')}
          >
            {item.label}
          </button>
        ))}
      </div>

      {notice && <p role="status" className="rounded-lg bg-emerald-100 px-3 py-2 text-sm text-emerald-900">{notice}</p>}
      {draft.isPending && <p className="text-muted-foreground">Carregando…</p>}
      {draft.isError && <p role="alert">Não foi possível carregar o construtor. Recarregue a página para tentar novamente.</p>}
      {draft.data && <Builder key={`${surface}-${draft.data.id}`} surface={surface} layout={draft.data} setNotice={setNotice} />}
    </div>
  )
}
