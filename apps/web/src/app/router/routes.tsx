import { FolderTree, House, Library, ShieldCheck } from 'lucide-react'
import { createBrowserRouter, isRouteErrorResponse, Link, useRouteError } from 'react-router'
import { PanelLayout, type PanelNavItem } from '@/app/layouts/PanelLayout'
import { StudentLayout } from '@/app/layouts/StudentLayout'
import { GuestOnly, RequireAuth, RequirePermission } from './guards'

const ADMIN_NAV: PanelNavItem[] = [
  { to: '/admin/materiais', label: 'Materiais', icon: Library, permission: 'materials.manage_all' },
  { to: '/admin/categorias', label: 'Categorias', icon: FolderTree, permission: 'categories.manage' },
  { to: '/admin/papeis', label: 'Papéis e permissões', icon: ShieldCheck, permission: 'roles.manage' },
]
const TEACHER_NAV: PanelNavItem[] = [{ to: '/professor', label: 'Início', icon: House }]

function RouteError() {
  const error = useRouteError()
  const notFound = isRouteErrorResponse(error) && error.status === 404

  return (
    <main className="dark grid min-h-dvh place-items-center bg-background p-6 text-center text-foreground">
      <div>
        <h1 className="font-heading text-4xl font-bold">{notFound ? 'Página não encontrada' : 'Algo deu errado'}</h1>
        <p className="mt-3 text-muted-foreground">
          {notFound ? 'O endereço não existe ou foi movido.' : 'Recarregue a página para tentar novamente.'}
        </p>
        <Link to="/" className="mt-6 inline-block text-brand hover:underline">
          Voltar ao início
        </Link>
      </div>
    </main>
  )
}

export const router = createBrowserRouter([
  {
    errorElement: <RouteError />,
    children: [
      {
        element: <GuestOnly />,
        children: [
          { path: '/entrar', lazy: async () => ({ Component: (await import('@/modules/auth/pages/LoginPage')).LoginPage }) },
          { path: '/esqueci-senha', lazy: async () => ({ Component: (await import('@/modules/auth/pages/ForgotPasswordPage')).ForgotPasswordPage }) },
          { path: '/redefinir-senha', lazy: async () => ({ Component: (await import('@/modules/auth/pages/ResetPasswordPage')).ResetPasswordPage }) },
        ],
      },
      {
        element: <RequireAuth />,
        children: [
          {
            element: <StudentLayout />,
            children: [
              { index: true, lazy: async () => ({ Component: (await import('@/modules/home/pages/HomePage')).HomePage }) },
            ],
          },
          {
            element: <RequirePermission permission="panel.admin" />,
            children: [
              {
                path: '/admin',
                element: <PanelLayout title="Administração" items={ADMIN_NAV} />,
                children: [
                  { index: true, lazy: async () => ({ Component: (await import('@/modules/admin/pages/AdminIndexPage')).AdminIndexPage }) },
                  {
                    element: <RequirePermission permission="materials.manage_all" />,
                    children: [
                      { path: 'materiais', lazy: async () => ({ Component: (await import('@/modules/materials/pages/MaterialsLibraryPage')).MaterialsLibraryPage }) },
                      { path: 'materiais/novo', lazy: async () => ({ Component: (await import('@/modules/materials/pages/MaterialEditorPage')).MaterialEditorPage }) },
                      { path: 'materiais/:id', lazy: async () => ({ Component: (await import('@/modules/materials/pages/MaterialEditorPage')).MaterialEditorPage }) },
                    ],
                  },
                  {
                    element: <RequirePermission permission="categories.manage" />,
                    children: [
                      { path: 'categorias', lazy: async () => ({ Component: (await import('@/modules/categories/pages/CategoriesPage')).CategoriesPage }) },
                    ],
                  },
                  {
                    element: <RequirePermission permission="roles.manage" />,
                    children: [
                      { path: 'papeis', lazy: async () => ({ Component: (await import('@/modules/admin/pages/RolesPage')).RolesPage }) },
                    ],
                  },
                ],
              },
            ],
          },
          {
            element: <RequirePermission permission="panel.teacher" />,
            children: [
              {
                path: '/professor',
                element: <PanelLayout title="Painel do professor" items={TEACHER_NAV} />,
                children: [
                  { index: true, lazy: async () => ({ Component: (await import('@/modules/teachers/pages/TeacherHomePage')).TeacherHomePage }) },
                ],
              },
            ],
          },
        ],
      },
    ],
  },
])
