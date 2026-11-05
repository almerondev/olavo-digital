import { Bell, BookOpen, CircleUser, ClipboardList, FolderTree, GraduationCap, House, LayoutDashboard, LayoutTemplate, Library, Megaphone, Radio, Settings, ShieldCheck, Users, UsersRound } from 'lucide-react'
import { createBrowserRouter, isRouteErrorResponse, useRouteError } from 'react-router'
import { PanelLayout, type PanelNavItem } from '@/app/layouts/PanelLayout'
import { StudentLayout } from '@/app/layouts/StudentLayout'
import { GuestOnly, RequireAuth, RequirePermission } from './guards'

const ADMIN_NAV: PanelNavItem[] = [
  { to: '/admin', label: 'Visão geral', icon: LayoutDashboard, permission: 'analytics.view_all', end: true },
  { to: '/admin/alunos', label: 'Alunos', icon: Users, permission: 'students.manage' },
  { to: '/admin/turmas', label: 'Turmas e grupos', icon: UsersRound, permission: 'students.manage' },
  { to: '/admin/materiais', label: 'Materiais', icon: Library, permission: 'materials.manage_all' },
  { to: '/admin/home', label: 'Construtor da home', icon: LayoutTemplate, permission: 'home.manage' },
  { to: '/admin/ao-vivo', label: 'Ao vivo agora', icon: Radio, permission: 'live.manage' },
  { to: '/admin/avisos', label: 'Avisos', icon: Megaphone, permission: 'announcements.manage' },
  { to: '/admin/notificacoes', label: 'Notificações', icon: Bell, permission: 'notifications.send' },
  { to: '/admin/cursos', label: 'Cursos', icon: BookOpen, permission: 'categories.manage' },
  { to: '/admin/professores', label: 'Professores', icon: GraduationCap, permission: 'teachers.manage' },
  { to: '/admin/categorias', label: 'Categorias', icon: FolderTree, permission: 'categories.manage' },
  { to: '/admin/papeis', label: 'Papéis e permissões', icon: ShieldCheck, permission: 'roles.manage' },
  { to: '/admin/auditoria', label: 'Auditoria', icon: ClipboardList, permission: 'audit.view' },
  { to: '/admin/configuracoes', label: 'Configurações', icon: Settings, permission: 'settings.manage' },
]
const TEACHER_NAV: PanelNavItem[] = [
  { to: '/professor', label: 'Início', icon: House, end: true },
  { to: '/professor/materiais', label: 'Publicações', icon: Library },
  { to: '/professor/perfil', label: 'Meu perfil', icon: CircleUser },
]

const RELOAD_FLAG = 'olavo:reloaded-after-stale-chunk'

/** Módulo antigo depois de uma atualização: recarregar uma vez resolve, e a marca evita repetir sem fim. */
function reloadIfStaleChunk(error: unknown): boolean {
  const stale = error instanceof Error && /dynamically imported module|Importing a module script failed/i.test(error.message)
  if (!stale) return false

  try {
    if (sessionStorage.getItem(RELOAD_FLAG)) return false
    sessionStorage.setItem(RELOAD_FLAG, '1')
  } catch {
    return false
  }

  location.reload()
  return true
}

function RouteError() {
  const error = useRouteError()
  const notFound = isRouteErrorResponse(error) && error.status === 404

  if (reloadIfStaleChunk(error)) return null

  console.error(error)

  return (
    <main className="dark grid min-h-dvh place-items-center bg-background p-6 text-center text-foreground">
      <div>
        <h1 className="font-heading text-4xl font-bold">{notFound ? 'Página não encontrada' : 'Algo deu errado'}</h1>
        <p className="mt-3 text-muted-foreground">
          {notFound ? 'O endereço não existe ou foi movido.' : 'Recarregue a página para tentar novamente.'}
        </p>
        <a href="/" className="mt-6 inline-block text-brand hover:underline">
          Voltar ao início
        </a>
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
      { path: '/termos', lazy: async () => ({ Component: (await import('@/modules/settings/pages/LegalPage')).TermsPage }) },
      { path: '/privacidade', lazy: async () => ({ Component: (await import('@/modules/settings/pages/LegalPage')).PrivacyPage }) },
      {
        element: <RequireAuth />,
        children: [
          {
            element: <StudentLayout />,
            children: [
              { index: true, lazy: async () => ({ Component: (await import('@/modules/home/pages/HomePage')).HomePage }) },
              { path: 'materiais', lazy: async () => ({ Component: (await import('@/modules/home/pages/HomePage')).MaterialsPage }) },
              { path: 'minha-lista', lazy: async () => ({ Component: (await import('@/modules/favorites/pages/MyListPage')).MyListPage }) },
              { path: 'historico', lazy: async () => ({ Component: (await import('@/modules/history/pages/HistoryPage')).HistoryPage }) },
              { path: 'perfil', lazy: async () => ({ Component: (await import('@/modules/profile/pages/ProfilePage')).ProfilePage }) },
              { path: 'ao-vivo', lazy: async () => ({ Component: (await import('@/modules/olavo-tv/pages/OlavoTvPage')).OlavoTvPage }) },
              { path: 'pesquisa', lazy: async () => ({ Component: (await import('@/modules/communication/pages/SearchPage')).SearchPage }) },
              { path: 'notificacoes', lazy: async () => ({ Component: (await import('@/modules/communication/pages/NotificationsPage')).NotificationsPage }) },
              { path: 'avisos', lazy: async () => ({ Component: (await import('@/modules/communication/pages/AnnouncementsPage')).AnnouncementsPage }) },
              { path: 'elenco', lazy: async () => ({ Component: (await import('@/modules/cast/pages/CastPage')).CastPage }) },
              { path: 'elenco/:slug', lazy: async () => ({ Component: (await import('@/modules/cast/pages/TeacherProfilePage')).TeacherProfilePage }) },
              { path: 'explorar', lazy: async () => ({ Component: (await import('@/modules/catalog/pages/ExplorePage')).ExplorePage }) },
              { path: 'materiais/:slug', lazy: async () => ({ Component: (await import('@/modules/materials/pages/MaterialDetailPage')).MaterialDetailPage }) },
            ],
          },
          {
            element: <RequirePermission permission="home.manage" />,
            children: [
              { path: '/admin/home/:id/previa', lazy: async () => ({ Component: (await import('@/modules/admin/home-builder/pages/HomePreviewPage')).HomePreviewPage }) },
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
                    element: <RequirePermission permission="home.manage" />,
                    children: [
                      { path: 'home', lazy: async () => ({ Component: (await import('@/modules/admin/home-builder/pages/HomeBuilderPage')).HomeBuilderPage }) },
                    ],
                  },
                  {
                    element: <RequirePermission permission="live.manage" />,
                    children: [
                      { path: 'ao-vivo', lazy: async () => ({ Component: (await import('@/modules/admin/live/pages/LiveAdminPage')).LiveAdminPage }) },
                    ],
                  },
                  {
                    element: <RequirePermission permission="teachers.manage" />,
                    children: [
                      { path: 'professores', lazy: async () => ({ Component: (await import('@/modules/teachers/pages/AdminTeachersPage')).AdminTeachersPage }) },
                    ],
                  },
                  {
                    element: <RequirePermission permission="categories.manage" />,
                    children: [
                      { path: 'alunos', lazy: async () => ({ Component: (await import('@/modules/people/pages/StudentsPage')).StudentsPage }) },
                      { path: 'alunos/:id', lazy: async () => ({ Component: (await import('@/modules/people/pages/StudentEditorPage')).StudentEditorPage }) },
                      { path: 'turmas', lazy: async () => ({ Component: (await import('@/modules/people/pages/GroupsPage')).GroupsPage }) },
                      { path: 'auditoria', lazy: async () => ({ Component: (await import('@/modules/reports/pages/AuditPage')).AuditPage }) },
                      { path: 'configuracoes', lazy: async () => ({ Component: (await import('@/modules/settings/pages/SettingsPage')).SettingsPage }) },
                      { path: 'avisos', lazy: async () => ({ Component: (await import('@/modules/communication/pages/AdminAnnouncementsPage')).AdminAnnouncementsPage }) },
                      { path: 'notificacoes', lazy: async () => ({ Component: (await import('@/modules/communication/pages/AdminNotificationsPage')).AdminNotificationsPage }) },
                      { path: 'cursos', lazy: async () => ({ Component: (await import('@/modules/categories/pages/CoursesPage')).CoursesPage }) },
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
                  { path: 'materiais', lazy: async () => ({ Component: (await import('@/modules/teachers/pages/TeacherMaterialsPage')).TeacherMaterialsPage }) },
                  { path: 'materiais/novo', lazy: async () => ({ Component: (await import('@/modules/teachers/pages/TeacherMaterialEditorPage')).TeacherMaterialEditorPage }) },
                  { path: 'materiais/:id', lazy: async () => ({ Component: (await import('@/modules/teachers/pages/TeacherMaterialEditorPage')).TeacherMaterialEditorPage }) },
                  { path: 'perfil', lazy: async () => ({ Component: (await import('@/modules/teachers/pages/TeacherOwnProfilePage')).TeacherOwnProfilePage }) },
                ],
              },
            ],
          },
        ],
      },
    ],
  },
])
