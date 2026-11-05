import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowDown, ArrowUp, Plus, X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { UploadField } from '@/components/media/UploadField'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { CATEGORIES_KEY, flattenTree, useCategories, useGroupOptions, useTeacherOptions } from '@/modules/materials/hooks'
import { ApiError } from '@/services/http'
import type { Category, CategoryType, Upload } from '@/types/api'
import { categoriesApi, type CategoryInput } from '../api/categories'
import { coursesApi } from '../api/courses'

const TYPES_KEY = ['admin', 'category-types']

const VISIBILITY_LABELS: Record<Category['visibility'], string> = {
  public: 'Todos os alunos',
  restricted: 'Turmas selecionadas',
  hidden: 'Oculta',
}

export function CategoriesPage() {
  const queryClient = useQueryClient()
  const categories = useCategories()
  const types = useQuery({ queryKey: TYPES_KEY, queryFn: categoriesApi.types })
  const [selection, setSelection] = useState<number | 'new' | null>(null)

  const reorder = useMutation({
    mutationFn: categoriesApi.reorder,
    onSettled: () => queryClient.invalidateQueries({ queryKey: CATEGORIES_KEY }),
  })

  function move(category: Category, direction: -1 | 1) {
    const siblings = (categories.data ?? []).filter((item) => item.parent_id === category.parent_id).map((item) => item.id)
    const from = siblings.indexOf(category.id)
    const to = from + direction
    if (to < 0 || to >= siblings.length) return

    ;[siblings[from], siblings[to]] = [siblings[to], siblings[from]]
    reorder.mutate(siblings)
  }

  if (categories.isPending || types.isPending) return <p className="text-muted-foreground">Carregando categorias…</p>
  if (categories.isError || types.isError) return <p role="alert">Não foi possível carregar as categorias. Recarregue a página.</p>

  const selected = categories.data.find((category) => category.id === selection) ?? null
  const typeName = (id: number) => types.data.find((type) => type.id === id)?.name

  return (
    <div className="grid max-w-6xl gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Categorias e matérias</h1>
          <p className="mt-1 text-sm text-muted-foreground">Organize o catálogo e defina quem vê cada área.</p>
        </div>
        <Button onClick={() => setSelection('new')}>
          <Plus className="size-4" />
          Nova categoria
        </Button>
      </header>

      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <ul className="overflow-hidden rounded-lg border bg-card">
          {flattenTree(categories.data).map(({ category, depth }) => (
            <li key={category.id} className={cn('flex items-center gap-2 border-b px-3 py-2 text-sm last:border-0', selection === category.id && 'bg-muted')}>
              <button
                type="button"
                onClick={() => setSelection(category.id)}
                className="min-w-0 flex-1 text-left"
                style={{ paddingLeft: depth * 20 }}
              >
                <span className={cn('font-medium', !category.is_active && 'text-muted-foreground line-through')}>{category.name}</span>
                <span className="block text-xs text-muted-foreground tabular-nums">
                  {category.course_name ?? 'Geral'} · {typeName(category.category_type_id)} · {category.materials_count} {category.materials_count === 1 ? "material" : "materiais"}
                  {category.visibility !== 'public' && ` · ${VISIBILITY_LABELS[category.visibility]}`}
                </span>
              </button>
              <Button variant="ghost" size="icon" aria-label={`Mover ${category.name} para cima`} disabled={reorder.isPending} onClick={() => move(category, -1)}>
                <ArrowUp className="size-4" />
              </Button>
              <Button variant="ghost" size="icon" aria-label={`Mover ${category.name} para baixo`} disabled={reorder.isPending} onClick={() => move(category, 1)}>
                <ArrowDown className="size-4" />
              </Button>
            </li>
          ))}
        </ul>

        <div className="grid gap-6">
          {selection !== null && (
            <CategoryEditor
              key={selection}
              category={selected}
              categories={categories.data}
              types={types.data}
              onClose={() => setSelection(null)}
            />
          )}
          <TypesPanel types={types.data} />
        </div>
      </div>
    </div>
  )
}

interface EditorProps {
  category: Category | null
  categories: Category[]
  types: CategoryType[]
  onClose: () => void
}

function CategoryEditor({ category, categories, types, onClose }: EditorProps) {
  const queryClient = useQueryClient()
  const teachers = useTeacherOptions()
  const groups = useGroupOptions()
  const [cover, setCover] = useState<Upload | null>(category?.cover ?? null)
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [form, setForm] = useState<Omit<CategoryInput, 'cover_upload_id'>>({
    name: category?.name ?? '',
    category_type_id: category?.category_type_id ?? types[0].id,
    parent_id: category?.parent_id ?? null,
    course_id: category?.course_id ?? null,
    description: category?.description ?? '',
    icon: category?.icon ?? '',
    is_active: category?.is_active ?? true,
    visibility: category?.visibility ?? 'public',
    teacher_ids: category?.teacher_ids ?? [],
    group_ids: category?.group_ids ?? [],
  })

  const courses = useQuery({ queryKey: ['admin', 'courses'], queryFn: coursesApi.list })
  const refresh = () => queryClient.invalidateQueries({ queryKey: CATEGORIES_KEY })

  const save = useMutation({
    mutationFn: () => {
      const input = { ...form, cover_upload_id: cover?.id ?? null }
      return category ? categoriesApi.update(category.id, input) : categoriesApi.create(input)
    },
    onSuccess: async () => {
      await refresh()
      onClose()
    },
  })

  const remove = useMutation({
    mutationFn: () => categoriesApi.remove(category!.id),
    onSuccess: async () => {
      await refresh()
      onClose()
    },
  })

  const failure = [save.error, remove.error].find((error) => error instanceof ApiError) as ApiError | undefined

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5">
      <div className="flex items-center justify-between">
        <h2 className="font-heading text-lg font-bold">{category ? 'Editar categoria' : 'Nova categoria'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}>
          <X className="size-4" />
        </Button>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="cat-name">Nome</Label>
        <Input id="cat-name" value={form.name} aria-invalid={Boolean(failure?.fieldError('name'))} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        {failure?.fieldError('name') && <p className="text-sm text-destructive">{failure.fieldError('name')}</p>}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="cat-type">Tipo</Label>
          <NativeSelect id="cat-type" value={form.category_type_id} onChange={(e) => setForm({ ...form, category_type_id: Number(e.target.value) })}>
            {types.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="cat-parent">Dentro de</Label>
          <NativeSelect
            id="cat-parent"
            value={form.parent_id ?? ''}
            onChange={(e) => setForm({ ...form, parent_id: e.target.value ? Number(e.target.value) : null })}
          >
            <option value="">Nível principal</option>
            {flattenTree(categories).filter((item) => item.category.id !== category?.id).map(({ category: option, depth }) => (
              <option key={option.id} value={option.id}>
                {'— '.repeat(depth)}
                {option.name}
              </option>
            ))}
          </NativeSelect>
          {failure?.fieldError('parent_id') && <p className="text-sm text-destructive">{failure.fieldError('parent_id')}</p>}
        </div>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="cat-course">Curso</Label>
        <NativeSelect
          id="cat-course"
          value={form.course_id ?? ''}
          disabled={form.parent_id !== null}
          onChange={(e) => setForm({ ...form, course_id: e.target.value ? Number(e.target.value) : null })}
        >
          <option value="">Geral: todos os cursos</option>
          {courses.data?.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}
        </NativeSelect>
        <p className="text-xs text-muted-foreground">{form.parent_id !== null ? 'Subcategoria segue o curso da categoria superior.' : 'Só os alunos do curso escolhido veem esta categoria.'}</p>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="cat-description">Descrição</Label>
        <Textarea id="cat-description" rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
      </div>

      <div className="grid gap-2">
        <Label htmlFor="cat-icon">Ícone</Label>
        <Input id="cat-icon" placeholder="Ex.: book-open" value={form.icon} onChange={(e) => setForm({ ...form, icon: e.target.value })} />
      </div>

      <UploadField label="Imagem de capa" purpose="image" accept=".jpg,.jpeg,.png,.webp" value={cover} onChange={setCover} />

      <div className="grid gap-2">
        <Label htmlFor="cat-visibility">Visibilidade</Label>
        <NativeSelect
          id="cat-visibility"
          value={form.visibility}
          onChange={(e) => setForm({ ...form, visibility: e.target.value as Category['visibility'] })}
        >
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
                  setForm({ ...form, group_ids: value === true ? [...form.group_ids, group.id] : form.group_ids.filter((id) => id !== group.id) })
                }
              />
              {group.name}
            </Label>
          ))}
        </fieldset>
      )}

      <fieldset className="grid gap-2">
        <legend className="mb-1 text-sm font-medium">Professores autorizados a publicar</legend>
        {teachers.data?.map((teacher) => (
          <Label key={teacher.id} className="flex items-center gap-2 font-normal">
            <Checkbox
              checked={form.teacher_ids.includes(teacher.id)}
              onCheckedChange={(value) =>
                setForm({ ...form, teacher_ids: value === true ? [...form.teacher_ids, teacher.id] : form.teacher_ids.filter((id) => id !== teacher.id) })
              }
            />
            {teacher.name}
          </Label>
        ))}
      </fieldset>

      <Label className="flex items-center gap-2 font-normal">
        <Checkbox checked={form.is_active} onCheckedChange={(value) => setForm({ ...form, is_active: value === true })} />
        Categoria ativa
      </Label>

      {failure && !failure.fieldError('name') && !failure.fieldError('parent_id') && (
        <p role="alert" className="text-sm text-destructive">{failure.message}</p>
      )}

      <div className="flex flex-wrap items-center justify-between gap-2">
        <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar'}</Button>
        {category && (
          <Button
            type="button"
            variant={confirmingDelete ? 'destructive' : 'ghost'}
            disabled={remove.isPending}
            onClick={() => (confirmingDelete ? remove.mutate() : setConfirmingDelete(true))}
          >
            {confirmingDelete ? 'Confirmar exclusão' : 'Excluir'}
          </Button>
        )}
      </div>
    </form>
  )
}

function TypesPanel({ types }: { types: CategoryType[] }) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const refresh = () => queryClient.invalidateQueries({ queryKey: TYPES_KEY })

  const create = useMutation({
    mutationFn: () => categoriesApi.createType(name),
    onSuccess: async () => {
      setName('')
      await refresh()
    },
  })
  const remove = useMutation({ mutationFn: categoriesApi.removeType, onSuccess: refresh })
  const failure = [create.error, remove.error].find((error) => error instanceof ApiError) as ApiError | undefined

  function submit(event: FormEvent) {
    event.preventDefault()
    create.mutate()
  }

  return (
    <section className="grid gap-3 rounded-lg border bg-card p-5">
      <h2 className="font-heading text-lg font-bold">Tipos de categoria</h2>
      <ul className="grid gap-1 text-sm">
        {types.map((type) => (
          <li key={type.id} className="flex items-center justify-between gap-2">
            {type.name}
            {!type.is_system && (
              <Button variant="ghost" size="icon" aria-label={`Excluir tipo ${type.name}`} onClick={() => remove.mutate(type.id)}>
                <X className="size-4" />
              </Button>
            )}
          </li>
        ))}
      </ul>
      <form onSubmit={submit} className="flex gap-2">
        <Input aria-label="Nome do novo tipo" placeholder="Novo tipo" value={name} onChange={(e) => setName(e.target.value)} />
        <Button type="submit" variant="outline" disabled={create.isPending}>Adicionar</Button>
      </form>
      {failure && <p role="alert" className="text-sm text-destructive">{failure.fieldError('name') ?? failure.message}</p>}
    </section>
  )
}
