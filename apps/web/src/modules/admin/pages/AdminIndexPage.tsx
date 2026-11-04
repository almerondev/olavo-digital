import { Navigate } from 'react-router'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'

/** O dashboard entra na fase 8; até lá, o painel abre na primeira tela disponível. */
export function AdminIndexPage() {
  const { data: user } = useCurrentUser()

  if (user?.permissions.includes('materials.manage_all')) return <Navigate to="/admin/materiais" replace />
  if (user?.permissions.includes('categories.manage')) return <Navigate to="/admin/categorias" replace />
  if (user?.permissions.includes('roles.manage')) return <Navigate to="/admin/papeis" replace />
  return <p className="text-muted-foreground">Nenhuma área do painel está liberada para o seu perfil.</p>
}
