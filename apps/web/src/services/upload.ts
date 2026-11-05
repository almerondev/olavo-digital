import { ApiError, api } from '@/services/http'
import type { Upload } from '@/types/api'

interface UploadTarget {
  url: string
  method: string
  headers: Record<string, string>
}

const POLL_INTERVAL_MS = 800
const POLL_ATTEMPTS = 150

function putFile(file: File, target: UploadTarget, onProgress: (percent: number) => void): Promise<void> {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open(target.method, target.url)
    Object.entries(target.headers).forEach(([name, value]) => xhr.setRequestHeader(name, value))

    // O envio local passa pelo Laravel e usa a sessão; a URL pré-assinada do R2 dispensa os dois.
    if (new URL(target.url, location.href).origin === location.origin) {
      const xsrf = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1]
      xhr.withCredentials = true
      if (xsrf) xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(xsrf))
    }

    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) onProgress(Math.round((event.loaded / event.total) * 100))
    }
    xhr.onload = () =>
      xhr.status < 300 ? resolve() : reject(new ApiError(xhr.status, 'upload_failed', 'O envio falhou. Tente novamente.'))
    xhr.onerror = () => reject(new ApiError(0, 'upload_failed', 'A conexão caiu durante o envio. Tente novamente.'))
    xhr.send(file)
  })
}

async function waitForProcessing(uuid: string): Promise<Upload> {
  for (let attempt = 0; attempt < POLL_ATTEMPTS; attempt++) {
    const { data } = await api<{ data: Upload }>(`/uploads/${uuid}`)
    if (data.status === 'ready' || data.status === 'rejected') return data
    await new Promise((resolve) => setTimeout(resolve, POLL_INTERVAL_MS))
  }

  throw new ApiError(0, 'upload_timeout', 'O processamento está demorando. Confira o arquivo mais tarde.')
}

export interface UploadCallbacks {
  onProgress: (percent: number) => void
  onProcessing: () => void
}

export async function uploadFile(file: File, purpose: 'material' | 'image' | 'avatar', callbacks: UploadCallbacks): Promise<Upload> {
  const { data, target } = await api<{ data: Upload; target: UploadTarget }>('/uploads', {
    method: 'POST',
    body: { purpose, name: file.name, size: file.size },
  })

  await putFile(file, target, callbacks.onProgress)
  callbacks.onProcessing()
  await api(`/uploads/${data.uuid}/complete`, { method: 'POST' })

  return waitForProcessing(data.uuid)
}
