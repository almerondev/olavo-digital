import { FileUp, X } from 'lucide-react'
import { useId, useRef, useState, type ChangeEvent } from 'react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { ApiError } from '@/services/http'
import { uploadFile } from '@/services/upload'
import type { Upload } from '@/types/api'
import { formatBytes } from '@/utils/format'

interface UploadFieldProps {
  label: string
  purpose: 'material' | 'image'
  accept?: string
  value: Upload | null
  error?: string
  onChange: (upload: Upload | null) => void
}

type Progress = { stage: 'sending'; percent: number } | { stage: 'processing' } | null

export function UploadField({ label, purpose, accept, value, error, onChange }: UploadFieldProps) {
  const id = useId()
  const input = useRef<HTMLInputElement>(null)
  const [progress, setProgress] = useState<Progress>(null)
  const [failure, setFailure] = useState<string | null>(null)

  async function send(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return

    setFailure(null)
    setProgress({ stage: 'sending', percent: 0 })

    try {
      const upload = await uploadFile(file, purpose, {
        onProgress: (percent) => setProgress({ stage: 'sending', percent }),
        onProcessing: () => setProgress({ stage: 'processing' }),
      })

      if (upload.status === 'rejected') setFailure(upload.rejection_reason ?? 'O arquivo foi recusado.')
      else onChange(upload)
    } catch (caught) {
      const apiError = caught instanceof ApiError ? caught : null
      setFailure(apiError?.fieldError('name') ?? apiError?.fieldError('size') ?? apiError?.message ?? 'O envio falhou.')
    } finally {
      setProgress(null)
    }
  }

  const preview = value?.image_urls ? Object.values(value.image_urls)[0] : undefined
  const message = failure ?? error

  return (
    <div className="grid gap-2">
      <Label htmlFor={id}>{label}</Label>
      <input id={id} ref={input} type="file" accept={accept} className="sr-only" onChange={send} disabled={progress !== null} />

      {value && (
        <div className="flex items-center gap-3 rounded-lg border p-2 text-sm">
          {preview && <img src={preview} alt="" className="h-12 w-20 rounded object-cover" />}
          <div className="min-w-0 flex-1">
            <p className="truncate font-medium">{value.original_name}</p>
            <p className="text-xs text-muted-foreground">{formatBytes(value.size_bytes)}</p>
          </div>
          <Button type="button" variant="ghost" size="icon" aria-label={`Remover ${value.original_name}`} onClick={() => onChange(null)}>
            <X className="size-4" />
          </Button>
        </div>
      )}

      {progress ? (
        <div role="status" className="grid gap-1 text-sm text-muted-foreground">
          {progress.stage === 'sending' ? `Enviando… ${progress.percent}%` : 'Validando o arquivo…'}
          <div className="h-1.5 overflow-hidden rounded bg-muted">
            <div
              className="h-full bg-primary transition-[width]"
              style={{ width: progress.stage === 'sending' ? `${progress.percent}%` : '100%' }}
            />
          </div>
        </div>
      ) : (
        <Button type="button" variant="outline" className="justify-start" onClick={() => input.current?.click()}>
          <FileUp className="size-4" />
          {value ? 'Trocar arquivo' : 'Escolher arquivo'}
        </Button>
      )}

      {message && (
        <p role="alert" className="text-sm text-destructive">
          {message}
        </p>
      )}
    </div>
  )
}
