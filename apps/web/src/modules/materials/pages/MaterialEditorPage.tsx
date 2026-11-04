import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { UploadField } from '@/components/media/UploadField'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'
import { ApiError } from '@/services/http'
import type { Material, MaterialType, Upload } from '@/types/api'
import { toLocalInput } from '@/utils/format'
import { materialsApi, type MaterialAction, type MaterialInput } from '../api/materials'
import { StatusBadge } from '../components/StatusBadge'
import { flattenTree, useCategories, useGroupOptions, useTeacherOptions } from '../hooks'
import { TYPE_ACCEPT, TYPE_LABELS, VISIBILITY_LABELS } from '../labels'

interface FormState extends Omit<MaterialInput, 'cover_upload_id' | 'file_upload_id' | 'publish_at'> {
  cover: Upload | null
  file: Upload | null
  publish_at: string
}

const EMPTY: FormState = {
  title: '',
  description: '',
  type: 'pdf',
  visibility: 'all',
  teacher_id: null,
  cover: null,
  file: null,
  external_url: '',
  publish_at: '',
  allow_download: false,
  is_featured: false,
  category_ids: [],
  primary_category_id: null,
  tags: [],
  collaborator_ids: [],
  group_ids: [],
}

function fromMaterial(material: Material): FormState {
  return {
    title: material.title,
    description: material.description ?? '',
    type: material.type,
    visibility: material.visibility,
    teacher_id: material.teacher?.id ?? null,
    cover: material.cover,
    file: material.file,
    external_url: material.external_url ?? '',
    publish_at: toLocalInput(material.publish_at),
    allow_download: material.allow_download,
    is_featured: material.is_featured,
    category_ids: material.categories.map((category) => category.id),
    primary_category_id: material.categories.find((category) => category.is_primary)?.id ?? null,
    tags: material.tags,
    collaborator_ids: material.collaborator_ids ?? [],
    group_ids: material.group_ids ?? [],
  }
}

function toInput({ cover, file, publish_at, external_url, ...rest }: FormState): MaterialInput {
  return {
    ...rest,
    cover_upload_id: cover?.id ?? null,
    file_upload_id: file?.id ?? null,
    external_url: external_url || null,
    publish_at: publish_at ? new Date(publish_at).toISOString() : null,
  }
}

const URL_TYPES: MaterialType[] = ['external_video', 'link']

export function MaterialEditorPage() {
  const id = Number(useParams().id) || null
  const material = useQuery({ queryKey: ['admin', 'material', id], queryFn: () => materialsApi.get(id!), enabled: id !== null })

  if (id === null) return <MaterialForm key="new" material={null} />
  if (material.isPending) return <p className="text-muted-foreground">Carregando material…</p>
  if (material.isError) return <p role="alert">Não encontramos este material.</p>

  return <MaterialForm key={material.data.id} material={material.data} />
}

function MaterialForm({ material }: { material: Material | null }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const categories = useCategories()
  const teachers = useTeacherOptions()
  const groups = useGroupOptions()
  const [form, setForm] = useState<FormState>(material ? fromMaterial(material) : EMPTY)
  const [tagDraft, setTagDraft] = useState('')
  const [rejectNote, setRejectNote] = useState('')
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [notice, setNotice] = useState<string | null>(null)

  // Publicar salva antes: se a validação falhar, a nova tentativa atualiza o mesmo rascunho.
  const savedId = useRef(material?.id ?? null)

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => setForm((current) => ({ ...current, [key]: value }))

  async function refresh(saved: Material) {
    await queryClient.invalidateQueries({ queryKey: ['admin', 'materials'] })
    queryClient.setQueryData(['admin', 'material', saved.id], saved)
    if (!material) navigate(`/admin/materiais/${saved.id}`, { replace: true })
  }

  async function persist() {
    const saved = savedId.current
      ? await materialsApi.update(savedId.current, toInput(form))
      : await materialsApi.create(toInput(form))

    savedId.current = saved.id
    return saved
  }

  const save = useMutation({
    mutationFn: persist,
    onSuccess: async (saved) => {
      setNotice('Material salvo.')
      await refresh(saved)
    },
  })

  const act = useMutation({
    mutationFn: async ({ action, note }: { action: MaterialAction; note?: string }) => {
      const saved = action === 'release' ? await persist() : material!
      return materialsApi.transition(saved.id, action, note ? { note } : undefined)
    },
    onSuccess: async (saved) => {
      setNotice('Status atualizado.')
      setForm(fromMaterial(saved))
      await refresh(saved)
    },
  })

  const duplicate = useMutation({
    mutationFn: () => materialsApi.duplicate(material!.id),
    onSuccess: (copy) => navigate(`/admin/materiais/${copy.id}`),
  })

  const remove = useMutation({
    mutationFn: () => materialsApi.remove(material!.id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin', 'materials'] })
      navigate('/admin/materiais', { replace: true })
    },
  })

  const failure = [save.error, act.error, duplicate.error, remove.error].find((error) => error instanceof ApiError) as ApiError | undefined
  const fieldError = (field: string) => failure?.fieldError(field)
  const status = material?.status ?? 'draft'
  const isUrlType = URL_TYPES.includes(form.type)
  const busy = save.isPending || act.isPending

  function toggleCategory(categoryId: number, checked: boolean) {
    const category_ids = checked ? [...form.category_ids, categoryId] : form.category_ids.filter((item) => item !== categoryId)
    const keepsPrimary = form.primary_category_id !== null && category_ids.includes(form.primary_category_id)

    setForm({ ...form, category_ids, primary_category_id: keepsPrimary ? form.primary_category_id : (category_ids[0] ?? null) })
  }

  function addTag(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key !== 'Enter' && event.key !== ',') return
    event.preventDefault()
    const tag = tagDraft.trim()
    if (tag && !form.tags.includes(tag)) set('tags', [...form.tags, tag])
    setTagDraft('')
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    setNotice(null)
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid max-w-6xl gap-6" noValidate>
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link to="/admin/materiais" className="text-sm text-muted-foreground hover:underline">
            Biblioteca de materiais
          </Link>
          <h1 className="font-heading text-3xl font-bold tracking-tight">{material ? material.title : 'Novo material'}</h1>
        </div>
        <StatusBadge status={status} />
      </header>

      {material?.review_note && (
        <p className="rounded-md bg-amber-100 px-3 py-2 text-sm text-amber-900">Observação da revisão: {material.review_note}</p>
      )}

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="grid content-start gap-5 rounded-lg border bg-card p-5">
          <div className="grid gap-2">
            <Label htmlFor="title">Título</Label>
            <Input id="title" value={form.title} aria-invalid={Boolean(fieldError('title'))} onChange={(e) => set('title', e.target.value)} />
            {fieldError('title') && <p className="text-sm text-destructive">{fieldError('title')}</p>}
          </div>

          <div className="grid gap-2">
            <Label htmlFor="description">Descrição</Label>
            <Textarea id="description" rows={4} value={form.description} onChange={(e) => set('description', e.target.value)} />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="type">Tipo</Label>
            <NativeSelect
              id="type"
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value as MaterialType, file: null })}
            >
              {Object.entries(TYPE_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </NativeSelect>
          </div>

          {isUrlType ? (
            <div className="grid gap-2">
              <Label htmlFor="external_url">{form.type === 'link' ? 'Endereço' : 'Endereço do vídeo'}</Label>
              <Input
                id="external_url"
                type="url"
                placeholder="https://"
                value={form.external_url ?? ''}
                aria-invalid={Boolean(fieldError('external_url'))}
                onChange={(e) => set('external_url', e.target.value)}
              />
              {fieldError('external_url') && <p className="text-sm text-destructive">{fieldError('external_url')}</p>}
            </div>
          ) : (
            <UploadField
              label="Arquivo"
              purpose="material"
              accept={TYPE_ACCEPT[form.type]}
              value={form.file}
              error={fieldError('file_upload_id')}
              onChange={(upload) => set('file', upload)}
            />
          )}

          <fieldset className="grid gap-2">
            <legend className="mb-1 text-sm font-medium">Categorias</legend>
            <p className="text-xs text-muted-foreground">Marque todas que se aplicam e escolha a principal.</p>
            <div className="grid max-h-72 gap-1 overflow-y-auto rounded-lg border p-2">
              {flattenTree(categories.data ?? []).map(({ category, depth }) => {
                const checked = form.category_ids.includes(category.id)

                return (
                  <div key={category.id} className="flex items-center justify-between gap-3 text-sm" style={{ paddingLeft: depth * 16 }}>
                    <Label className="flex items-center gap-2 font-normal">
                      <Checkbox checked={checked} onCheckedChange={(value) => toggleCategory(category.id, value === true)} />
                      {category.name}
                    </Label>
                    {checked && (
                      <label className="flex items-center gap-1 text-xs text-muted-foreground">
                        <input
                          type="radio"
                          name="primary-category"
                          checked={form.primary_category_id === category.id}
                          onChange={() => set('primary_category_id', category.id)}
                        />
                        Principal
                      </label>
                    )}
                  </div>
                )
              })}
            </div>
            {fieldError('category_ids') && <p className="text-sm text-destructive">{fieldError('category_ids')}</p>}
          </fieldset>

          <div className="grid gap-2">
            <Label htmlFor="tags">Tags</Label>
            {form.tags.length > 0 && (
              <div className="flex flex-wrap gap-1">
                {form.tags.map((tag) => (
                  <button
                    key={tag}
                    type="button"
                    aria-label={`Remover tag ${tag}`}
                    onClick={() => set('tags', form.tags.filter((item) => item !== tag))}
                    className="rounded bg-muted px-2 py-0.5 text-xs hover:bg-destructive/15"
                  >
                    {tag} ×
                  </button>
                ))}
              </div>
            )}
            <Input id="tags" placeholder="Digite e pressione Enter" value={tagDraft} onChange={(e) => setTagDraft(e.target.value)} onKeyDown={addTag} />
          </div>
        </div>

        <aside className="grid content-start gap-5">
          <div className="grid gap-4 rounded-lg border bg-card p-5">
            <div className="grid gap-2">
              <Label htmlFor="teacher">Professor responsável</Label>
              <NativeSelect
                id="teacher"
                value={form.teacher_id ?? ''}
                aria-invalid={Boolean(fieldError('teacher_id'))}
                onChange={(e) => set('teacher_id', e.target.value ? Number(e.target.value) : null)}
              >
                <option value="">Selecione</option>
                {teachers.data?.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
              </NativeSelect>
              {fieldError('teacher_id') && <p className="text-sm text-destructive">{fieldError('teacher_id')}</p>}
            </div>

            <fieldset className="grid gap-2">
              <legend className="mb-1 text-sm font-medium">Colaboradores</legend>
              {teachers.data?.filter((teacher) => teacher.id !== form.teacher_id).map((teacher) => (
                <Label key={teacher.id} className="flex items-center gap-2 font-normal">
                  <Checkbox
                    checked={form.collaborator_ids.includes(teacher.id)}
                    onCheckedChange={(value) =>
                      set('collaborator_ids', value === true
                        ? [...form.collaborator_ids, teacher.id]
                        : form.collaborator_ids.filter((item) => item !== teacher.id))
                    }
                  />
                  {teacher.name}
                </Label>
              ))}
              {teachers.data?.length === 1 && <p className="text-xs text-muted-foreground">Nenhum outro professor cadastrado.</p>}
            </fieldset>

            <UploadField label="Capa" purpose="image" accept=".jpg,.jpeg,.png,.webp" value={form.cover} onChange={(upload) => set('cover', upload)} />
          </div>

          <div className="grid gap-4 rounded-lg border bg-card p-5">
            <div className="grid gap-2">
              <Label htmlFor="visibility">Visibilidade</Label>
              <NativeSelect id="visibility" value={form.visibility} onChange={(e) => set('visibility', e.target.value as FormState['visibility'])}>
                {Object.entries(VISIBILITY_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </NativeSelect>
            </div>

            {form.visibility === 'restricted' && (
              <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm font-medium">Turmas e grupos</legend>
                {groups.data?.map((group) => (
                  <Label key={group.id} className="flex items-center gap-2 font-normal">
                    <Checkbox
                      checked={form.group_ids.includes(group.id)}
                      onCheckedChange={(value) =>
                        set('group_ids', value === true ? [...form.group_ids, group.id] : form.group_ids.filter((item) => item !== group.id))
                      }
                    />
                    {group.name}
                  </Label>
                ))}
              </fieldset>
            )}

            <div className="grid gap-2">
              <Label htmlFor="publish_at">Agendar publicação</Label>
              <Input id="publish_at" type="datetime-local" value={form.publish_at} aria-invalid={Boolean(fieldError('publish_at'))} onChange={(e) => set('publish_at', e.target.value)} />
              {fieldError('publish_at') && <p className="text-sm text-destructive">{fieldError('publish_at')}</p>}
            </div>

            <Label className="flex items-center gap-2 font-normal">
              <Checkbox checked={form.allow_download} onCheckedChange={(value) => set('allow_download', value === true)} />
              Permitir download
            </Label>
            <Label className="flex items-center gap-2 font-normal">
              <Checkbox checked={form.is_featured} onCheckedChange={(value) => set('is_featured', value === true)} />
              Destaque
            </Label>
          </div>

          <div className="grid gap-2 rounded-lg border bg-card p-5">
            {failure && Object.keys(failure.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{failure.message}</p>}
            {failure && Object.keys(failure.errors ?? {}).length > 0 && <p role="alert" className="text-sm text-destructive">Revise os campos destacados.</p>}
            {notice && !failure && <p role="status" className="text-sm text-muted-foreground">{notice}</p>}

            {(status === 'draft' || status === 'pending_review') && (
              <Button type="button" disabled={busy} onClick={() => { setNotice(null); act.mutate({ action: status === 'draft' ? 'release' : 'approve' }) }}>
                {status === 'pending_review' ? 'Aprovar' : form.publish_at && new Date(form.publish_at) > new Date() ? 'Agendar publicação' : 'Publicar'}
              </Button>
            )}
            <Button type="submit" variant="outline" disabled={busy}>
              {save.isPending ? 'Salvando…' : status === 'draft' ? 'Salvar rascunho' : 'Salvar alterações'}
            </Button>
            {status === 'published' && <Button type="button" variant="outline" disabled={busy} onClick={() => act.mutate({ action: 'archive' })}>Arquivar</Button>}
            {status === 'archived' && <Button type="button" variant="outline" disabled={busy} onClick={() => act.mutate({ action: 'restore' })}>Restaurar</Button>}
            {status === 'scheduled' && <Button type="button" variant="outline" disabled={busy} onClick={() => act.mutate({ action: 'unschedule' })}>Cancelar agendamento</Button>}

            {status === 'pending_review' && (
              <div className="grid gap-2 border-t pt-3">
                <Label htmlFor="reject-note">Motivo da devolução</Label>
                <Textarea id="reject-note" rows={2} value={rejectNote} onChange={(e) => setRejectNote(e.target.value)} />
                {fieldError('note') && <p className="text-sm text-destructive">{fieldError('note')}</p>}
                <Button type="button" variant="outline" disabled={busy} onClick={() => act.mutate({ action: 'reject', note: rejectNote })}>Devolver ao professor</Button>
              </div>
            )}

            {material && (
              <div className="flex flex-wrap gap-2 border-t pt-3">
                <Button type="button" variant="ghost" size="sm" disabled={duplicate.isPending} onClick={() => duplicate.mutate()}>Duplicar</Button>
                <Button
                  type="button"
                  variant={confirmingDelete ? 'destructive' : 'ghost'}
                  size="sm"
                  disabled={remove.isPending}
                  onClick={() => (confirmingDelete ? remove.mutate() : setConfirmingDelete(true))}
                >
                  {confirmingDelete ? 'Confirmar exclusão' : 'Excluir'}
                </Button>
              </div>
            )}
          </div>
        </aside>
      </div>
    </form>
  )
}
