import { Navigate } from 'react-router'
import { useCurrentUser } from '@/modules/auth/hooks/useCurrentUser'
import { DashboardPage } from '@/modules/reports/pages/DashboardPage'

/** Perfis sem acesso aos números abrem na primeira tela liberada. */
export function AdminIndexPage() {
  const { data: user } = useCurrentUser()

  if (user?.permissions.includes('analytics.view_all')) return <DashboardPage />
  if (user?.permissions.includes('materials.manage_all')) return <Navigate to="/admin/materiais" replace />
  if (user?.permissions.includes('students.manage')) return <Navigate to="/admin/alunos" replace />
  if (user?.permissions.includes('categories.manage')) return <Navigate to="/admin/categorias" replace />
  if (user?.permissions.includes('roles.manage')) return <Navigate to="/admin/papeis" replace />
  return <p className="text-muted-foreground">Nenhuma área do painel está liberada para o seu perfil.</p>
}
