import { useQuery } from '@tanstack/react-query'
import { ApiError } from '@/services/http'
import { authApi } from '../api/auth'

export const CURRENT_USER_KEY = ['current-user']

export function useCurrentUser() {
  return useQuery({
    queryKey: CURRENT_USER_KEY,
    queryFn: async () => {
      try {
        return await authApi.me()
      } catch (error) {
        if (error instanceof ApiError && (error.status === 401 || error.code === 'account_blocked')) return null
        throw error
      }
    },
    staleTime: 5 * 60 * 1000,
  })
}
