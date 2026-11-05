import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { coursesApi } from '@/modules/categories/api/courses'
import { ApiError } from '@/services/http'
import type { StudentDetail } from '@/types/api'
import { downloadJson } from '@/utils/download'
import { formatDate } from '@/utils/format'
import { peopleApi } from '../api'
import { STATUS_LABELS } from './StudentsPage'

const KEY = ['admin', 'students']
const CONFIRM_WORD = 'ANONIMIZAR'

function toggle(list: number[], id: number, on: boolean) {
  return on ? [...list, id] : list.filter((item) => item !== id)
}

function StudentForm({ student }: { student: StudentDetail | null }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const courses = useQuery({ queryKey: ['admin', 'courses'], queryFn: coursesApi.list })
  const groups = useQuery({ queryKey: ['admin', 'groups'], queryFn: peopleApi.groups })
  const [form, setForm] = useState({
    name: student?.name ?? '',
    email: student?.email ?? '',
    phone: student?.phone ?? '',
    enrollment_code: student?.enrollment_code ?? '',
    birth_date: student?.birth_date ?? '',
    guardian_name: student?.guardian_name ?? '',
    guardian_contact: student?.guardian_contact ?? '',
    course_ids: student?.course_ids ?? [],
    group_ids: student?.group_ids ?? [],
  })

  const save = useMutation({
    mutationFn: () => {
      const input = {
        ...form,
        phone: form.phone || null,
        enrollment_code: form.enrollment_code || null,
        birth_date: form.birth_date || null,
        guardian_name: form.guardian_name || null,
        guardian_contact: form.guardian_contact || null,
      }
      return student ? peopleApi.update(student.id, input) : peopleApi.create(input)
    },
    onSuccess: (saved) => {
      queryClient.invalidateQueries({ queryKey: KEY })
      if (!student) navigate(`/admin/alunos/${saved.id}`, { replace: true })
    },
  })
  const error = save.error instanceof ApiError ? save.error : null

  function field(name: 'name' | 'email' | 'phone' | 'enrollment_code' | 'birth_date' | 'guardian_name' | 'guardian_contact', label: string, type = 'text') {
    return (
      <div className="grid gap-2">
        <Label htmlFor={`student-${name}`}>{label}</Label>
        <Input id={`student-${name}`} type={type} value={form[name]} aria-invalid={Boolean(error?.fieldError(name))} onChange={(e) => setForm({ ...form, [name]: e.target.value })} />
        {error?.fieldError(name) && <p className="text-sm text-destructive">{error.fieldError(name)}</p>}
      </div>
    )
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-4 rounded-lg border bg-card p-5" noValidate>
      <h2 className="font-heading text-lg font-bold">Dados do aluno</h2>
      <div className="grid gap-3 sm:grid-cols-2">
        {field('name', 'Nome')}
        {field('email', 'E-mail', 'email')}
        {field('phone', 'Telefone', 'tel')}
        {field('enrollment_code', 'Matrícula')}
        {field('birth_date', 'Data de nascimento', 'date')}
        {field('guardian_name', 'Responsável')}
        {field('guardian_contact', 'Contato do responsável')}
      </div>
      <fieldset className="grid gap-2">
        <legend className="mb-1 text-sm font-medium">Cursos</legend>
        <p className="text-xs text-muted-foreground">O aluno só vê as matérias dos cursos marcados.</p>
        {courses.data?.map((course) => (
          <Label key={course.id} className="flex items-center gap-2 font-normal">
            <Checkbox checked={form.course_ids.includes(course.id)} onCheckedChange={(on) => setForm({ ...form, course_ids: toggle(form.course_ids, course.id, on === true) })} />
            {course.name}
          </Label>
        ))}
      </fieldset>
      <fieldset className="grid gap-2">
        <legend className="mb-1 text-sm font-medium">Turmas e grupos</legend>
        {groups.data?.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma turma criada.</p>}
        {groups.data?.map((group) => (
          <Label key={group.id} className="flex items-center gap-2 font-normal">
            <Checkbox checked={form.group_ids.includes(group.id)} onCheckedChange={(on) => setForm({ ...form, group_ids: toggle(form.group_ids, group.id, on === true) })} />
            {group.name}
          </Label>
        ))}
      </fieldset>
      {!student && <p className="text-sm text-muted-foreground">O aluno recebe por e-mail um link para criar a própria senha.</p>}
      {error && Object.keys(error.errors ?? {}).length === 0 && <p role="alert" className="text-sm text-destructive">{error.message}</p>}
      {save.isSuccess && student && <p role="status" className="text-sm text-muted-foreground">Alterações salvas.</p>}
      <div><Button type="submit" disabled={save.isPending}>{save.isPending ? 'Salvando…' : student ? 'Salvar' : 'Cadastrar aluno'}</Button></div>
    </form>
  )
}

function AccountPanel({ student }: { student: StudentDetail }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [confirmText, setConfirmText] = useState('')
  const [notice, setNotice] = useState('')
  const refresh = () => queryClient.invalidateQueries({ queryKey: KEY })
  const blocked = student.status === 'blocked'

  const toggleBlock = useMutation({ mutationFn: () => (blocked ? peopleApi.unblock(student.id) : peopleApi.block(student.id)), onSuccess: refresh })
  const reset = useMutation({ mutationFn: () => peopleApi.resetPassword(student.id), onSuccess: () => setNotice('Link de redefinição enviado ao e-mail do aluno.') })
  const exportData = useMutation({ mutationFn: () => peopleApi.exportData(student.id), onSuccess: (data) => downloadJson(`dados-aluno-${student.id}.json`, data) })
  const anonymize = useMutation({
    mutationFn: () => peopleApi.anonymize(student.id),
    onSuccess: async () => {
      await refresh()
      navigate('/admin/alunos', { replace: true })
    },
  })
  const failure = [toggleBlock.error, reset.error, exportData.error, anonymize.error].find((error) => error instanceof ApiError) as ApiError | undefined

  return (
    <section aria-label="Conta" className="grid gap-3 rounded-lg border bg-card p-5 text-sm">
      <h2 className="font-heading text-lg font-bold">Conta</h2>
      <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
        <dt className="text-muted-foreground">Situação</dt><dd>{STATUS_LABELS[student.status]}</dd>
        <dt className="text-muted-foreground">Cadastro</dt><dd className="tabular-nums">{formatDate(student.created_at)}</dd>
        <dt className="text-muted-foreground">Último acesso</dt><dd className="tabular-nums">{student.last_login_at ? formatDate(student.last_login_at) : 'Nunca'}</dd>
        <dt className="text-muted-foreground">Termos</dt><dd className="tabular-nums">{student.terms_accepted_at ? `Aceitos em ${formatDate(student.terms_accepted_at)}` : 'Sem aceite registrado'}</dd>
      </dl>
      <div className="flex flex-wrap gap-2">
        <Button variant={blocked ? 'default' : 'outline'} size="sm" disabled={toggleBlock.isPending} onClick={() => toggleBlock.mutate()}>{blocked ? 'Desbloquear' : 'Bloquear'}</Button>
        <Button variant="outline" size="sm" disabled={reset.isPending} onClick={() => reset.mutate()}>Redefinir senha</Button>
        <Button variant="outline" size="sm" disabled={exportData.isPending} onClick={() => exportData.mutate()}>Exportar dados</Button>
      </div>
      {notice && <p role="status" className="text-muted-foreground">{notice}</p>}
      {failure && <p role="alert" className="text-destructive">{failure.message}</p>}
      <div className="grid gap-2 border-t pt-3">
        <h3 className="font-medium">Excluir dados pessoais</h3>
        {student.deletion_requested_at && <p className="font-medium">O aluno pediu a exclusão em {formatDate(student.deletion_requested_at)}.</p>}
        <p className="text-muted-foreground">Remove nome, contatos e vínculos de forma definitiva. Não dá para desfazer. Para confirmar, digite {CONFIRM_WORD}.</p>
        <div className="flex flex-wrap gap-2">
          <Input aria-label="Confirmação" className="w-48" value={confirmText} onChange={(e) => setConfirmText(e.target.value)} />
          <Button variant="destructive" size="sm" disabled={confirmText !== CONFIRM_WORD || anonymize.isPending} onClick={() => anonymize.mutate()}>Anonimizar aluno</Button>
        </div>
      </div>
    </section>
  )
}

function HistoryPanel({ student }: { student: StudentDetail }) {
  return (
    <section aria-label="Histórico" className="grid gap-3 rounded-lg border bg-card p-5 text-sm">
      <h2 className="font-heading text-lg font-bold">Histórico</h2>
      <p className="text-muted-foreground tabular-nums">{student.stats.accesses} acessos · {student.stats.completed} concluídos · {student.stats.favorites} favoritos</p>
      {student.history.length === 0 && <p className="text-muted-foreground">Nenhum acesso a conteúdos ainda.</p>}
      <ul className="max-h-96 overflow-auto">
        {student.history.map((entry) => (
          <li key={entry.id} className="flex justify-between gap-3 border-b py-1.5 last:border-0">
            <Link to={`/materiais/${entry.slug}`} className="truncate hover:underline">{entry.title}</Link>
            <span className="shrink-0 text-xs text-muted-foreground tabular-nums">{formatDate(entry.started_at)}</span>
          </li>
        ))}
      </ul>
    </section>
  )
}

export function StudentEditorPage() {
  const { id } = useParams()
  const studentId = id && id !== 'novo' ? Number(id) : null
  const student = useQuery({ queryKey: [...KEY, 'detail', studentId], queryFn: () => peopleApi.student(studentId!), enabled: studentId !== null })

  return (
    <div className="grid max-w-6xl gap-5">
      <header>
        <Link to="/admin/alunos" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"><ArrowLeft className="size-3.5" />Alunos</Link>
        <h1 className="mt-1 font-heading text-3xl font-bold tracking-tight">{studentId ? student.data?.name ?? 'Aluno' : 'Novo aluno'}</h1>
      </header>
      {student.isError && <p role="alert">Não foi possível carregar este aluno.</p>}
      {studentId === null && <div className="max-w-2xl"><StudentForm student={null} /></div>}
      {student.data && (
        <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_400px]">
          <StudentForm key={student.dataUpdatedAt} student={student.data} />
          <div className="grid gap-5">
            <AccountPanel student={student.data} />
            <HistoryPanel student={student.data} />
          </div>
        </div>
      )}
    </div>
  )
}
