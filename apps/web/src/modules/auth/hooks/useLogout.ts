import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { stopPush } from '@/modules/native/push'
import { authApi } from '../api/auth'

export function useLogout() {
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  return useMutation({
    mutationFn: async () => {
      await stopPush()
      await authApi.logout()
    },
    onSettled: () => {
      queryClient.clear()
      navigate('/entrar', { replace: true })
    },
  })
}
