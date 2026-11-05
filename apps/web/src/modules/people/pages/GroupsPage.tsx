import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, X } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { NativeSelect } from '@/components/ui/native-select'
import { cn } from '@/lib/utils'
import { ApiError } from '@/services/http'
import type { StudentGroupItem } from '@/types/api'
import { peopleApi } from '../api'

const KEY = ['admin', 'groups']

function Editor({ group, onDone, onClose }: { group: StudentGroupItem | null; onDone: () => void; onClose: () => void }) {
  const [form, setForm] = useState({ name: group?.name ?? '', kind: group?.kind ?? 'turma', year: group?.year ? String(group.year) : '', is_active: group?.is_active ?? true })
  const [confirming, setConfirming] = useState(false)
  const save = useMutation({
    mutationFn: () => {
      const input = { ...form, year: form.year ? Number(form.year) : null }
      return group ? peopleApi.updateGroup(group.id, input) : peopleApi.createGroup(input)
    },
    onSuccess: onDone,
  })
  const remove = useMutation({ mutationFn: () => peopleApi.deleteGroup(group!.id), onSuccess: onDone })
  const error = save.error instanceof ApiError ? save.error : null

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <div className="flex items-center justify-between">
        <h2 className="font-heading text-lg font-bold">{group ? 'Editar turma' : 'Nova turma ou grupo'}</h2>
        <Button type="button" variant="ghost" size="icon" aria-label="Fechar" onClick={onClose}><X className="size-4" /></Button>
      </div>
      <div className="grid gap-2">
        <Label htmlFor="group-name">Nome</Label>
        <Input id="group-name" value={form.name} aria-invalid={Boolean(error?.fieldError('name'))} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        {error?.fieldError('name') && <p className="text-sm text-destructive">{error.fieldError('name')}</p>}
      </div>
      <div className="grid gap-3 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="group-kind">Tipo</Label>
          <NativeSelect id="group-kind" value={form.kind} onChange={(e) => setForm({ ...form, kind: e.target.value as 'turma' | 'grupo' })}>
            <option value="turma">Turma</option>
            <option value="grupo">Grupo</option>
          </NativeSelect>
        </div>
        <div className="grid gap-2">
          <Label htmlFor="group-year">Ano</Label>
          <Input id="group-year" type="number" value={form.year} onChange={(e) => setForm({ ...form, year: e.target.value })} />
        </div>
      </div>
      <Label className="flex items-center gap-2 font-normal">
        <Checkbox checked={form.is_active} onCheckedChange={(value) => setForm({ ...form, is_active: value === true })} />
        Ativa
      </Label>
      {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : 'Salvar'}</Button>
        {group && <Button type="button" variant={confirming ? 'destructive' : 'ghost'} disabled={remove.isPending} onClick={() => (confirming ? remove.mutate() : setConfirming(true))}>{confirming ? 'Confirmar exclusão' : 'Excluir'}</Button>}
      </div>
    </form>
  )
}

export function GroupsPage() {
  const queryClient = useQueryClient()
  const [selection, setSelection] = useState<number | 'new' | null>(null)
  const groups = useQuery({ queryKey: KEY, queryFn: peopleApi.groups })
  const selected = groups.data?.find((group) => group.id === selection) ?? null

  return (
    <div className="grid max-w-5xl gap-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Turmas e grupos</h1>
          <p className="mt-1 text-sm text-muted-foreground">Use para direcionar conteúdos, avisos e notificações. Os alunos entram pela ficha de cada um.</p>
        </div>
        <Button onClick={() => setSelection('new')}><Plus className="size-4" />Nova turma</Button>
      </header>
      {groups.isError && <p role="alert">Não foi possível carregar as turmas. Recarregue a página.</p>}
      <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <ul className="overflow-hidden rounded-lg border bg-card">
          {groups.data?.length === 0 && <li className="p-4 text-sm text-muted-foreground">Nenhuma turma criada.</li>}
          {groups.data?.map((group) => (
            <li key={group.id} className={cn('flex items-center border-b last:border-0', selection === group.id && 'bg-muted')}>
              <button type="button" onClick={() => setSelection(group.id)} className="min-w-0 flex-1 p-3 text-left text-sm hover:bg-muted/60">
                <span className={cn('block font-medium', !group.is_active && 'text-muted-foreground line-through')}>{group.name}</span>
                <span className="block text-xs text-muted-foreground tabular-nums">{group.kind === 'turma' ? 'Turma' : 'Grupo'}{group.year && ` · ${group.year}`}</span>
              </button>
              <Link to={`/admin/alunos?group=${group.id}`} className="shrink-0 px-3 text-xs underline tabular-nums">{group.students_count} {group.students_count === 1 ? 'aluno' : 'alunos'}</Link>
            </li>
          ))}
        </ul>
        {selection !== null && <Editor key={selection} group={selected} onClose={() => setSelection(null)} onDone={() => { setSelection(null); return queryClient.invalidateQueries({ queryKey: KEY }) }} />}
      </div>
    </div>
  )
}
