const API_BASE = '/api/v1'
const WRITE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly errors: Record<string, string[]>

  constructor(status: number, code: string, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.code = code
    this.errors = errors
  }

  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

function readXsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

function refreshCsrfCookie(): Promise<Response> {
  return fetch('/sanctum/csrf-cookie', { credentials: 'include' })
}

interface RequestOptions {
  method?: string
  body?: unknown
  keepalive?: boolean
}

async function send(path: string, { method = 'GET', body, keepalive }: RequestOptions): Promise<Response> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  if (WRITE_METHODS.has(method)) {
    if (!readXsrfToken()) await refreshCsrfCookie()
    headers['X-XSRF-TOKEN'] = readXsrfToken() ?? ''
  }

  return fetch(`${API_BASE}${path}`, {
    method,
    headers,
    credentials: 'include',
    keepalive,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
}

export async function api<T>(path: string, options: RequestOptions = {}): Promise<T> {
  let response: Response

  try {
    response = await send(path, options)

    if (response.status === 419) {
      await refreshCsrfCookie()
      response = await send(path, options)
    }
  } catch {
    throw new ApiError(0, 'network_error', 'Não foi possível conectar. Verifique sua internet e tente novamente.')
  }

  if (response.status === 204) return undefined as T

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(
      response.status,
      payload?.code ?? 'unknown_error',
      payload?.message ?? 'Algo deu errado. Tente novamente.',
      payload?.errors,
    )
  }

  return payload as T
}
