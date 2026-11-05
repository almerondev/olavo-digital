import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useEffect } from 'react'
import { RouterProvider } from 'react-router'
import { router } from '@/app/router/routes'
import { ConfigEffects } from '@/modules/settings/config'
import { ApiError } from '@/services/http'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry: (count, error) => !(error instanceof ApiError && error.status < 500 && error.status > 0) && count < 2,
    },
  },
})

export function AppProviders() {
  useEffect(() => {
    const timer = setTimeout(() => {
      try {
        sessionStorage.removeItem('olavo:reloaded-after-stale-chunk')
      } catch {
        // Armazenamento bloqueado: só perde a proteção contra recarga repetida.
      }
    }, 10000)

    return () => clearTimeout(timer)
  }, [])

  return (
    <QueryClientProvider client={queryClient}>
      <ConfigEffects />
      <RouterProvider router={router} />
    </QueryClientProvider>
  )
}
