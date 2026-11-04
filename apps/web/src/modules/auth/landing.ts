import type { User } from '@/types/api'

/** Cada perfil começa na própria interface após o login. */
export function landingPath(user: User): string {
  if (user.permissions.includes('panel.admin')) return '/admin'
  if (user.permissions.includes('panel.teacher')) return '/professor'
  return '/'
}
