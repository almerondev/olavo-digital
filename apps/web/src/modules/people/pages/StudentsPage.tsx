import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { Plus, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { cn } from '@/lib/utils'
import { coursesApi } from '@/modules/categories/api/courses'
import { formatDate } from '@/utils/format'
import { peopleApi } from '../api'

export const STATUS_LABELS = { active: 'Ativo', pending: 'Pendente', blocked: 'Bloqueado' }

export function StudentsPage() {
  const [params, setParams] = useSearchParams()
  const [term, setTerm] = useState(params.get('q') ?? '')
  const courses = useQuery({ queryKey: ['admin', 'courses'], queryFn: coursesApi.list })
  const groups = useQuery({ queryKey: ['admin', 'groups'], queryFn: peopleApi.groups })
  const students = useQuery({ queryKey: ['admin', 'students', params.toString()], queryFn: () => peopleApi.students(params), placeholderData: keepPreviousData })

  function setFilter(name: string, value: string) {
    setParams((current) => {
      const next = new URLSearchParams(current)
      next.delete('page')
      if (value) next.set(name, value)
      else next.delete(name)
      return next
    }, { replace: true })
  }

  useEffect(() => {
    const timer = setTimeout(() => {
      if (term !== (params.get('q') ?? '')) setFilter('q', term)
    }, 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [term])

  const meta = students.data?.meta

  function goTo(page: number) {
    setParams((current) => {
      const next = new URLSearchParams(current)
      next.set('page', String(page))
      return next
    })
  }

  return (
    <div className="grid max-w-6xl gap-5">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Alunos</h1>
          {meta && <p className="mt-1 text-sm text-muted-foreground tabular-nums">{meta.total} {meta.total === 1 ? 'aluno' : 'alunos'}</p>}
        </div>
        <Button render={<Link to="/admin/alunos/novo" />}><Plus className="size-4" />Novo aluno</Button>
      </header>

      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
        <div className="relative sm:col-span-2">
          <Search aria-hidden className="absolute top-2 left-2.5 size-4 text-muted-foreground" />
          <Input aria-label="Buscar aluno" placeholder="Nome, e-mail, telefone ou matrícula" className="pl-8" value={term} onChange={(e) => setTerm(e.target.value)} />
        </div>
        <NativeSelect aria-label="Curso" value={params.get('course') ?? ''} onChange={(e) => setFilter('course', e.target.value)}>
          <option value="">Todos os cursos</option>
          {courses.data?.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}
        </NativeSelect>
        <NativeSelect aria-label="Turma" value={params.get('group') ?? ''} onChange={(e) => setFilter('group', e.target.value)}>
          <option value="">Todas as turmas</option>
          {groups.data?.map((group) => <option key={group.id} value={group.id}>{group.name}</option>)}
        </NativeSelect>
        <NativeSelect aria-label="Situação" value={params.get('deletion') ? 'deletion' : params.get('status') ?? ''} onChange={(e) => {
          const value = e.target.value
          setParams((current) => {
            const next = new URLSearchParams(current)
            next.delete('page')
            next.delete('status')
            next.delete('deletion')
            if (value === 'deletion') next.set('deletion', '1')
            else if (value) next.set('status', value)
            return next
          }, { replace: true })
        }}>
          <option value="">Todas as situações</option>
          <option value="active">Ativos</option>
          <option value="blocked">Bloqueados</option>
          <option value="deletion">Pediram exclusão</option>
        </NativeSelect>
      </div>

      {students.isError && <p role="alert">Não foi possível carregar os alunos. Recarregue a página.</p>}

      <div className="overflow-x-auto rounded-lg border bg-card">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead className="border-b text-xs text-muted-foreground">
            <tr>
              <th className="p-3 font-medium">Aluno</th>
              <th className="p-3 font-medium">Curso</th>
              <th className="p-3 font-medium">Turma</th>
              <th className="p-3 font-medium">Último acesso</th>
              <th className="p-3 font-medium">Situação</th>
            </tr>
          </thead>
          <tbody>
            {students.data?.data.length === 0 && <tr><td colSpan={5} className="p-4 text-muted-foreground">Nenhum aluno encontrado.</td></tr>}
            {students.data?.data.map((student) => (
              <tr key={student.id} className="border-b last:border-0 hover:bg-muted/50">
                <td className="p-3">
                  <Link to={`/admin/alunos/${student.id}`} className="font-medium hover:underline">{student.name}</Link>
                  <span className="block text-xs text-muted-foreground">{student.email}</span>
                </td>
                <td className="p-3">{student.courses.join(', ') || '—'}</td>
                <td className="p-3">{student.groups.join(', ') || '—'}</td>
                <td className="p-3 tabular-nums">{student.last_login_at ? formatDate(student.last_login_at) : 'Nunca'}</td>
                <td className="p-3">
                  <span className={cn('rounded px-2 py-0.5 text-xs font-semibold', student.status === 'blocked' ? 'bg-destructive text-white' : 'bg-muted text-muted-foreground')}>{STATUS_LABELS[student.status]}</span>
                  {student.deletion_requested_at && <span className="ml-1 rounded bg-brand px-2 py-0.5 text-xs font-semibold text-brand-foreground">Pediu exclusão</span>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-end gap-2 text-sm">
          <Button variant="outline" size="sm" disabled={meta.current_page === 1} onClick={() => goTo(meta.current_page - 1)}>Anterior</Button>
          <span className="tabular-nums">{meta.current_page} de {meta.last_page}</span>
          <Button variant="outline" size="sm" disabled={meta.current_page === meta.last_page} onClick={() => goTo(meta.current_page + 1)}>Próxima</Button>
        </div>
      )}
    </div>
  )
}
