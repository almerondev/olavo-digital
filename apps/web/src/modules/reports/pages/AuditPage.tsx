import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { NativeSelect } from '@/components/ui/native-select'
import { formatDate } from '@/utils/format'
import { reportsApi } from '../api'
import { actionLabel } from '../labels'

export function AuditPage() {
  const [params, setParams] = useSearchParams()
  const [actor, setActor] = useState(params.get('actor') ?? '')
  const audit = useQuery({ queryKey: ['admin', 'audit', params.toString()], queryFn: () => reportsApi.audit(params), placeholderData: keepPreviousData })

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
      if (actor !== (params.get('actor') ?? '')) setFilter('actor', actor)
    }, 300)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [actor])

  const meta = audit.data?.meta

  return (
    <div className="grid max-w-6xl gap-5">
      <header>
        <h1 className="font-heading text-3xl font-bold tracking-tight">Auditoria</h1>
        <p className="mt-1 text-sm text-muted-foreground">Quem fez o quê, e quando. Os registros não podem ser alterados.</p>
      </header>
      <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <NativeSelect aria-label="Ação" value={params.get('action') ?? ''} onChange={(e) => setFilter('action', e.target.value)}>
          <option value="">Todas as ações</option>
          {audit.data?.actions.map((action) => <option key={action} value={action}>{actionLabel(action)}</option>)}
        </NativeSelect>
        <Input aria-label="Pessoa" placeholder="Nome ou e-mail" value={actor} onChange={(e) => setActor(e.target.value)} />
        <Input aria-label="De" type="date" value={params.get('from') ?? ''} onChange={(e) => setFilter('from', e.target.value)} />
        <Input aria-label="Até" type="date" value={params.get('to') ?? ''} onChange={(e) => setFilter('to', e.target.value)} />
      </div>
      {audit.isError && <p role="alert">Não foi possível carregar os registros. Recarregue a página.</p>}
      <div className="overflow-x-auto rounded-lg border bg-card">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead className="border-b text-xs text-muted-foreground">
            <tr>
              <th className="p-3 font-medium">Quando</th>
              <th className="p-3 font-medium">Pessoa</th>
              <th className="p-3 font-medium">Ação</th>
              <th className="p-3 font-medium">Item</th>
              <th className="p-3 font-medium">IP</th>
            </tr>
          </thead>
          <tbody>
            {audit.data?.data.length === 0 && <tr><td colSpan={5} className="p-4 text-muted-foreground">Nenhum registro encontrado.</td></tr>}
            {audit.data?.data.map((entry) => (
              <tr key={entry.id} className="border-b last:border-0">
                <td className="p-3 whitespace-nowrap tabular-nums">{formatDate(entry.created_at)}</td>
                <td className="p-3">{entry.actor ?? 'Sistema'}</td>
                <td className="p-3">{actionLabel(entry.action)}</td>
                <td className="p-3 text-muted-foreground">{entry.subject ?? '—'}</td>
                <td className="p-3 text-muted-foreground tabular-nums">{entry.ip_address ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-end gap-2 text-sm">
          <Button variant="outline" size="sm" disabled={meta.current_page === 1} onClick={() => setFilter('page', String(meta.current_page - 1))}>Anterior</Button>
          <span className="tabular-nums">{meta.current_page} de {meta.last_page}</span>
          <Button variant="outline" size="sm" disabled={meta.current_page === meta.last_page} onClick={() => setParams((current) => { const next = new URLSearchParams(current); next.set('page', String(meta.current_page + 1)); return next })}>Próxima</Button>
        </div>
      )}
    </div>
  )
}
