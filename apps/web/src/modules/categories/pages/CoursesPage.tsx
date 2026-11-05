import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { ApiError } from '@/services/http'
import type { Course } from '@/types/api'
import { coursesApi } from '../api/courses'

const KEY = ['admin', 'courses']

function CourseEditor({ course, onDone, onClose }: { course: Course | null; onDone: () => void; onClose: () => void }) {
  const [form, setForm] = useState({ name: course?.name ?? '', description: course?.description ?? '', is_active: course?.is_active ?? true })
  const [confirming, setConfirming] = useState(false)
  const save = useMutation({ mutationFn: () => (course ? coursesApi.update(course.id, form) : coursesApi.create(form)), onSuccess: onDone })
  const remove = useMutation({ mutationFn: () => coursesApi.remove(course!.id), onSuccess: onDone })
  const error = save.error instanceof ApiError ? save.error : null
  const removeError = remove.error instanceof ApiError ? remove.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <div className="flex items-center justify-between">
        <h2 className="font-heading text-lg font-bold">{course ? 'Editar curso' : 'Novo curso'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}><X className="size-4" /></Button>
      </div>
      <div className="grid gap-2">
        <Label htmlFor="course-name">Nome</Label>
        <Input id="course-name" value={form.name} aria-invalid={Boolean(error?.fieldError('name'))} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        {error?.fieldError('name') && <p className="text-sm text-destructive">{error.fieldError('name')}</p>}
      </div>
      <div className="grid gap-2">
        <Label htmlFor="course-description">Descrição</Label>
        <Textarea id="course-description" rows={2} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} />
      </div>
      <Label className="flex items-center gap-2 font-normal">
        <Checkbox checked={form.is_active} onCheckedChange={(value) => setForm({ ...form, is_active: value === true })} />
        Curso ativo
      </Label>
      {removeError && <p role="alert" className="text-sm text-destructive">{removeError.message}</p>}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar'}</Button>
        {course && (
          <Button type="button" variant={confirming ? 'destructive' : 'ghost'} disabled={remove.isPending} onClick={() => (confirming ? remove.mutate() : setConfirming(true))}>
            {confirming ? 'Confirmar exclusão' : 'Excluir'}
          </Button>
        )}
      </div>
    </form>
  )
}

export function CoursesPage() {
  const queryClient = useQueryClient()
  const [selection, setSelection] = useState<number | 'new' | null>(null)
  const courses = useQuery({ queryKey: KEY, queryFn: coursesApi.list })
  const selected = courses.data?.find((course) => course.id === selection) ?? null

  return (
    <div className="grid max-w-5xl gap-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Cursos</h1>
          <p className="mt-1 text-sm text-muted-foreground">Cada curso tem as próprias matérias, e o aluno só vê os cursos em que está matriculado.</p>
        </div>
        <Button onClick={() => setSelection('new')}><Plus className="size-4" />Novo curso</Button>
      </header>
      {courses.isError && <p role="alert">Não foi possível carregar os cursos. Recarregue a página.</p>}
      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <ul className="overflow-hidden rounded-lg border bg-card">
          {courses.data?.map((course) => (
            <li key={course.id} className={cn('border-b last:border-0', selection === course.id && 'bg-muted')}>
              <button type="button" onClick={() => setSelection(course.id)} className="w-full p-3 text-left text-sm hover:bg-muted/60">
                <span className={cn('block font-medium', !course.is_active && 'text-muted-foreground line-through')}>{course.name}</span>
                <span className="block text-xs text-muted-foreground tabular-nums">{course.categories_count} {course.categories_count === 1 ? 'matéria' : 'matérias'} · {course.students_count} {course.students_count === 1 ? 'aluno' : 'alunos'}</span>
              </button>
            </li>
          ))}
        </ul>
        {selection !== null && (
          <CourseEditor key={selection} course={selected} onClose={() => setSelection(null)} onDone={() => { setSelection(null); return queryClient.invalidateQueries({ queryKey: KEY }) }} />
        )}
      </div>
      <p className="text-sm text-muted-foreground">As matérias de cada curso ficam em <Link to="/admin/categorias" className="underline">Categorias</Link>.</p>
    </div>
  )
}
