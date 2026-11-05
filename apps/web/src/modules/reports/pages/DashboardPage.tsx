import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router'
import type { DashboardData } from '@/types/api'
import { formatDate } from '@/utils/format'
import { reportsApi } from '../api'
import { actionLabel } from '../labels'

/** Âmbar da marca, escurecido para ter contraste sobre o cartão claro. */
const MARK = 'oklch(0.62 0.14 70)'
const number = new Intl.NumberFormat('pt-BR')

function shortDay(day: string): string {
  const [, month, date] = day.split('-')
  return `${date}/${month}`
}

function Stat({ label, value, hint }: { label: string; value: number; hint?: string }) {
  return (
    <div className="rounded-lg border bg-card p-4">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 font-heading text-3xl font-bold tabular-nums">{number.format(value)}</p>
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  )
}

function ViewsChart({ data }: { data: DashboardData['views_by_day'] }) {
  const [hovered, setHovered] = useState<number | null>(null)
  const max = Math.max(1, ...data.map((item) => item.views))
  const total = data.reduce((sum, item) => sum + item.views, 0)
  const active = hovered === null ? null : data[hovered]

  return (
    <section aria-label="Acessos por dia" className="rounded-lg border bg-card p-4">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="font-heading font-bold">Acessos nos últimos 30 dias</h2>
        <p className="text-sm text-muted-foreground tabular-nums" aria-live="polite">
          {active ? `${shortDay(active.day)}: ${number.format(active.views)} ${active.views === 1 ? 'acesso' : 'acessos'}` : `${number.format(total)} no período`}
        </p>
      </div>
      <div className="mt-4 flex gap-2">
        <div className="flex h-40 flex-col justify-between text-right text-xs text-muted-foreground tabular-nums"><span>{number.format(max)}</span><span>0</span></div>
        <div className="min-w-0 flex-1">
          <div className="flex h-40 items-end border-b" onMouseLeave={() => setHovered(null)}>
            {data.map((item, index) => (
              <div key={item.day} className="flex h-full min-w-0 flex-1 items-end px-px" onMouseEnter={() => setHovered(index)} title={`${shortDay(item.day)}: ${item.views}`}>
                <div className="w-full rounded-t" style={{ height: item.views ? `${Math.max(3, (item.views / max) * 100)}%` : 0, background: MARK, opacity: hovered === null || hovered === index ? 1 : 0.45 }} />
              </div>
            ))}
          </div>
          <div className="mt-1 flex justify-between text-xs text-muted-foreground tabular-nums"><span>{shortDay(data[0].day)}</span><span>{shortDay(data[data.length - 1].day)}</span></div>
        </div>
      </div>
      <details className="mt-3 text-sm">
        <summary className="cursor-pointer text-muted-foreground">Ver tabela</summary>
        <table className="mt-2 w-full max-w-xs text-left">
          <thead><tr className="text-xs text-muted-foreground"><th className="py-1 font-medium">Dia</th><th className="py-1 text-right font-medium">Acessos</th></tr></thead>
          <tbody>{data.map((item) => <tr key={item.day} className="border-t"><td className="py-1 tabular-nums">{shortDay(item.day)}</td><td className="py-1 text-right tabular-nums">{item.views}</td></tr>)}</tbody>
        </table>
      </details>
    </section>
  )
}

function CoursesChart({ data }: { data: DashboardData['students_by_course'] }) {
  const max = Math.max(1, ...data.map((item) => item.students))

  return (
    <section aria-label="Alunos por curso" className="rounded-lg border bg-card p-4">
      <h2 className="font-heading font-bold">Alunos por curso</h2>
      <ul className="mt-3 grid gap-3 text-sm">
        {data.map((item) => (
          <li key={item.name}>
            <div className="flex justify-between gap-3"><span className="truncate">{item.name}</span><span className="font-medium tabular-nums">{number.format(item.students)}</span></div>
            <div className="mt-1 h-2 rounded bg-muted"><div className="h-full rounded" style={{ width: `${(item.students / max) * 100}%`, background: MARK }} /></div>
          </li>
        ))}
      </ul>
    </section>
  )
}

function ListPanel({ title, empty, children }: { title: string; empty: boolean; children: React.ReactNode }) {
  return (
    <section aria-label={title} className="rounded-lg border bg-card p-4">
      <h2 className="font-heading font-bold">{title}</h2>
      {empty ? <p className="mt-2 text-sm text-muted-foreground">Sem dados ainda.</p> : <ul className="mt-2 text-sm">{children}</ul>}
    </section>
  )
}

function Row({ left, right }: { left: React.ReactNode; right: React.ReactNode }) {
  return (
    <li className="flex items-baseline justify-between gap-3 border-b py-1.5 last:border-0">
      <span className="min-w-0 truncate">{left}</span>
      <span className="shrink-0 text-xs text-muted-foreground tabular-nums">{right}</span>
    </li>
  )
}

export function DashboardPage() {
  const dashboard = useQuery({ queryKey: ['admin', 'dashboard'], queryFn: reportsApi.dashboard })

  if (dashboard.isError) return <p role="alert">Não foi possível carregar a visão geral. Recarregue a página.</p>
  if (!dashboard.data) return <div role="status" aria-label="Carregando" className="h-64 animate-pulse rounded-lg bg-muted" />

  const { totals, views_by_day: views, students_by_course: courses, recent_students: recent, top_materials: top, latest_materials: latest, recent_activity: activity } = dashboard.data

  return (
    <div className="grid max-w-6xl gap-5">
      <h1 className="font-heading text-3xl font-bold tracking-tight">Visão geral</h1>
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Stat label="Alunos" value={totals.students} hint={`${number.format(totals.active_students)} ativos`} />
        <Stat label="Ativos nos últimos 7 dias" value={totals.recent_students} />
        <Stat label="Professores" value={totals.teachers} />
        <Stat label="Materiais publicados" value={totals.published_materials} hint={`${number.format(totals.videos)} vídeos`} />
        <Stat label="Visualizações" value={totals.views} />
        <Stat label="Transmissões realizadas" value={totals.live_streams} />
      </div>
      <div className="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <ViewsChart data={views} />
        <CoursesChart data={courses} />
      </div>
      <div className="grid gap-5 md:grid-cols-2">
        <ListPanel title="Materiais mais acessados" empty={top.length === 0}>
          {top.map((item) => <Row key={item.id} left={<Link to={`/admin/materiais/${item.id}`} className="hover:underline">{item.title}</Link>} right={`${number.format(item.views)} acessos`} />)}
        </ListPanel>
        <ListPanel title="Últimas publicações" empty={latest.length === 0}>
          {latest.map((item) => <Row key={item.id} left={<Link to={`/admin/materiais/${item.id}`} className="hover:underline">{item.title}</Link>} right={formatDate(item.published_at)} />)}
        </ListPanel>
        <ListPanel title="Alunos ativos recentemente" empty={recent.length === 0}>
          {recent.map((item) => <Row key={item.id} left={<Link to={`/admin/alunos/${item.id}`} className="hover:underline">{item.name}</Link>} right={formatDate(item.last_login_at)} />)}
        </ListPanel>
        <ListPanel title="Atividades recentes" empty={activity.length === 0}>
          {activity.map((item) => <Row key={item.id} left={<>{item.actor ?? 'Sistema'}: {actionLabel(item.action).toLowerCase()}</>} right={formatDate(item.created_at)} />)}
        </ListPanel>
      </div>
    </div>
  )
}
