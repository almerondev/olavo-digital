import { useMutation } from '@tanstack/react-query'
import { ArrowDown, ArrowUp, X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { courseLabel, flattenTree, useCategories, useTeacherOptions } from '@/modules/materials/hooks'
import { ApiError } from '@/services/http'
import type { BuilderSection } from '@/types/api'
import { homeBuilderApi } from '../api'
import { SOURCE_LABELS, SECTION_LAYOUT_LABELS } from '../labels'
import { MaterialPicker, type PickedMaterial } from './MaterialPicker'
import { AudienceField, fromIso, PeriodFields, toIso } from './SharedFields'

interface SectionEditorProps {
  layoutId: number
  section: BuilderSection | null
  onSaved: () => void
  onClose: () => void
}

export function SectionEditor({ layoutId, section, onSaved, onClose }: SectionEditorProps) {
  const categories = useCategories()
  const teachers = useTeacherOptions()
  const [form, setForm] = useState({
    title: section?.title ?? '',
    description: section?.description ?? '',
    source_type: section?.source_type ?? 'category',
    category_id: section?.category_id ?? null as number | null,
    teacher_id: section?.teacher_id ?? null as number | null,
    layout: section?.layout ?? 'carousel',
    max_items: section?.max_items ?? 20,
    is_active: section?.is_active ?? true,
    starts_at: fromIso(section?.starts_at ?? null),
    ends_at: fromIso(section?.ends_at ?? null),
    group_ids: section?.group_ids ?? [],
  })
  const [materials, setMaterials] = useState<PickedMaterial[]>(section?.materials ?? [])

  const save = useMutation({
    mutationFn: () => {
      const input = { ...form, starts_at: toIso(form.starts_at), ends_at: toIso(form.ends_at), material_ids: materials.map((item) => item.id) }
      return section ? homeBuilderApi.updateSection(section.id, input) : homeBuilderApi.createSection(layoutId, input)
    },
    onSuccess: onSaved,
  })
  const error = save.error instanceof ApiError ? save.error : null

  function moveMaterial(index: number, direction: -1 | 1) {
    const next = [...materials]
    const target = index + direction
    if (target < 0 || target >= next.length) return
    ;[next[index], next[target]] = [next[target], next[index]]
    setMaterials(next)
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <div className="flex items-center justify-between">
        <h2 className="font-heading text-lg font-bold">{section ? 'Editar seção' : 'Nova seção'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}><X className="size-4" /></Button>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="section-title">Título</Label>
        <Input id="section-title" value={form.title} aria-invalid={Boolean(error?.fieldError('title'))} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        {error?.fieldError('title') && <p className="text-sm text-destructive">{error.fieldError('title')}</p>}
      </div>

      <div className="grid gap-2">
        <Label htmlFor="section-description">Descrição (opcional)</Label>
        <Input id="section-description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
      </div>

      <div className="grid gap-2">
        <Label htmlFor="section-source">Origem dos conteúdos</Label>
        <NativeSelect id="section-source" value={form.source_type} onChange={(e) => setForm({ ...form, source_type: e.target.value })}>
          {Object.entries(SOURCE_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </NativeSelect>
      </div>

      {form.source_type === 'category' && (
        <div className="grid gap-2">
          <Label htmlFor="section-category">Categoria</Label>
          <NativeSelect id="section-category" value={form.category_id ?? ''} aria-invalid={Boolean(error?.fieldError('category_id'))} onChange={(e) => setForm({ ...form, category_id: e.target.value ? Number(e.target.value) : null })}>
            <option value="">Selecione</option>
            {flattenTree(categories.data ?? []).map(({ category, depth }) => (
              <option key={category.id} value={category.id}>{'— '.repeat(depth)}{courseLabel(category)}</option>
            ))}
          </NativeSelect>
          {error?.fieldError('category_id') && <p className="text-sm text-destructive">{error.fieldError('category_id')}</p>}
        </div>
      )}

      {form.source_type === 'teacher' && (
        <div className="grid gap-2">
          <Label htmlFor="section-teacher">Professor</Label>
          <NativeSelect id="section-teacher" value={form.teacher_id ?? ''} aria-invalid={Boolean(error?.fieldError('teacher_id'))} onChange={(e) => setForm({ ...form, teacher_id: e.target.value ? Number(e.target.value) : null })}>
            <option value="">Selecione</option>
            {teachers.data?.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
          </NativeSelect>
          {error?.fieldError('teacher_id') && <p className="text-sm text-destructive">{error.fieldError('teacher_id')}</p>}
        </div>
      )}

      {form.source_type === 'manual' && (
        <div className="grid gap-2">
          <p className="text-sm font-medium">Conteúdos escolhidos</p>
          <ol className="grid gap-1">
            {materials.map((material, index) => (
              <li key={material.id} className="flex items-center gap-1 rounded-lg bg-muted/60 px-2 py-1 text-sm">
                <span className="min-w-0 flex-1 truncate">{material.title}</span>
                <Button type="button" variant="ghost" size="icon" aria-label={`Subir ${material.title}`} onClick={() => moveMaterial(index, -1)}><ArrowUp className="size-3.5" /></Button>
                <Button type="button" variant="ghost" size="icon" aria-label={`Descer ${material.title}`} onClick={() => moveMaterial(index, 1)}><ArrowDown className="size-3.5" /></Button>
                <Button type="button" variant="ghost" size="icon" aria-label={`Remover ${material.title}`} onClick={() => setMaterials(materials.filter((item) => item.id !== material.id))}><X className="size-3.5" /></Button>
              </li>
            ))}
          </ol>
          <MaterialPicker label="Buscar material para adicionar" excluded={materials.map((item) => item.id)} onPick={(material) => setMaterials([...materials, material])} />
        </div>
      )}

      <div className="grid gap-3 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="section-layout">Formato</Label>
          <NativeSelect id="section-layout" value={form.layout} onChange={(e) => setForm({ ...form, layout: e.target.value })}>
            {Object.entries(SECTION_LAYOUT_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="section-max">Quantidade de cards</Label>
          <Input id="section-max" type="number" min={1} max={60} value={form.max_items} aria-invalid={Boolean(error?.fieldError('max_items'))} onChange={(e) => setForm({ ...form, max_items: Number(e.target.value) })} />
          {error?.fieldError('max_items') && <p className="text-sm text-destructive">{error.fieldError('max_items')}</p>}
        </div>
      </div>

      <PeriodFields prefix="section" startsAt={form.starts_at} endsAt={form.ends_at} error={error?.fieldError('ends_at')} onChange={(field, value) => setForm({ ...form, [field]: value })} />
      <AudienceField value={form.group_ids} onChange={(group_ids) => setForm({ ...form, group_ids })} />

      <Label className="flex items-center gap-2 font-normal">
        <Checkbox checked={form.is_active} onCheckedChange={(value) => setForm({ ...form, is_active: value === true })} />
        Seção ativa
      </Label>

      {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      <div><Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar seção'}</Button></div>
    </form>
  )
}
