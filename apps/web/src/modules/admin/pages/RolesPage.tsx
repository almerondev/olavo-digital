import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useMemo, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { ApiError } from '@/services/http'
import type { Permission, Role } from '@/types/api'
import { rolesApi, type RoleInput } from '../api/roles'

const MODULE_LABELS: Record<string, string> = {
  paineis: 'Painéis',
  conteudo: 'Conteúdo',
  materiais: 'Materiais',
  relatorios: 'Relatórios',
  elenco: 'Elenco',
  home: 'Home',
  catalogo: 'Catálogo',
  pessoas: 'Pessoas',
  comunicacao: 'Comunicação',
  olavo_tv: 'Ao vivo agora',
  sistema: 'Sistema',
}

const ROLES_KEY = ['admin', 'roles']
const EMPTY: RoleInput = { name: '', description: '', permissions: [] }

function toInput(role: Role): RoleInput {
  return { name: role.name, description: role.description ?? '', permissions: role.permissions }
}

export function RolesPage() {
  const queryClient = useQueryClient()
  const roles = useQuery({ queryKey: ROLES_KEY, queryFn: rolesApi.list })
  const permissions = useQuery({ queryKey: ['admin', 'permissions'], queryFn: rolesApi.permissions })
  const [selectedId, setSelectedId] = useState<number | 'new' | null>(null)

  const activeId = selectedId ?? roles.data?.[0]?.id ?? null
  const selected = roles.data?.find((role) => role.id === activeId)

  if (roles.isPending || permissions.isPending) return <p className="text-muted-foreground">Carregando papéis…</p>
  if (roles.isError || permissions.isError) {
    return <p role="alert">Não foi possível carregar os papéis. Recarregue a página para tentar novamente.</p>
  }

  return (
    <div className="grid max-w-5xl gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="font-heading text-3xl font-bold tracking-tight">Papéis e permissões</h1>
          <p className="mt-1 text-sm text-muted-foreground">Defina o que cada perfil pode fazer na plataforma.</p>
        </div>
        <Button onClick={() => setSelectedId('new')}>
          <Plus className="size-4" />
          Novo papel
        </Button>
      </header>

      <div className="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
        <ul className="grid content-start gap-1">
          {roles.data.map((role) => (
            <li key={role.id}>
              <button
                type="button"
                onClick={() => setSelectedId(role.id)}
                aria-current={role.id === activeId}
                className={cn(
                  'flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm transition',
                  role.id === activeId ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                )}
              >
                <span className="font-medium">{role.name}</span>
                <span className="text-xs tabular-nums opacity-70">
                  {role.users_count} {role.users_count === 1 ? 'usuário' : 'usuários'}
                </span>
              </button>
            </li>
          ))}
        </ul>

        <RoleEditor
          key={activeId}
          role={activeId === 'new' ? null : (selected ?? null)}
          permissions={permissions.data}
          onSaved={async (role) => {
            await queryClient.invalidateQueries({ queryKey: ROLES_KEY })
            setSelectedId(role.id)
          }}
          onDeleted={async () => {
            await queryClient.invalidateQueries({ queryKey: ROLES_KEY })
            setSelectedId(null)
          }}
        />
      </div>
    </div>
  )
}

interface RoleEditorProps {
  role: Role | null
  permissions: Permission[]
  onSaved: (role: Role) => void
  onDeleted: () => void
}

function RoleEditor({ role, permissions, onSaved, onDeleted }: RoleEditorProps) {
  const [form, setForm] = useState<RoleInput>(role ? toInput(role) : EMPTY)
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const readOnly = role?.slug === 'admin'

  const grouped = useMemo(() => {
    const groups = new Map<string, Permission[]>()
    for (const permission of permissions) {
      groups.set(permission.module, [...(groups.get(permission.module) ?? []), permission])
    }
    return [...groups]
  }, [permissions])

  const save = useMutation({
    mutationFn: () => (role ? rolesApi.update(role.id, form) : rolesApi.create(form)),
    onSuccess: onSaved,
  })
  const remove = useMutation({ mutationFn: () => rolesApi.remove(role!.id), onSuccess: onDeleted })
  const saveError = save.error instanceof ApiError ? save.error : null
  const removeError = remove.error instanceof ApiError ? remove.error : null

  function toggle(slug: string, checked: boolean) {
    setForm({
      ...form,
      permissions: checked ? [...form.permissions, slug] : form.permissions.filter((item) => item !== slug),
    })
  }

  function submit(event: FormEvent) {
    event.preventDefault()
    save.mutate()
  }

  return (
    <form onSubmit={submit} className="grid gap-6 rounded-lg border bg-card p-5 sm:p-6">
      {readOnly && (
        <p className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
          O papel de administrador sempre tem acesso completo e não pode ser alterado.
        </p>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="role-name">Nome</Label>
          <Input
            id="role-name"
            value={form.name}
            disabled={readOnly}
            aria-invalid={Boolean(saveError?.fieldError('name'))}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
          />
          {saveError?.fieldError('name') && <p className="text-sm text-destructive">{saveError.fieldError('name')}</p>}
        </div>
        <div className="grid gap-2">
          <Label htmlFor="role-description">Descrição</Label>
          <Input
            id="role-description"
            value={form.description}
            disabled={readOnly}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
          />
        </div>
      </div>

      <div className="grid gap-5 sm:grid-cols-2">
        {grouped.map(([module, items]) => (
          <fieldset key={module} className="grid content-start gap-2" disabled={readOnly}>
            <legend className="mb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
              {MODULE_LABELS[module] ?? module}
            </legend>
            {items.map((permission) => (
              <Label key={permission.slug} className="flex items-start gap-2 leading-snug font-normal">
                <Checkbox
                  className="mt-0.5"
                  disabled={readOnly}
                  checked={form.permissions.includes(permission.slug)}
                  onCheckedChange={(checked) => toggle(permission.slug, checked === true)}
                />
                {permission.description}
              </Label>
            ))}
          </fieldset>
        ))}
      </div>

      {(saveError && !saveError.fieldError('name')) || removeError ? (
        <p role="alert" className="text-sm text-destructive">
          {(removeError ?? saveError)?.message}
        </p>
      ) : null}
      {save.isSuccess && (
        <p role="status" className="text-sm text-muted-foreground">
          Papel salvo.
        </p>
      )}

      {!readOnly && (
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Button type="submit" disabled={save.isPending}>
            {save.isPending ? 'Salvando…' : role ? 'Salvar alterações' : 'Criar papel'}
          </Button>
          {role && !role.is_system && (
            <Button
              type="button"
              variant={confirmingDelete ? 'destructive' : 'ghost'}
              disabled={remove.isPending}
              onClick={() => (confirmingDelete ? remove.mutate() : setConfirmingDelete(true))}
            >
              <Trash2 className="size-4" />
              {confirmingDelete ? 'Confirmar exclusão' : 'Excluir papel'}
            </Button>
          )}
        </div>
      )}
    </form>
  )
}
